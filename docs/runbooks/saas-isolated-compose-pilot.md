# Isolated Compose pilot (non-production only)

Use distinct Compose project names and loopback host ports for two fixture instances. Prepare separate external, non-production env files with placeholder fixture values for the existing required Compose variables. Set `GPPRO_HOST_PORT=8001` in the first file and `GPPRO_HOST_PORT=8002` in the second. Do not put real credentials in this repository.

```sh
bash tools/saas-instance-preflight.sh pilot-one 8001 pilot-two 8002
docker compose -p pilot-one --env-file /path/to/pilot-one.env config
docker compose -p pilot-two --env-file /path/to/pilot-two.env config
```

The preflight checks only the declared pair: project-name syntax, decimal host-port range, and distinct project names and host ports. It does not check occupied host ports, running Compose projects, or whether the env files match the declared ports. The Compose commands illustrate configuration inspection only; they do not start containers. The app binds to `127.0.0.1` on the selected host port, while its container port remains `8001`. Without `GPPRO_HOST_PORT`, the host port defaults to `8001`. The `gppro_data`, `gppro_plugins`, and `gppro_db` volumes have no external or explicit global names, so Compose scopes them by project name. Reusing a project name reuses its volumes; unique names and ports are required for separate fixtures.

Docker/Podman was unavailable for this pilot, so Compose configuration, container behavior, data isolation, and runtime readiness were not validated. This does not provision customers or address monitoring, authentication, billing, or production deployment.

## Per-instance backup command (unverified runtime)

Backups must be stored outside the repository in an existing directory. Choose a new output filename; existing dumps or checksum files are refused. From the repository root, inspect the dry run before the real invocation:

```sh
bash tools/saas-instance-backup.sh --dry-run pilot-one /path/outside/repository/pilot-one.sql
bash tools/saas-instance-backup.sh pilot-one /path/outside/repository/pilot-one.sql
```

The dry run prints the dump command and checksum step without calling Docker or creating files. The real invocation exclusively reserves both `pilot-one.sql` and `pilot-one.sql.sha256` with owner-only permissions before dumping, so a pre-existing file, directory, or symlink at either path makes it fail instead of writing elsewhere. The checksum is computed from the streamed dump bytes, and the run fails if either reserved path was replaced while it ran. The partial dump is visible at its final path while the dump runs; a failure removes only the regular files this run reserved. The destination directory must be private to the operator: this is not protection against a hostile process continuously modifying that directory. Credentials are expanded inside the existing `mysql` container; the script does not read env files or print credential values. Treat dumps as sensitive data and restrict access to the external backup directory.

The dump is **NOT tested against Docker**. There is no restore procedure or verified recovery guarantee; dry-run contract tests are not backup/restore validation.
