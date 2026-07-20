# Runbook: Rename `kimai2_*` tables + `auth='kimai'` data to gppro branding

Related SDD change: `rename-kimai-tables` (proposal / spec / design / tasks in Engram, topic
`sdd/rename-kimai-tables/*`).

This runbook covers backup, backup verification, deployment, rollback, and post-deploy
verification for the migration that renames all 35 `kimai2_*` physical MySQL tables to
`gppro_*` and migrates `gppro_users.auth = 'kimai'` rows to `auth = 'internal'`.

**Scope of this document**: procedure only. The two Doctrine migrations it references
(`Version{ts}01` — schema rename, `Version{ts}02` — auth data update) are delivered in PR2
and PR3 of this stacked chain and do not exist yet at the time this runbook is written (PR1).
Do not run the "Apply" or "Rollback" sections below until both migrations have merged to
`main` and been deployed together.

---

## 1. Pre-requisites

Before touching any real-data environment (staging or production):

- [ ] Confirm DB credentials and connectivity. Resolve the actual host/user/db from the
      deploy target's `DATABASE_URL` (Symfony's env var, format documented in `.env.dist`):

  ```bash
  # Example DATABASE_URL: mysql://user:password@127.0.0.1:3306/database?charset=utf8mb4&serverVersion=10.5.8-MariaDB
  # Parse it the same way .docker/entrypoint.sh does, or read the values directly from
  # your deploy environment (docker-compose env, webserver vhost, secrets manager, etc.)
  echo "$DATABASE_URL"
  ```

  Test connectivity before proceeding:

  ```bash
  mysql -h <DATABASE_HOST> -P <DATABASE_PORT> -u <DATABASE_USER> -p <DATABASE_NAME> -e "SELECT 1;"
  ```

- [ ] Confirm available disk space for the dump. As a rule of thumb, reserve at least
      2x the current database size (dump file + working room for the restore test):

  ```bash
  du -sh /var/lib/mysql/<DATABASE_NAME> 2>/dev/null || \
    mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
    "SELECT table_schema AS db, ROUND(SUM(data_length+index_length)/1024/1024,1) AS size_mb \
     FROM information_schema.tables WHERE table_schema='<DATABASE_NAME>' GROUP BY table_schema;"
  df -h /var/backups
  ```

- [ ] Confirm the maintenance window if this is a production run (Phase 5 of the SDD
      tasks). Staging dry-runs (Phase 3) do not require a maintenance window but must not
      run against production itself.
- [ ] Confirm `bin/console` is reachable from the deploy target (container exec or SSH):

  ```bash
  bin/console --version
  ```

---

## 2. Backup

Take a full logical backup with `mysqldump` before any migration touches real data
(staging-restored or production). `--single-transaction` gives a consistent InnoDB
snapshot without locking tables; `--routines --triggers` captures stored routines and
triggers so the dump is a complete restore point.

```bash
mysqldump --single-transaction --routines --triggers \
  -h <DATABASE_HOST> -P <DATABASE_PORT> -u <DATABASE_USER> -p <DATABASE_NAME> \
  > gppro_pre_rename_$(date +%Y%m%d%H%M%S).sql
```

Record the resulting filename and its checksum — this is the disaster-recovery
artifact referenced by the rollback section below and by SDD tasks 0.2, 5.2:

```bash
sha256sum gppro_pre_rename_*.sql
```

Store the dump (and checksum) somewhere outside the DB host itself (e.g. a backup
bucket or the ops backup share) before continuing.

---

## 3. Verify the backup (mandatory before continuing)

Never trust an unverified backup. Restore it into a throwaway/scratch database and
confirm both the schema and a known row count match.

```bash
# 1. Create a scratch database
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e "CREATE DATABASE test_restore;"

# 2. Restore the dump into it
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p test_restore < gppro_pre_rename_YYYYMMDDHHMMSS.sql

# 3. Compare table counts between source and restored DB
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='<DATABASE_NAME>';"
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='test_restore';"

# 4. Compare row count on a key table (kimai2_users, pre-rename name) between source and restore
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
  "SELECT COUNT(*) FROM <DATABASE_NAME>.kimai2_users;"
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
  "SELECT COUNT(*) FROM test_restore.kimai2_users;"
```

