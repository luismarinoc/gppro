# Codebase Health Baseline Report

## Checkout Identity

- Repository root: `/root/dev/gppro`
- Commit: `f580368` (`fix/deploy: restrict app port to localhost`)
- Baseline version: `codebase-health-baseline/v1`
- Assessment entry point: `tools/codebase-health-baseline/assess.sh`

## Baseline Claim

**No passing baseline claim** is made. The ledger explicitly records `baseline_claim: unavailable` because Docker, Composer, PHPUnit, database, migration, frontend, and related runtime prerequisites were not available for this assessment. Unavailable checks are not passes.

## Checks And Ledger

The authoritative evidence ledger is `docs/runbooks/codebase-health-baseline/ledger.json`. It records eight checks with their procedures, inputs, results, availability, reproducibility, evidence class, source references, and confidence impact:

- Compose build and health: unavailable; Docker validation was not available.
- Database bootstrap: unavailable; container and dependency prerequisites were unavailable.
- Migration forward/replay: unavailable because database bootstrap was unavailable.
- PHPUnit: unavailable; `vendor/bin/phpunit` is absent.
- Composer lint: unavailable; Composer/vendor prerequisites were unavailable.
- PHP-CS-Fixer: unavailable.
- PHPStan: unavailable.
- Frontend install, audit, and lint: unavailable; frontend dependency prerequisites were unavailable.

The ledger also records these confirmed repository signals:

- `phpstan.neon` and `tests/phpstan.neon` contain `ignoreErrors` entries; measured counts require PHPStan execution.
- README, Dockerfile, and CI describe different PHP/database runtime sources; owner review is required before compatibility priority.
- Docker image healthchecks exist, while Compose has no healthcheck wait condition for the app dependency; executable validation is required.
- The unavailable quality result remains a hypothesis, not a failure or pass.

## Signals And Priorities

`docs/runbooks/codebase-health-baseline/backlog.md` is the ranked, bounded follow-up ledger. Only reproducible findings receive priority:

- P1: CI/runtime matrix drift across README, Dockerfile, and CI, classified as a verified fact.
- P2: Explicit PHPStan ignored errors, classified as a verified fact.
- Informational: unavailable quality result, classified as a hypothesis; it receives no remediation priority.

Migration, database, PHPUnit, frontend, and health checks remain follow-up validation until reproducibly executed.

## Reproduction Commands

Run from the exact checkout:

```bash
bash tools/codebase-health-baseline/assess.sh /root/dev/gppro
bash tools/codebase-health-baseline/test-assessment.sh
python3 -m json.tool docs/runbooks/codebase-health-baseline/ledger.json
```

The assessment must emit the exact repository root and commit, `BASELINE_CLAIM=unavailable`, and the ledger/report paths. Relative paths outside the repository root and a mismatched `--expected-root` are rejected.

When prerequisites are available, follow the procedures recorded in `ledger.json`; do not substitute host success for exact-checkout Docker/dependency evidence.

## Scope Guard

This report is diagnostic only. It records findings and bounded future follow-up slices; it does not claim remediation is complete. The ActivityBoard production fix is preserved in its own prior commit and is not part of the baseline worktree changes. The baseline does not change application source, templates, assets, database, migrations, runtime state, generated outputs, CI behavior, or secrets. No unavailable or non-repeatable check is represented as a passing result.
