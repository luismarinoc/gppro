# Design: Codebase Health and Operational-Readiness Baseline

## Technical Approach

Produce a versioned, reviewable assessment for the exact Git checkout. Evidence collection is read-only: inspect repository configuration and reliability-sensitive areas, then execute the existing Composer, PHPUnit, PHPStan, frontend, migration, and Docker procedures where prerequisites exist. Every observation is stored with source, command/procedure, inputs, result, reproducibility, and exactly one evidence class. No application or configuration remediation is performed.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Evidence record | Structured finding/check records plus narrative interpretation | Unstructured report; issue-only output | Keeps facts, hypotheses, unavailable checks, and conclusions auditable and prevents interpretation from masquerading as measurement. |
| Runtime authority | Exact checkout, pinned lockfiles, and repository-defined Docker/CI procedures | Native host tools; “latest” local services | Docker is the reproducibility boundary; native checks are supplementary and cannot establish a passing baseline. |
| Unavailable checks | Record `unavailable`, reason, attempted procedure, and confidence impact | Skip silently; infer success from static inspection | Environment limits are themselves operational evidence; silence would overstate readiness. |
| Prioritization | Rank only reproducibly supported findings using agreed impact × evidence strength; hypotheses remain informational | Rank by apparent severity or code smell count | Prevents unsupported remediation priorities and bounds future slices below 400 changed lines. |

## Data Flow

```text
Exact checkout + repository sources
        ├── static/document inspection ──┐
        ├── Docker/native checks ─────────┼──> evidence ledger ──> classified findings
        └── CI/runtime comparison ───────┘              └──> bounded reliability backlog
```

The validation contract covers: Compose/build and container health; database bootstrap; Doctrine migrations forward/replay; PHPUnit unit/full suites; Composer linting, PHP-CS-Fixer, PHPStan source/tests; and `pnpm` install/audit plus applicable frontend checks. Use non-secret test values only. `tests/bootstrap.php` may reset the test database, so database isolation and disposable volumes are mandatory. Capture checkout identity, tool/image versions, command, environment class (never secret values), exit status, logs/reference, and repeatability.

## File Changes

| File | Action | Description |
|---|---|---|
| `openspec/changes/gppro-codebase-analysis/design.md` | Create | This technical design. |
| Baseline report/measurement outputs (future task-defined paths) | Create | Evidence ledger, validation results, signals, and ranked follow-up backlog; documentation only. |
| Application source, schema, runtime, CI, and dependency policy | No change | Reviewed as evidence; remediation is explicitly deferred. |

## Interfaces / Contracts

Each check/finding MUST contain:

```text
id, category, source_refs, procedure, inputs, result,
reproducibility, availability, evidence_class, reliability_impact,
interpretation, priority (nullable), follow_up (nullable)
```

`evidence_class` is exactly `verified fact`, `hypothesis`, or `follow-up validation`. A priority is valid only when availability is confirmed, reproducibility is demonstrated, reliability impact is evidenced, and the rationale names the impact/evidence criteria. Unavailable checks never yield a passing verdict.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Evidence review | Required categories, fields, classifications, and scope guard | Checklist/schema review of the generated ledger and report. |
| Integration | Docker database, migrations, tests, static analysis, frontend, health | Run each contract step independently; record unavailable rather than substituting unsupported host results. |
| Reproducibility | Same checkout/procedure yields comparable result | Repeat applicable checks or record why repetition is impossible. No application E2E is added. |

## Threat Matrix

| Boundary | Applicability | Design response | Planned RED tests |
|---|---|---|---|
| Documentation-like paths | N/A — no executable-file classification is introduced. | Do not execute reviewed documentation. | None. |
| Git repository selection | Applicable — exact-checkout evidence depends on repository/cwd authority. | Resolve and record absolute repository root and commit; reject ambiguous or non-repository paths. | Assessment harness fails for a relative path resolving outside the target repository and for a mismatched absolute root. |
| Commit state | N/A — assessment does not stage, commit, or mutate the index. | Report the observed worktree state only. | None. |
| Push state | N/A — no push or branch destination is used. | Never push or infer remote readiness. | None. |
| PR commands | N/A — no PR automation or command composition exists. | No PR command generation. | None. |

## Migration / Rollout

No migration required. Run against disposable Docker volumes and approved non-secret values. A failed or unavailable prerequisite produces a partial baseline, never a passing claim.

## Open Questions

- [ ] Product/operations must confirm reliability-impact tiers and owners before ranking.
- [ ] Confirm the minimum Docker check set required to promote a hypothesis to a priority.
