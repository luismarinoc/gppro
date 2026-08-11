# Tasks: Codebase Health and Operational-Readiness Baseline

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~300–380 authored lines |
| 400-line budget risk | Medium |
| Chained PRs recommended | Yes — slice by autonomous work unit |
| Suggested split | PR 1: assessment contract; PR 2: evidence outputs and report |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No — maintainer selected auto-chain with stacked-to-main slices
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| 1 | Add read-only assessment contract and evidence harness | PR 1 | `bash tools/codebase-health-baseline/test-assessment.sh` | `bash tools/codebase-health-baseline/assess.sh /root/dev/gppro` | Remove `tools/codebase-health-baseline/` and its tests |
| 2 | Capture ledger, signals, prioritization, and report | PR 2 | `python3 -m json.tool docs/runbooks/codebase-health-baseline/ledger.json` | `bash tools/codebase-health-baseline/assess.sh /root/dev/gppro` | Remove only `docs/runbooks/codebase-health-baseline/` outputs |

## Phase 1: Foundation and Scope Guard

- [x] 1.1 Create `tools/codebase-health-baseline/assess.sh` to resolve/record absolute repository root and commit, use disposable Docker volumes/non-secret inputs, and never mutate application, schema, runtime, CI, index, or remote state.
- [x] 1.2 **RED:** Create `tools/codebase-health-baseline/test-assessment.sh` proving relative paths outside the target repository and mismatched absolute roots fail; then make `assess.sh` return a non-zero result with no baseline claim.

## Phase 2: Evidence Collection and Validation

- [x] 2.1 Add contract steps in `assess.sh` for Compose/build/health, database bootstrap, migration forward/replay, PHPUnit, Composer/lint, PHP-CS-Fixer, PHPStan source/tests, and pnpm/frontend checks.
- [x] 2.2 Record each check in `docs/runbooks/codebase-health-baseline/ledger.json` with the required fields: procedure, inputs, result, availability, reproducibility, evidence class, references, and confidence impact.
- [x] 2.3 Mark missing Docker/dependencies/configuration as `unavailable` with reason and attempted procedure; ensure unavailable or non-repeatable checks cannot produce a passing verdict.

## Phase 3: Signals, Classification, and Prioritization

- [x] 3.1 Capture PHPStan suppressions, CI/runtime matrix drift, compatibility, quality, runtime, and operational signals in `ledger.json`, separating measurements from interpretation and mapping Security, migrations, API, Invoice, Timesheet, and FxRate impacts.
- [x] 3.2 Classify every finding exactly as `verified fact`, `hypothesis`, or `follow-up validation`, citing repository evidence and validation status; record conflicting sources as follow-up validation.
- [x] 3.3 Build `docs/runbooks/codebase-health-baseline/backlog.md` using agreed impact × evidence-strength criteria; allow priority only for available, reproducible, reliability-evidenced findings and keep hypotheses informational.

## Phase 4: Reporting and Verification

- [x] 4.1 Create `docs/runbooks/codebase-health-baseline/report.md` with checkout identity, tool/image versions, results, unavailable checks, confidence limits, ranked bounded slices, and an explicit no-remediation/no-passing-claim statement.
- [x] 4.2 Review ledger/report against all specification scenarios, repeat applicable checks or document why repetition is impossible, validate JSON, run the harness tests, and confirm only documentation/measurement outputs changed.
