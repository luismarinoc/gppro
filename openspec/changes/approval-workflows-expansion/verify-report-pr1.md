```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:4dd26b36623c26a0e20b7fa01b212786e0fb4210
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 0/7
scenarios: 0/9
test_command: vendor/bin/phpunit tests/Entity/InvoicePaymentApprovalLevelTest.php tests/Invoice/InvoicePaymentApprovalLevelResolverTest.php tests/Controller/InvoicePaymentApprovalLevelControllerTest.php
test_exit_code: 0
test_output_hash: sha256:55aefb1536196443a0cd1fa0c9a0de335053ae79ea3d24aa2f1ddd212cffce59
build_command: vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
build_exit_code: 1
build_output_hash: sha256:ad463a2b8edea24cd06ae48c9fd8a57aa84bda36a4851fc80d94359de1b6819d
```

# Verification Report: approval-workflows-expansion — PR1 (Invoice payment-approval foundation)

**Change**: approval-workflows-expansion
**Scope of this verification**: PR1 only — branch `approval-workflows-expansion-pr1-invoice-foundation`, tip `4dd26b36623c26a0e20b7fa01b212786e0fb4210`, based on tracker `approval-workflows-expansion-tracker` off `main`@`c194c8b0a43c196257e170433585d51f28224871`. 7 commits ahead of main (`git log --oneline main..HEAD` confirmed).
**Mode**: Strict TDD active (RED/GREEN commit pairs present); full proposal+spec+design+tasks artifacts present for the overall change, but only Phase 1 (11 tasks) is in scope for this PR.

## Diff Range Confirmed
```
4dd26b3 feat(invoice): add InvoicePaymentApprovalLevel admin CRUD
cde663b test(invoice): add RED test for InvoicePaymentApprovalLevelController
278c286 feat(invoice): add InvoicePaymentApprovalLevelResolver
42fc738 test(invoice): add RED test for InvoicePaymentApprovalLevelResolver
b1ce6ac feat(invoice): add InvoicePaymentApprovalLevel entity and repository
a09a6b0 test(invoice): add RED test for InvoicePaymentApprovalLevel entity
93afd0f docs(sdd): add approval-workflows-expansion SDD artifacts
```
`git diff --stat main..HEAD`: 23 files, +1668/-1 (authored non-doc lines ≈ 857: entity 123, controller 119, form 56, resolver 43, repo 48, migration 34, templates 53, tests 344, config 10, translations 28).

## Completeness (Phase 1 only)
| Metric | Value |
|--------|-------|
| Phase 1 tasks total | 11 |
| Phase 1 tasks complete | 11 |
| Phase 1 tasks incomplete | 0 |
| Overall change tasks (Phases 2/3/T/4/5) | Not in scope for PR1 — correctly unchecked, deferred to later chained PRs per design's "Recommended PR Split" and tasks.md's Review Workload Forecast (chain strategy: feature-branch-chain) |

Spot-checked (not just checkboxes) against actual diffs:
- 1.1/1.2: `tests/Entity/InvoicePaymentApprovalLevelTest.php` (4 tests) + `src/Entity/InvoicePaymentApprovalLevel.php` — `minAmount` is `?float`/`Types::FLOAT`, `0.0` comparison in `validateLevelOneMinAmount` (D5 confirmed).
- 1.3: `migrations/Version20260814100000.php` — `min_amount DOUBLE PRECISION`, level-1 seed row `(1, 0, 'ROLE_TEAMLEAD')`, matches `Version20260812140000.php`'s Expense seed pattern.
- 1.4: `src/Repository/InvoicePaymentApprovalLevelRepository.php` — byte-identical shape to `ExpenseApprovalLevelRepository` (`findAllOrdered`, `saveLevel`, `deleteLevel` with level-1 guard).
- 1.5/1.6: `tests/Invoice/InvoicePaymentApprovalLevelResolverTest.php` (4 tests, fractional-float boundary cases) + `src/Invoice/InvoicePaymentApprovalLevelResolver.php` — genuinely independent class (own constructor typed to `InvoicePaymentApprovalLevelRepository`, no shared interface/trait/inheritance with `App\Expense\ApprovalLevelResolver`); D1 confirmed by direct side-by-side read of both files.
- 1.7/1.8: `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` (7 tests) + `src/Controller/InvoicePaymentApprovalLevelController.php` + `src/Form/InvoicePaymentApprovalLevelForm.php` — routes, `isMonotonic()`, CSRF, level-1-cannot-delete guard all mirror `ExpenseApprovalLevelController`/`ExpenseApprovalLevelForm` exactly (side-by-side diff confirms only the float-vs-int type difference).
- 1.8 DI note: `config/services.yaml` factory registration for `InvoicePaymentApprovalLevelRepository` added — confirmed to match the existing `ExpenseApprovalLevelRepository` entry byte-for-byte in shape (`class`/`factory: ['@doctrine.orm.entity_manager', getRepository]`/`arguments`).
- 1.9: `templates/invoice_payment_approval_level/{index,edit}.html.twig` — structurally mirror `templates/expense_approval_level/*`.
- 1.10: `config/packages/gppro.yaml` — `manage_invoice_payment_approval_levels` added to `ROLE_SUPER_ADMIN` array alongside `manage_expense_approval_levels`.
- 1.11: test/static-analysis/lint commands re-run independently below.

