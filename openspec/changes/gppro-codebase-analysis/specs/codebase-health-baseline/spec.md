# Codebase Health Baseline Specification

## Purpose

Define a diagnostic baseline for code health and operational readiness that reduces technical debt risk to product-user reliability without changing application behavior.

## Requirements

### Requirement: Evidence-classified baseline inventory

The baseline MUST inventory findings from the repository's workflows, Docker/runtime configuration, static-analysis policy, tests, compatibility documentation, and reliability-sensitive areas. Every finding MUST be classified exactly as `verified fact`, `hypothesis`, or `follow-up validation`, with the repository evidence and validation status recorded.

#### Scenario: Finding is supported by repository evidence

- GIVEN a measurable condition in `.github/workflows/`, `Dockerfile`, `docker-compose.yml`, `phpstan.neon`, tests, or project documentation
- WHEN it is recorded in the baseline
- THEN it is labeled exactly one evidence class and cites the inspected evidence

#### Scenario: Finding cannot yet be reproduced

- GIVEN an observed concern without a repeatable check or sufficient environment access
- WHEN it is recorded
- THEN it is labeled `hypothesis` or `follow-up validation`, and is not represented as a verified fact

### Requirement: Reproducible exact-checkout validation

The baseline MUST define a Docker-based validation contract for the exact checkout, covering database bootstrap, migrations, tests, static analysis, frontend checks, and container health. Each check MUST record its command or procedure, inputs, result, and reproducibility status; unavailable checks MUST be explicitly marked unavailable.

#### Scenario: Validation succeeds reproducibly

- GIVEN Docker/Compose access and approved non-secret test configuration
- WHEN the contract is executed against the exact checkout
- THEN results can be repeated and include outcomes for each applicable validation category

#### Scenario: Environment prevents execution

- GIVEN a required dependency or environment capability is unavailable
- WHEN the contract is attempted
- THEN the check is recorded as unavailable with its reason, and the checkout is not declared passing

### Requirement: Measured readiness and compatibility signals

The baseline MUST measure or explicitly mark unavailable quality, compatibility, runtime, and operational-readiness signals, including PHPStan suppressions and CI/runtime matrix drift. It MUST distinguish measurements from interpretation and identify affected reliability-sensitive areas such as security, migrations, API, invoicing, timesheets, and FX rates.

#### Scenario: Signal is measurable

- GIVEN a configured source of truth and a reproducible measurement procedure
- WHEN the signal is assessed
- THEN the baseline records the measurement, source, scope, and reliability implication

#### Scenario: Signal lacks an authoritative source

- GIVEN conflicting or incomplete CI, runtime, or compatibility information
- WHEN the signal is assessed
- THEN the discrepancy is recorded as evidence requiring follow-up rather than silently resolved

### Requirement: Evidence-gated reliability prioritization

The baseline MUST produce a bounded, risk-ranked debt backlog linked to product-user reliability. Ranking MUST use agreed impact and evidence-strength criteria. A `hypothesis` MUST NOT become a priority until a reproducible check promotes it with supporting evidence; follow-up slices MUST remain reviewable and below the 400-line review budget.

#### Scenario: Confirmed finding is prioritized

- GIVEN a finding with reproducible evidence and a documented reliability impact
- WHEN ranking is performed
- THEN it receives a priority, rationale, evidence class, and bounded follow-up slice

#### Scenario: Hypothesis lacks proof

- GIVEN a finding classified as `hypothesis` without reproducible evidence
- WHEN ranking is performed
- THEN it remains informational or follow-up work and cannot receive a remediation priority

### Requirement: Baseline-only scope protection

The baseline MUST NOT remove suppressions, change production, schema, runtime, or CI behavior, add observability, broaden regression coverage, modularize, replace frameworks, rename upstream/fork identity, or claim remediation is complete. Outputs MUST describe diagnosis and proposed bounded follow-up only.

#### Scenario: Assessment produces no remediation change

- GIVEN the baseline is generated
- WHEN repository changes are reviewed
- THEN only baseline documentation or measurement outputs are changed; application behavior remains unchanged

#### Scenario: Proposed remediation is discovered

- GIVEN a finding suggests code, schema, runtime, or CI remediation
- WHEN it is included in the baseline
- THEN it is recorded as a bounded future slice and not implemented in this change
