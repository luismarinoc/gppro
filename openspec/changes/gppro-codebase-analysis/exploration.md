## Exploration: gppro codebase condition, architecture, and improvement priorities

### Current State

**Verified facts.** The repository is clean on `main` at `5dab523` (`chore(release): bump version to 2.62.23`) and is a Symfony 6.4 / PHP >=8.2 monolith with Doctrine ORM, Twig, MariaDB/MySQL, Webpack Encore, PHPUnit, PHPStan, and Docker. The application is organized into about 43 `src/` domains (including API, controllers, entities, repositories, security, invoicing, reporting, plugins, and FX rates), with 1,090 PHP files under `src/`, 758 PHP test files, 88 migrations, and 187 Twig templates. These counts are repository measurements, not quality metrics.

The main runtime composition is conventional Symfony: `src/Kernel.php` loads bundles and discovers runtime plugins in `var/plugins/`; `config/services.yaml` enables autowiring/autoconfiguration and explicitly wires repositories, security services, HTTP clients, and compiler passes; `config/routes.yaml` combines attribute routes, API routes, security routes, and plugin routes. Doctrine maps `src/Entity/` and uses custom UTC date types (`config/packages/doctrine.yaml`). The security model has separate stateful web and stateless API firewalls, LDAP/SAML/database providers, CSRF-enabled form login, TOTP, login throttling, login links, and role/voter-based authorization (`config/packages/security.yaml`).

The codebase shows a mature upstream-derived platform extended with gppro-specific behavior. The FX-rate capability is a recent example: `src/FxRate/MindicadorClient.php` isolates the external API, `FxRateSynchronizer` persists USD and UF independently, `FxRateRepository` provides idempotent upsert and date queries, and `FxRatesSyncCommand` provides explicit exit semantics and Santiago timezone handling. The UI change in `src/Controller/FxRateController.php` and `templates/fx_rates/index.html.twig` adds indicator tabs and raises the filtered page size to 1000; `tests/Controller/FxRateControllerTest.php` verifies the 60-row no-pagination case and permission/success flows.

**Verified quality signals.** CI runs PHP 8.2–8.5 integration tests against MySQL 8.4, PHPStan and PHPUnit, PHP-CS-Fixer, Symfony/container/YAML/Twig/schema/XLIFF linting, Composer and Symfony security audits, frontend dependency installation/audit, and Docker image builds (`.github/workflows/*.yaml`). PHPUnit is configured for integration tests with a database and DAMA transaction extension (`phpunit.xml.dist`). PHPStan is configured at level 9 with strict-rule options, but `phpstan.neon` is 3,268 lines and contains 648 `identifier`/`message` suppression entries by repository count; this is a measurable type-signal debt even if some suppressions are intentional compatibility boundaries.

**Execution limitation.** Native PHP, Composer, pnpm, and mysql are unavailable in the analysis environment, so tests, PHPStan, linting, and the application console were not executed. Docker and Docker Compose are available; `docker compose config --quiet` succeeds, but emits warnings because the local environment file variables are unset. Existing unrelated containers are running, but they are not treated as evidence that this checkout builds or passes.

**Hypotheses requiring follow-up validation.** The project appears operationally deployable because Compose, health checks, persistent volumes, migrations, and CI image builds are present, but readiness of this exact checkout remains unproven until a clean Docker build and test run are performed. The repository retains upstream naming and compatibility residue (`kimai`, `kimai2`, and Kimai references in migrations, workflow labels, and documentation); this is likely intentional fork/upgrade compatibility, but it increases onboarding and migration-risk unless explicitly documented. The Dockerfile builds PHP 8.3 while the support contract and CI cover 8.2–8.5; this is not inherently incorrect, but the release/runtime support matrix should be made explicit.

### Affected Areas

- `src/Kernel.php`, `config/services.yaml`, `config/routes.yaml` — application composition, plugin discovery, service wiring, and route loading.
- `src/Entity/`, `src/Repository/`, `migrations/`, `config/packages/doctrine.yaml` — persistence model, schema evolution, custom date handling, and migration compatibility.
- `src/Security/`, `config/packages/security.yaml`, `src/Ldap/`, `src/Saml/`, `src/API/Authentication/` — high-impact authentication and authorization surface.
- `src/Controller/`, `src/Command/`, `src/Invoice/`, `src/Reporting/`, `src/Timesheet/` — large application service/use-case surface and likely highest regression blast radius.
- `src/FxRate/`, `src/Entity/FxRate.php`, `src/Repository/FxRateRepository.php`, `src/Command/FxRatesSyncCommand.php`, `templates/fx_rates/`, `tests/FxRate/`, `tests/Controller/FxRateControllerTest.php` — current gppro-specific external-data and UI flow; a useful reference for future vertical slices.
- `phpstan.neon`, `tests/phpstan.neon`, `.php-cs-fixer.dist.php`, `composer.json` — static quality policy and technical-debt measurement.
- `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/KernelTestTrait.php` — integration-test bootstrap and database coupling.
- `.github/workflows/testing.yaml`, `linting.yaml`, `frontend.yaml`, `docker.yaml`, `Dockerfile`, `docker-compose.yml`, `.env.dist` — CI/CD, container, and operational reproducibility.
- `README.md`, `AGENTS.md`, `CONTRIBUTING.md`, `UPGRADING*.md` — contributor/operator documentation and project identity.