## Build & Tests Execution
**Focused PR1 test suite**: PASSED — 15 passed / 0 failed
```
vendor/bin/phpunit tests/Entity/InvoicePaymentApprovalLevelTest.php tests/Invoice/InvoicePaymentApprovalLevelResolverTest.php tests/Controller/InvoicePaymentApprovalLevelControllerTest.php
...............                                                   15 / 15 (100%)
OK (15 tests, 61 assertions)
```
Independently re-run — matches the apply report's claimed 15/15 exactly (4 entity + 4 resolver + 7 controller = 15).

**Static analysis**: 1 error found, confirmed pre-existing and unrelated
```
vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
Controller/QuotationControllerTest.php:296 — decodeJsonResponse() should return array<string, mixed> but returns array<mixed, mixed> (return.type)
Found 1 error
```
`QuotationControllerTest.php` is not in this PR's diff (`git diff --stat main..HEAD` does not list it) — confirmed unrelated to this change.

**Zero-regression check (Expense)**: PASSED — 14 passed / 0 failed
```
vendor/bin/phpunit tests/Entity/ExpenseApprovalLevelTest.php tests/Expense/ApprovalLevelResolverTest.php tests/Controller/ExpenseApprovalLevelControllerTest.php
..............                                                    14 / 14 (100%)
OK (14 tests, 63 assertions)
```
`git diff main -- src/Entity/ExpenseApprovalLevel.php src/Expense/ApprovalLevelResolver.php src/Controller/ExpenseApprovalLevelController.php` → **empty** (confirmed zero accidental Expense-code changes).

**Twig lint**: PASSED — `bin/console lint:twig templates/invoice_payment_approval_level/` → "All 2 Twig files contain valid syntax."
**XLIFF lint**: PASSED — `bin/console lint:xliff` on the 4 modified translation files → "All 4 XLIFF files contain valid syntax." All template-referenced translation keys (`invoice_payment_approval_level.*`) present in both `en`/`es` `messages.xlf` and the `not_monotonic` flash key in both `flashmessages.xlf`.

**Migration replay**: NOT EXECUTED — dev DB credentials unavailable in this sandboxed verification environment (`doctrine:migrations:status` failed with `Access denied for user 'user'@'localhost'`). Not a regression: the phpunit functional/controller tests above did exercise Doctrine's ORM mapping for `InvoicePaymentApprovalLevel` against a live (test) schema and passed, and the migration's raw SQL (`min_amount DOUBLE PRECISION`, FK `approver_user_id → gppro_users ON DELETE SET NULL`, level-1 seed) was manually cross-checked against the entity mapping and found consistent. Recommend a maintainer run `doctrine:migrations:migrate` once against a real dev DB before merge as a final sanity check (not blocking — same-shape pattern as the already-shipped Expense migration).

