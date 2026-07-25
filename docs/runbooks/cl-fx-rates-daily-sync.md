# Runbook: Chilean daily FX rate sync (USD/UF from mindicador.cl)

Related SDD change: `cl-fx-rates-daily-sync` (spec / design / tasks in Engram, topic
`sdd/cl-fx-rates-daily-sync/*`).

This runbook covers what the `gppro:fx-rates:sync` console command does, how to run it
manually for backfill or correction, and how to configure its production trigger via a
Dokploy scheduled job.

**Scope of this document**: the command and its automated trigger (PR3 of this change).
The `/admin/fx-rates` web panel (viewing, backfilling, and correcting entries without
CLI access) is delivered in PR4/PR5 and is not covered here.

---

## 1. What the command does

```
bin/console gppro:fx-rates:sync [--date=YYYY-MM-DD] [--force]
```

Fetches the daily USD ("dolar") and UF values published by
[mindicador.cl](https://mindicador.cl) and persists each as its own
`(date, indicator)` row in `gppro_fx_rates`. USD and UF are fetched, evaluated, and
persisted **independently** — a failure or no-data result for one never blocks the
other.

- **Without `--date`**: syncs "today", computed in `America/Santiago` — never the
  host/container timezone. This project's Docker image bakes `Europe/Berlin` as the
  default TZ (`.docker/Dockerfile`, `ARG TIMEZONE`), so the command deliberately never
  reads the ambient clock's calendar date; it resolves the current UTC instant and
  converts it to the Chilean calendar day itself.
- **With `--date=YYYY-MM-DD`**: syncs that specific past (or today's) Chilean calendar
  date. Used for backfill and manual correction. A future Chilean date is rejected.
- **With `--force`**: overwrites an existing `(date, indicator)` row instead of leaving
  it untouched. Without `--force`, a re-run for a date/indicator that already has a row
  is a no-op for that indicator — this is the command's idempotency guarantee, safe to
  run repeatedly (e.g. twice a day, see §3).

### Exit codes

| Code | Meaning |
|---|---|
| `0` (SUCCESS) | Every indicator either persisted a value, was skipped because a row already existed (no `--force`), or legitimately had no data for that date. **A weekend/holiday with no published value is exit 0** — mindicador.cl simply has nothing to report, this is expected, not a failure. |
| `1` (FAILURE) | At least one indicator failed with a transport error, a non-404 non-2xx HTTP status, or malformed JSON. The other indicator's result (if it succeeded) is still persisted. |
| `2` (INVALID) | `--date` could not be parsed (expected `YYYY-MM-DD`), or is a future Chilean calendar date. Nothing is persisted. |

A non-zero exit code (`1` or `2`) is the only case that should page/alert. Exit `0`
covers both "synced successfully" and "no data today (normal)".

---

## 2. Manual usage (backfill / correction)

Run these from a shell with `bin/console` access (container exec or SSH), same as any
other `bin/console` command in this project.

### Backfill a specific past date

```bash
bin/console gppro:fx-rates:sync --date=2026-07-20
```

Fetches and persists USD/UF for `2026-07-20` only if no row currently exists for that
`(date, indicator)`. Existing rows are left untouched and reported as skipped.

### Force-correct a wrong or stale value

```bash
bin/console gppro:fx-rates:sync --date=2026-07-20 --force
```

Re-fetches `2026-07-20` from mindicador.cl and overwrites whatever is currently stored,
refreshing `modifiedAt`. Use this when a value was recorded incorrectly (e.g. the
source published a late correction) or when the automated run captured a transient bad
value.

### Re-run today's sync manually (out-of-band)

```bash
bin/console gppro:fx-rates:sync
```

Safe to run at any time — if today's values are already persisted, this is a no-op; if
they're missing (e.g. the scheduled job didn't fire), it fills them in.

### Checking the result

The command prints one line per indicator (persisted / skipped / no-data / failed) and
a final summary line, and mirrors the same information to the application log (`info`
for normal outcomes, `error` for real failures) so both interactive runs and cron
output are auditable via `bin/console` output or the log aggregator.

---

## 3. Production trigger: Dokploy scheduled job

**Decision (design)**: use a Dokploy scheduled job running `bin/console
gppro:fx-rates:sync` (no `--date`, no `--force`) at two fixed UTC times per day:

```
0 12 * * *   (12:00 UTC)
0 20 * * *   (20:00 UTC)
```

### Why two runs, and why these times

Chile alternates between UTC-4 (standard/"winter") and UTC-3 (DST/"summer"), so any
fixed UTC hour drifts by one Chilean local hour twice a year. Because the command
computes its own Santiago calendar date (§1) rather than trusting the trigger's clock,
and the sync is idempotent without `--force`, the exact minute of either run is
irrelevant to correctness — both trigger times always land inside the same Chilean
calendar day regardless of DST:

- **12:00 UTC** → 08:00 (UTC-4) or 09:00 (UTC-3) in Santiago. mindicador.cl publishes
  the day's values early, so this run is expected to succeed with data on business
  days.
- **20:00 UTC** → 16:00 or 17:00 in Santiago. This second run is a **free self-heal**:
  if the 12:00 UTC run failed (transient outage) or the job didn't fire, this run picks
  it up. If the 12:00 UTC run already succeeded, this run costs one no-op query per
  indicator (both already persisted, nothing to overwrite since `--force` is not
  passed).

Weekends and Chilean holidays: mindicador.cl has no value to publish, so both runs
exit `0` with a "no data" result for that day — this is expected and does not need
attention.

### Setting up the job in Dokploy

1. In the Dokploy project for this deployment, add a **Scheduled Job** (not a one-off
   task) targeting the running `gppro` application container/service.
2. Command: `bin/console gppro:fx-rates:sync` (adjust the working directory/entrypoint
   prefix to match how other scheduled/cron commands are already invoked in this
   Dokploy project, if any exist).
3. Schedule: create **two** separate scheduled job entries, one per cron expression
   above (`0 12 * * *` and `0 20 * * *`), both in UTC (confirm Dokploy's job scheduler
   timezone setting — if Dokploy's scheduler runs in a different timezone than UTC,
   convert the two cron expressions accordingly, but keep both runs on the same
   calendar day in Chile per the reasoning above).
4. Confirm the job's exit code is surfaced in Dokploy's job history/logs, so a
   non-zero (`1` or `2`) exit is visible without needing to grep the application log.
5. After the first scheduled run, verify a `gppro_fx_rates` row exists for both `dolar`
   and `uf` for the current Chilean date (see §4).

### Fallback: no Dokploy scheduled jobs available

If this Dokploy instance does not support scheduled jobs, fall back to a host crontab
entry invoking the command via `docker exec` against the running container, using the
same two UTC times:

```cron
0 12 * * * docker exec <container_name> bin/console gppro:fx-rates:sync >> /var/log/gppro-fx-rates-sync.log 2>&1
0 20 * * * docker exec <container_name> bin/console gppro:fx-rates:sync >> /var/log/gppro-fx-rates-sync.log 2>&1
```

This fallback was rejected as the default per design (survives redeploys worse, needs
host SSH access, logs are not surfaced in the same UI as the app) but is documented
here in case the target Dokploy instance turns out not to offer scheduled jobs.

`symfony/scheduler` was also considered and rejected — it requires a supervised worker
process, which this deployment does not run.

---

## 4. Failure triage

### Exit code 1 (FAILURE) — real outage or bad response

1. Check the application log around the failure time for the `error`-level message —
   it includes the indicator (`dolar` or `uf`) and the underlying transport/parse
   error.
2. Manually re-run the command for the affected date once the underlying issue (e.g.
   mindicador.cl outage, network issue) is resolved:

   ```bash
   bin/console gppro:fx-rates:sync --date=<affected-date>
   ```

   (No `--force` needed if the failing indicator never persisted a row in the first
   place — it will be treated as missing and filled in normally.)
3. If the 20:00 UTC scheduled run already self-healed the failure (common case for a
   transient blip caught by the 12:00 UTC run), no manual action is required — confirm
   via §5 that both indicators have a row for the date in question.

### Exit code 2 (INVALID) — only relevant to manual/backfill usage

The scheduled job never passes `--date`, so this exit code should only ever appear
during manual backfill. It means the `--date` value was not `YYYY-MM-DD` or was a
future Chilean calendar date. Re-run with a corrected value.

### Persistent no-data (exit 0, but nothing persisted) on a day that should have data

If a weekday that should have published values is coming back empty repeatedly:

1. Manually check `https://mindicador.cl/api/dolar/<dd-mm-yyyy>` and
   `https://mindicador.cl/api/uf/<dd-mm-yyyy>` in a browser to confirm whether the
   upstream API actually has the data.
2. If the upstream API has the data but the command reports no-data, this points to a
   parsing regression — check the application log for details and escalate to
   engineering; do not attempt to hand-enter values via SQL (once the admin panel from
   PR4/PR5 ships, use it instead to backfill/correct through the validated form).

---

## 5. Verifying stored data

```bash
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p <DATABASE_NAME> -e \
  "SELECT date, indicator, rate_value, modified_at FROM gppro_fx_rates ORDER BY date DESC LIMIT 10;"
```

Each Chilean business day should show two rows (`dolar` and `uf`) once both scheduled
runs have executed. Weekends/holidays legitimately show no rows for that date.