Both counts (table count and `kimai2_users` row count) MUST match exactly. If you can,
also confirm a known user can log in against an app instance pointed at `test_restore`
(this is SDD task 0.2's required evidence — see the SDD tasks artifact).

Clean up the scratch database once verification passes:

```bash
mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e "DROP DATABASE test_restore;"
```

Do not proceed past this section until the backup has been proven to restore correctly.

---

## 4. Apply the migration (real deploy)

Applies to PR2 (schema rename, `Version{ts}01`) and PR3 (auth data migration,
`Version{ts}02`) once both have merged. Per the design, both migrations MUST ship in
the same deploy so there is no window where the schema is renamed but the auth
constant/data are not yet migrated (or vice versa).

### 4a. Docker deploy (production topology)

This project's single-container image runs migrations automatically via the entrypoint
(`.docker/entrypoint.sh`), which calls `prepareGppro()` → `gppro:install` **before**
`runServer()` starts serving requests:

```bash
# from .docker/entrypoint.sh:
# /opt/gppro/bin/console -n gppro:install   (runs BEFORE runServer)
```

So for a Docker deploy, simply deploying the new image (containing both migration
classes and the updated entity/code files) is sufficient — `gppro:install` internally
invokes `doctrine:migrations:migrate --allow-no-migration` (see
`src/Command/InstallCommand.php`) and applies both pending migrations in version order
(`Version{ts}01` then `Version{ts}02`) before the server accepts traffic. There is no
manual migration step required in this path.

### 4b. Manual deploy (no Docker / direct console access)

If deploying without the Docker entrypoint (e.g. bare-metal or a manual step), run the
migration explicitly after deploying the new code, before restarting the app server:

```bash
bin/console doctrine:migrations:migrate --no-interaction
```

This applies all pending migrations up to the latest version, in creation order
(`Version{ts}01` — schema rename — then `Version{ts}02` — auth data update).

To confirm status before/after:

```bash
bin/console doctrine:migrations:status
bin/console doctrine:migrations:list
```

---

## 5. Rollback

### 5a. Failure DURING the migration (before it completes)

Per design, the schema rename (`Version{ts}01`) is a single atomic multi-table
`RENAME TABLE` statement — MySQL/MariaDB guarantees it is all-or-nothing even if the
connection drops mid-statement, so there is no partial-rename state to clean up. The
auth data update (`Version{ts}02`) is a single `UPDATE` statement, also all-or-nothing.

Abort procedure:

1. Confirm no partial state exists:

   ```bash
   mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
     "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='<DATABASE_NAME>' AND table_name LIKE 'gppro_%';"
   mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p -e \
     "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='<DATABASE_NAME>' AND table_name LIKE 'kimai2_%';"
   ```

   You should see either 35/0 (fully renamed) or 0/35 (not yet renamed) — never a mix.

2. If the migration process died but the statement itself did not corrupt the schema,
   simply re-run it:

   ```bash
   bin/console doctrine:migrations:migrate --no-interaction
   ```

3. If you need to stop and not retry immediately, revert to the previous migration
   version explicitly:

   ```bash
   bin/console doctrine:migrations:migrate prev --no-interaction
   ```

### 5b. Migration completed but a problem is detected AFTER (e.g. login broken)

Roll back the code deploy AND the migrations together — do not leave code and schema
out of sync.

1. Revert migrations to the version before `Version{ts}01`, which reverses both
   migrations **in the correct order**: Doctrine runs `down()` in reverse-creation
   order, so `Version{ts}02.down()` (auth data revert: `auth='internal'` →
   `auth='kimai'`) runs first, while the table is still named `gppro_users`, and only
   then does `Version{ts}01.down()` (table rename revert: `gppro_*` → `kimai2_*`) run:

   ```bash
   bin/console doctrine:migrations:migrate prev --no-interaction
   bin/console doctrine:migrations:migrate prev --no-interaction
   ```

   (Two invocations of `prev` — one per migration — or target the specific pre-change
   version directly: `bin/console doctrine:migrations:migrate <version-before-01> --no-interaction`.)

2. Redeploy the previous application image/code alongside the reverted schema.

3. If the schema is corrupted beyond what migration rollback can fix (e.g. manual
   intervention broke something further), restore from the verified `mysqldump` taken
   in Section 2:

   ```bash
   mysql -h <DATABASE_HOST> -u <DATABASE_USER> -p <DATABASE_NAME> < gppro_pre_rename_YYYYMMDDHHMMSS.sql
   ```

   This is the last-resort disaster-recovery path — only use it if migration `down()`
   rollback is not sufficient.

---

## 6. Post-deploy verification checklist

Run all of the following after every real deploy (staging dry-run in Phase 3, and
production in Phase 5). All queries must return the exact expected value before the
deploy is considered complete (per spec Requirement "Post-Migration Verification Gate").

```sql
-- Expect: 35
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'gppro_%';

-- Expect: 0
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'kimai2_%';

-- Expect: 0
SELECT COUNT(*) FROM gppro_users WHERE auth = 'kimai';

-- Expect: only NULL / 'internal' / 'ldap' / 'saml' values
SELECT auth, COUNT(*) FROM gppro_users GROUP BY auth;
```

CLI leak check:

```bash
bin/console gppro:user:list | rg -i kimai
# Expect: no output (0 matches). Internal users must show "internal" in the auth column.
```

Manual smoke test (minimum):

1. Log in with a known local-password (internal-auth) user.
2. Navigate to the password-change page and confirm it loads and the password can be
   changed successfully.
3. If verifying a mid-deploy session-survival concern, confirm a user who was logged
   in **before** the deploy remains logged in after (no forced re-authentication).

If any check fails, follow Section 5b (post-completion rollback) immediately — do not
leave the environment in a partially-verified state.

---

## 7. Contacts / execution log

Fill in before each real execution (staging or production):

| Field | Value |
|-------|-------|
| Executed by | _(fill in)_ |
| Date/time | _(fill in)_ |
| Environment (staging / production) | _(fill in)_ |
| Backup file path + checksum | _(fill in)_ |
| Approved by | _(fill in)_ |
| Maintenance window (if production) | _(fill in)_ |
| Outcome (success / rolled back) | _(fill in)_ |