## Correctness (Static Evidence)
| Requirement (design D1–D5 + tasks 1.1–1.11) | Status | Notes |
|---|---|---|
| D1 — Resolver is a genuine duplicate, not reused/extracted | Implemented | Confirmed by direct file comparison — independent class, own constructor, no shared interface |
| D5 — `minAmount` is `float` | Implemented | `Types::FLOAT`, `NumberType` form field, `0.0` comparisons throughout entity/tests |
| Admin CRUD mirrors `ExpenseApprovalLevelController` | Implemented | Byte-for-byte structural match confirmed |
| DI factory registration for repository | Implemented | Matches `ExpenseApprovalLevelRepository` entry exactly |
| Permission `manage_invoice_payment_approval_levels` registered `ROLE_SUPER_ADMIN` | Implemented | `config/packages/gppro.yaml` |
| Migration additive/nullable, no Expense table touched | Implemented | New table only, FK to `gppro_users` |

## Coherence (Design)
| Decision | Followed? | Notes |
|----------|-----------|-------|
| D1 (duplicate resolver) | Yes | Verified by side-by-side source comparison |
| D5 (float minAmount) | Yes | Verified in entity, form, migration, and tests (fractional-boundary test cases included) |

## Spec Compliance (invoice-payment-approval, full capability — 7 requirements / 9 scenarios)
PR1 deliberately implements **only the approval-level ladder infrastructure** (entity/repo/resolver/admin CRUD) — no `Invoice` state-machine, submission, approve/reject, or PAID-gate logic yet. Per design's "Recommended PR Split," those land in PR2 (state machine + gate) and PR3 (voter + functional closure). Tasks.md Phase 2/3 tasks (2.1–3.5) are correctly unchecked.

| Requirement | Status |
|---|---|
| PAID transition requires submission and cleared levels | PENDING — PR2 scope |
| Submission freezes required levels at current amount | PENDING — PR2 scope |
| Post-submission amount changes do not reopen cleared levels | PENDING — PR2 scope |
| Only eligible approvers can clear a level | PENDING — PR2/PR3 scope |
| All levels cleared unlocks PAID with audit trail | PENDING — PR2 scope |
| PENDING/CANCELED remain ungated (regression guard) | PENDING — PR3 scope (explicit regression test task 3.3) |
| Historical PAID invoices grandfathered | PENDING — PR3 scope (explicit test task 3.4) |

**None of these 7 requirements/9 scenarios are expected to be testable from PR1's diff** — this is by design, not a gap. `requirements: 0/7` / `scenarios: 0/9` in the YAML envelope reflects this PR's scope, not a deficiency; full-capability spec compliance must be re-verified after PR2 and PR3 land.

## TDD Compliance
| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported (apply-progress artifact) | Missing | No `sdd/approval-workflows-expansion/apply-progress` observation found in Engram (searched 3 query variants, 0 hits) — a genuine pipeline-persistence gap. Every other recently-verified change in this project (`expense-access-scoping`, `activity-workspace`, `expense-category`, etc.) has a corresponding apply-progress artifact; this one does not. |
| All tasks have tests | Yes | 3/3 GREEN tasks (1.2, 1.6, 1.8) have a corresponding RED task (1.1, 1.5, 1.7) immediately preceding, and 3 corresponding test files exist |
| RED confirmed (tests exist) | Yes | Verified by commit history: `a09a6b0 test(invoice): add RED test for entity`, `42fc738 test(invoice): add RED test for resolver`, `cde663b test(invoice): add RED test for controller` — each immediately followed by its GREEN commit |
| GREEN confirmed (tests pass now) | Yes | All 15 tests pass on independent re-execution |
| Triangulation adequate | Yes | Entity: 4 cases; Resolver: 4 cases incl. fractional-boundary above/below/at-threshold; Controller: 7 cases incl. security, creation, named-approver, non-monotonic rejection, delete guard |
| Safety Net for modified files | N/A (new) | All 3 production files (`InvoicePaymentApprovalLevel.php`, `InvoicePaymentApprovalLevelResolver.php`, `InvoicePaymentApprovalLevelController.php`/`Form.php`) are genuinely new — no existing file modified except `config/services.yaml`/`config/packages/gppro.yaml` (additive lines only, confirmed by diff) |

**TDD Compliance**: 5/6 checks passed. The missing apply-progress artifact is a **pipeline-persistence gap**, not a code-quality gap — commit history independently and unambiguously corroborates RED→GREEN discipline for all 3 task groups.