### Approaches

1. **Evidence-first health baseline and hardening roadmap** — establish a reproducible Docker-based validation path, inventory failing tests/lint/static-analysis findings, classify PHPStan suppressions, document runtime/version/environment contracts, then address the highest-risk findings in small vertical slices.
   - Pros: low architectural risk; turns hypotheses into evidence; protects the existing mature platform; aligns with existing CI and contributor commands; produces measurable progress.
   - Cons: does not immediately simplify the monolith; requires prioritization discipline and may expose a large backlog.
   - Effort: Medium

2. **Broad modularization or framework/platform rewrite** — reorganize the monolith around new bounded contexts or replace major infrastructure before establishing a baseline.
   - Pros: potentially cleaner long-term boundaries and reduced legacy coupling.
   - Cons: high migration and regression risk across security, Doctrine, plugins, invoices, API, and templates; weak evidence for choosing new boundaries; likely exceeds the 400-line review budget by orders of magnitude.
   - Effort: High

### Recommendation

Choose the evidence-first approach. The architecture is already strongly modularized by Symfony domain folders and extension points, and the strongest near-term return is not a rewrite: it is proving the checkout reproducibly, reducing static-analysis suppression debt, documenting the fork/runtime contract, and adding targeted regression coverage around security, migrations, external integrations, and recently changed gppro-specific flows. A follow-up SDD proposal/spec is warranted for a bounded **codebase health and operational-readiness baseline** change, with explicit acceptance criteria and a phased delivery plan. Keep each work unit below the 400 changed-line review budget; use chained slices if the measured backlog exceeds it.

Suggested priority order:

1. Build and test the exact checkout through Docker, including database bootstrap, migrations, unit/integration tests, PHPStan, frontend checks, and container health.
2. Record a compatibility matrix for PHP, database, Docker image, timezone, environment variables, and migration table naming; resolve documentation/config drift where verified.
3. Classify and reduce PHPStan suppressions, starting with security, persistence, command, and API boundaries; do not remove compatibility suppressions without tests.
4. Add or strengthen regression tests for authorization boundaries, migration upgrades, external HTTP failure modes, and critical invoice/timesheet workflows.
5. Improve operational observability for scheduled FX synchronization and other background/console workflows (structured failure reporting, retry/runbook expectations, and alertable exit codes).

### Risks

- The application cannot be declared passing from this environment because runtime validation was not executed; Docker availability reduces but does not remove this uncertainty.
- Authentication, authorization, invoices, timesheets, migrations, and plugin loading are cross-cutting/high-blast-radius areas; changes there should be proposal-driven and test-first.
- The large PHPStan suppression configuration can hide regressions unless suppressions are ownership-tagged, counted, and burned down deliberately.
- Database/version drift is possible: `README.md` states MariaDB >=10.6 or MySQL >=8.4, while `phpunit.xml.dist` contains a MariaDB 10.5.8 test-server default and `docker-compose.yml` uses MariaDB 10.11; CI uses MySQL 8.4. These may be environment-specific defaults, but they should be reconciled or documented.
- `docker compose config` succeeds with blank substituted secrets when `.env` is absent. This is useful for syntax validation but unsafe as evidence of a runnable deployment; deployment validation must supply non-secret test values through the approved environment mechanism.
- The current FX-rate single-indicator view uses a hard-coded page size of 1000 (`src/Controller/FxRateController.php:41-44`); it satisfies the tested requirement for current data volume but is a scalability threshold, not an unbounded “all dates” guarantee.
- Upstream compatibility and fork identity are mixed in names and migration/table history. Renaming them without a migration/upgrade plan could break existing installations and plugins.

### Ready for Proposal

Yes. The exploration is sufficient for a bounded follow-up proposal focused on reproducible validation, operational-readiness evidence, and prioritized debt reduction. The proposal should first lock the Docker validation contract and review-budget slices; it should not commit to a broad rewrite or claim any unexecuted test result as verified.
