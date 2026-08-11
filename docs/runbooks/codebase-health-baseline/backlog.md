# Baseline Follow-up Backlog

This backlog ranks only evidence that is available and reproducible from the exact checkout. Impact is product-user reliability; evidence strength is reproducibility and source agreement. Unavailable checks are follow-up work, not passing evidence.

| Priority | Finding | Evidence class | Reliability impact | Bounded slice |
|---|---|---|---|---|
| P1 | CI/runtime matrix differs across README, Dockerfile, and CI | verified fact | Compatibility can vary across supported deployments | Compare supported PHP/database versions and document one owner-approved matrix; <=400 authored lines |
| P2 | PHPStan configuration contains explicit ignored errors | verified fact | Unreported defects may affect API, security, invoicing, and timesheets | Produce a counted suppression inventory and remove only with targeted regression tests; <=400 authored lines |
| Informational | Quality result is currently unavailable | hypothesis | Confidence is limited until dependencies are present | Run the ledger contract in disposable Docker/CI-equivalent tooling; no remediation in this baseline |

No hypothesis receives a remediation priority. Migration, database, PHPUnit, frontend, and health checks remain `follow-up validation` until reproducibly executed.
