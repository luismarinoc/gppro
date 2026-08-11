# Proposal: Codebase Health and Operational-Readiness Baseline

## Intent

Reduce technical debt that can undermine product-user reliability by creating an evidence-based baseline of code quality, runtime reproducibility, compatibility, and operational readiness. The first slice is strictly diagnostic and prioritization-focused: it must distinguish verified facts from hypotheses and follow-up validation, and no hypothesis may become a priority without reproducible evidence.

## Scope

### In Scope
- Establish a reproducible Docker-based validation contract for the exact checkout, including database bootstrap, migrations, tests, static analysis, frontend checks, and container health.
- Measure and document quality, compatibility, runtime, and operational-readiness signals, including PHPStan suppressions and CI/runtime matrix drift.
- Produce a risk-ranked debt backlog tied to product-user reliability, with evidence status and recommended bounded follow-up slices.

### Out of Scope
- Broad remediation, modularization, framework replacement, or upstream/fork renaming.
- Removing suppressions, changing production behavior, adding observability, or expanding regression coverage beyond baseline evidence collection.
- Declaring the checkout passing when validation cannot be executed reproducibly.

## Capabilities

### New Capabilities
- `codebase-health-baseline`: Evidence classification, reproducible validation, operational-readiness measurement, and prioritized technical-debt reporting.

### Modified Capabilities
- None.

## Approach

Use an evidence-first baseline aligned with existing Docker and CI workflows. Record each finding as exactly one of `verified fact`, `hypothesis`, or `follow-up validation`; hypotheses remain informational until a reproducible check confirms them. Map confirmed findings to user-facing reliability risks, rank them by impact and evidence strength, and split future remediation into reviewable units below the 400-line budget. Preserve current behavior during this phase.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `.github/workflows/`, `Dockerfile`, `docker-compose.yml`, `.env.dist` | Modified | Validation and runtime contract evidence |
| `phpstan.neon`, `tests/phpstan.neon`, `composer.json` | Modified | Static-analysis debt measurements and policy context |
| `phpunit.xml.dist`, `tests/bootstrap.php` | Modified | Test/database reproducibility evidence |
| `README.md`, `AGENTS.md`, `CONTRIBUTING.md`, `UPGRADING*.md` | Modified | Compatibility and operational documentation baseline |
| `src/Security/`, `migrations/`, `src/API/`, `src/Invoice/`, `src/Timesheet/`, `src/FxRate/` | Reviewed | Reliability-sensitive prioritization targets; no implementation changes |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Environment limits prevent validation | High | Use Docker with approved non-secret test values; label unavailable checks explicitly |
| Baseline becomes an unbounded backlog | Med | Define ranking criteria and bounded follow-up slices before remediation |
| Findings are mistaken for facts | Med | Require the three-way evidence classification and reproducible proof for priorities |

## Rollback Plan

Revert the proposal artifact and any baseline documentation or measurement outputs. No application, schema, runtime, or CI behavior changes are permitted in this slice.

## Dependencies

- Docker/Compose access and approved non-secret validation configuration.
- Product/operations agreement on reliability-impact ranking criteria.

## Success Criteria

- [ ] Exact-checkout validation results are reproducible or explicitly recorded as unavailable, without overstating confidence.
- [ ] Every baseline finding is classified as `verified fact`, `hypothesis`, or `follow-up validation`.
- [ ] No hypothesis is prioritized without reproducible evidence.
- [ ] A ranked, user-reliability-linked backlog identifies bounded follow-up slices under the 400-line review budget.

## Follow-up Planning Questions

- Which product workflows and reliability signals define the ranking tiers?
- What minimum Docker validation is required before a finding can be promoted from hypothesis?
- Who owns acceptance of compatibility, security, migration, and operational-readiness findings?
