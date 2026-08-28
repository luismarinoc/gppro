# Security Stage 1: Container Boundary Hardening

Stage 1 removes the confirmed container credential-exposure paths and applies
safe defaults to proxy trust and log-directory permissions. It does not change
authentication, SAML behavior, branding, or the existing application security
controls.

## Quick path

1. Set `APP_SECRET`, database credentials, and administrator credentials through
   the deployment environment or an equivalent secret store.
2. If a reverse proxy is used, set `TRUSTED_PROXIES` to that proxy's exact IP,
   CIDR, or hostname. Do not use `*`.
3. Build and start the container, then inspect its logs for the test markers
   described below.

## Changes

| Area | Stage 1 decision |
|------|------------------|
| Entrypoint tracing | `.docker/entrypoint.sh` no longer enables Bash xtrace. This covers database, administrator, and application secrets handled by the script. |
| Trusted proxies | Compose defaults to `127.0.0.1,::1`, which is suitable when the published service is reached directly. Deployments behind a proxy must provide `TRUSTED_PROXIES` explicitly with only the proxy addresses or network. |
| Log permissions | Development and production images create `var/logs` as `www-data:www-data` with mode `0750`, rather than world-writable mode `0777`. |
| Runtime ownership | The entrypoint still recursively changes ownership of `/opt/gppro/var` because that path is a writable volume and may contain existing data, plugins, cache, and logs owned by a previous container UID/GID. This broad operation is retained to avoid breaking upgrades or custom `USER_ID`/`GROUP_ID` deployments. |

## Required configuration contract

`TRUSTED_PROXIES` is a comma-separated Symfony trusted-proxy value. Configure it
to the address or network of the component that directly connects to the app.
For example:

```dotenv
TRUSTED_PROXIES=10.20.0.15
```

Use a narrowly scoped CIDR only when the complete proxy network is controlled by
the deployment. The application trusts forwarded host, protocol, port, prefix,
and client-IP headers from these proxies; an overly broad value can therefore
affect URL generation, client-IP detection, and rate limiting.

## Secret-log validation

Use recognizable **fake** values only. Never paste production credentials into a
validation command or issue tracker.

```bash
APP_SECRET='stage1-app-secret-marker' \
DATABASE_PASS='stage1-db-password-marker' \
ADMINPASS='stage1-admin-password-marker' \
docker compose up --build

docker compose logs --no-color app | tee /tmp/gppro-stage1.log
! grep -F 'stage1-app-secret-marker' /tmp/gppro-stage1.log
! grep -F 'stage1-db-password-marker' /tmp/gppro-stage1.log
! grep -F 'stage1-admin-password-marker' /tmp/gppro-stage1.log
```

The negative `grep` checks must succeed. Also verify the image contains no
world-writable `var/logs` directory and that the runtime UID/GID can write the
application's required volume paths. Review retained container and centralized
logs separately; removing xtrace does not remove credentials already captured by
older images.

## Residual risks and out of scope

- Credentials exposed by historical logs may still require investigation and
  rotation.
- The startup script remains privileged so it can create the configured runtime
  user and repair volume ownership. Dropping startup privileges requires a
  separate compatibility design.
- SAML validation settings, authentication changes, CI ownership controls, and
  branding remain future work from the broader audit.

## Focused validation

```bash
bash -n .docker/entrypoint.sh
docker compose config
```

When Docker is unavailable, validate the shell syntax and inspect the rendered
Compose configuration in an environment with Docker Compose installed.