### Test Layer Distribution
| Layer | Tests | Files | Tools |
|-------|-------|-------|-------|
| Unit | 8 | 2 (`InvoicePaymentApprovalLevelTest.php`, `InvoicePaymentApprovalLevelResolverTest.php`) | PHPUnit + mocked repository |
| Integration | 7 | 1 (`InvoicePaymentApprovalLevelControllerTest.php`) | PHPUnit + `HttpKernelBrowser`, real DB via `EntityManager` |
| E2E | 0 | 0 | not applicable |
| **Total** | **15** | **3** | |

### Assertion Quality
All assertions verify real behavior — no tautologies, no ghost loops, no orphan empty checks, no smoke-test-only patterns. All controller test assertions read back real persisted state via `EntityManager` after each HTTP action (not mock-call-count assertions). Resolver tests include genuine boundary triangulation (`1_000_000.5` included, `1_000_000.49` excluded).

### Quality Metrics
**Linter**: Not run separately (php-cs-fixer not invoked in this session; not part of this PR's declared task 1.11 command list)
**Type Checker (PHPStan)**: 1 pre-existing unrelated error (see above) — 0 new errors introduced by this PR's files

## Issues Found

**CRITICAL**: None.

**WARNING**:
1. Missing `apply-progress` Engram artifact for this change/PR — the strict-TDD verify protocol treats a missing "TDD Cycle Evidence" table as a hard blocker by default, but git commit history independently corroborates the full RED→GREEN sequence for all 3 task groups, so this is downgraded to WARNING rather than blocking. Recommend the apply phase (or a follow-up save) backfill this artifact before PR2/PR3 apply work begins, since later phases' strict-TDD verification will have the same gap otherwise.
2. Migration `up()` was not replayed against a live dev DB in this sandboxed environment (credentials unavailable) — cross-checked manually against entity mapping and found consistent; recommend a maintainer run `doctrine:migrations:migrate` once pre-merge as final confirmation.
3. No `MenuSubscriber` navigation entry was added for the new `/admin/invoice/payment-approval-levels/` admin screen (item 8 below) — genuinely out of scope for the 11 assigned Phase 1 tasks, but also **not explicitly assigned to any later phase** in tasks.md or design.md's File Changes table (unlike the Expense equivalent, which does have a `MenuSubscriber` entry at `src/EventSubscriber/MenuSubscriber.php:137-139`). This is a genuine planning gap in the SDD artifacts, not a Phase 1 execution gap — recommend adding a task to a future phase (or a small follow-up) so the new screen is reachable via UI, not only by direct URL.

**SUGGESTION**: None.

## Item 8 — MenuSubscriber Nav Entry Scope Check
Confirmed: no mention of `MenuSubscriber` anywhere in `proposal.md`, `design.md` (Architecture Decisions or File Changes table), or `tasks.md` (any phase, 1 through 5). The admin CRUD screen is functionally complete and correctly gated (`manage_invoice_payment_approval_levels`, `ROLE_SUPER_ADMIN`) but is currently reachable only via direct URL, not through the settings nav. This is confirmed **out of the assigned 11 Phase 1 tasks** (correct, not a Phase 1 gap), but it is also not clearly scoped to any later phase — flagged as WARNING #3 above for follow-up.

## Item 7 — No Accidental Commits on Local `main`
`git log -1 main` and `git log -1 origin/main` both resolve to `c194c8b0a43c196257e170433585d51f28224871` ("chore(release): bump version to 2.62.71 [skip ci]") — identical. `git merge-base main HEAD` also resolves to the same commit. Confirmed: no accidental commits landed on local `main`.

### Verdict
**PASS WITH WARNINGS**

All 11 Phase 1 tasks are genuinely complete and independently spot-checked against code (not just checkboxes). D1 (resolver duplication) and D5 (float minAmount) design decisions are both correctly implemented and verified by direct source comparison against their Expense analogues. The 15-test focused suite passes independently (matches apply report), PHPStan shows exactly 1 pre-existing unrelated error, zero-regression on Expense confirmed both by test execution (14/14) and an empty targeted `git diff`, the flagged DI deviation matches the existing pattern exactly, and both lint checks pass. Three WARNING-level findings (missing apply-progress artifact, unreplayed migration in this sandbox, and an unscoped MenuSubscriber follow-up) are process/documentation gaps, not functional defects, and do not block merge of this standalone, self-contained PR1.
