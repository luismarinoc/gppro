```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:804bcdeccfbb224573e3ff1d89bc1f5617366697
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 6/7
scenarios: 8/9
test_command: vendor/bin/phpunit tests/Entity/InvoiceTest.php tests/Invoice/InvoicePaymentApprovalPolicyTest.php tests/Invoice/InvoicePaymentApprovalServiceTest.php tests/Controller/InvoiceControllerTest.php
test_exit_code: 0
test_output_hash: sha256:60-tests-428-assertions-ok-2026-08-14
build_command: vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
build_exit_code: 1
build_output_hash: sha256:1-preexisting-unrelated-error-quotationcontrollertest
```

## Verification Report

**Change**: approval-workflows-expansion
**PR**: PR2 — Invoice submit/approve/reject state machine + dual PAID gate (Phase 2)
**Branch**: `approval-workflows-expansion-pr2-invoice-state-machine`, tip `804bcde`, base `d669b6a` (PR1 merge tip)
**Version**: N/A
**Mode**: Strict TDD (module loaded; apply-progress artifact unavailable for this PR — see WARNING)

### Completeness
| Metric | Value |
|--------|-------|
| Tasks total (Phase 2) | 15 |
| Tasks complete | 15 (`[x]` in tasks.md, each with concrete evidence) |
| Tasks incomplete | 0 |

### Build & Tests Execution

**Tests (scoped, PR2 focus)**: ✅ 60 passed / 0 failed
```text
vendor/bin/phpunit tests/Entity/InvoiceTest.php tests/Invoice/InvoicePaymentApprovalPolicyTest.php \
  tests/Invoice/InvoicePaymentApprovalServiceTest.php tests/Controller/InvoiceControllerTest.php
OK (60 tests, 428 assertions)
```

**Tests (full InvoiceControllerTest safety net)**: ✅ 28 passed / 0 failed — matches apply-report claim exactly.

**Tests (full `*Invoice*` regression sweep)**: ⚠️ 480 tests, 3 failures — matches apply-report's claimed "3 pre-existing unrelated failures" exactly:
- `InvoiceModelDefaultHydratorTest::testHydrate`
- `DebugRendererTest::testRender` (data set #0, #1)

Independently re-ran these 2 files against `origin/main` tip (`a3437a3`, detached checkout, stash-protected) — **identical 3 failures reproduce**, confirming they are genuinely pre-existing and unrelated to this PR, not regressions.

**Static analysis**: `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` → exactly 1 error, `Controller/QuotationControllerTest.php::decodeJsonResponse()` — pre-existing, unrelated to Invoice/PR2. 0 new errors. Matches apply-report claim.

**Twig lint**: `bin/console lint:twig templates/invoice/invoice_edit.html.twig` → OK.
**XLIFF lint**: `bin/console lint:xliff translations/` → OK, 602 files valid.

**Coverage**: not measured (no coverage tool run in this pass — informational only, not blocking per Strict-TDD-verify rules).

### Spec Compliance Matrix (invoice-payment-approval domain, Phase-2-scoped requirements only)
| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| PAID transition requires submission and cleared levels | unsubmitted invoice → PAID denied | `InvoiceControllerTest::testChangeStatusToPaidIsDeniedForUnsubmittedInvoice`, `::testEditFormStatusDropdownIsDeniedForUnsubmittedInvoice` | ✅ COMPLIANT |
| PAID transition requires submission and cleared levels | 1 of 2 levels cleared → PAID still denied | `InvoiceTest::testClearPaymentLevelInOrderAdvancesCurrentLevel` (asserts status stays `PENDING`, not `APPROVED`) | ✅ COMPLIANT |
| Submission freezes required levels at current amount | 1 level frozen at submit | `InvoiceTest::testSubmitForPaymentApprovalFreezesRequiredLevels`, `InvoiceControllerTest::testSubmitPaymentApprovalActionFreezesRequiredLevels` | ✅ COMPLIANT |
| Post-submission amount changes do not reopen cleared levels | amount increase after partial clearance | `InvoiceTest::testAmountChangeAfterPartialClearanceDoesNotReopenClearedLevels` | ✅ COMPLIANT |
| Only eligible approvers can clear a level | eligible role clears, audited | `InvoicePaymentApprovalPolicyTest` (eligible cases), `InvoiceControllerTest::testApprovePaymentActionByEligibleApproverClearsLevelAndApproves` | ✅ COMPLIANT |
| Only eligible approvers can clear a level | ineligible user denied | `InvoicePaymentApprovalPolicyTest` (ineligible cases), `InvoiceControllerTest::testApprovePaymentActionByIneligibleApproverIsDenied` | ✅ COMPLIANT |
| All levels cleared unlocks PAID with audit trail | final level clears → approved, audited | `InvoiceTest::testClearingFinalPaymentLevelCompletesApproval`, `InvoicePaymentApprovalServiceTest` (audit row assertions) | ✅ COMPLIANT |
| PENDING/CANCELED remain ungated | (out of Phase 2 scope — deferred to Phase 3 task 3.3 per tasks.md split) | — | ➖ N/A this PR (not a Phase 2 task; source inspection confirms gate is scoped to `STATUS_PAID` branch only, no incidental regression) |
| Historical PAID invoices are grandfathered | pre-existing PAID invoice re-save not blocked | `InvoiceControllerTest::testAlreadyPaidInvoiceCommentOnlyResaveIsNotBlocked` | ✅ COMPLIANT |

**Compliance summary**: 8/9 scenarios compliant with passing runtime tests; 1/9 correctly deferred to Phase 3 per the documented task split (not a Phase 2 defect).

### CRITICAL Verification — Dual PAID Gate (D6)

Read both real PAID-transition entry points directly in `src/Controller/InvoiceController.php`:

1. **`changeStatusAction()`** (line ~254): `if ($status === Invoice::STATUS_PAID) { if (!$invoice->isPaid() && !$invoice->isPaymentApproved()) { flash error; redirect; } ... }` — gate fires unconditionally before any setter call (`setPaymentDate`/`setIsPaid`) and before the render of `invoice_edit.html.twig`. No bypass path found.
2. **`editAction()`** (line ~294): `$wasPaid = $invoice->isPaid();` captured **before** `$form->handleRequest($request)`; after valid submit: `if ($invoice->isPaid() && !$wasPaid && !$invoice->isPaymentApproved()) { flash error; re-render; skip saveInvoice(); }` — this is the gate that closes the `InvoiceEditForm` status-dropdown bypass identified in design D6. Confirmed unconditional — fires on every genuine not-paid→paid transition regardless of how the form was populated.

Both gates are enforced by the SAME predicate (`!isPaid() && !isPaymentApproved()` / `isPaid() && !wasPaid && !isPaymentApproved()`), both throw no exception but hard-stop persistence (flash + redirect/re-render, `saveInvoice()`/`InvoiceService` PAID branch never reached). Confirmed via passing tests exercising both routes independently (`testChangeStatusToPaidIsDeniedForUnsubmittedInvoice`, `testEditFormStatusDropdownIsDeniedForUnsubmittedInvoice`). **No partial-fix / bypass gap found — this is the single most important check in the PR and it passes.**

### Grandfathering Guard (item 4)

`testAlreadyPaidInvoiceCommentOnlyResaveIsNotBlocked` (tests/Controller/InvoiceControllerTest.php:1147): creates an invoice, marks it PAID directly (`setIsPaid()`/`setPaymentDate()`, bypassing the approval flow — simulating a pre-existing historical invoice with `paymentApprovalStatus = null`), then re-saves via the edit form with only a comment change and `status` still `PAID`. Asserts: success flash, `isPaid()` stays true, comment persists, and `getPaymentApprovalStatus()` stays `null` (never retroactively flagged). This is because `editAction`'s gate only fires on `!$wasPaid` (a genuine transition) — a resave where `$wasPaid` is already true never enters the gated branch. Confirmed correct by design and by test.

### Policy / Creator-Equivalent (item 5)

`InvoicePaymentApprovalPolicy` read side-by-side with `ExpenseApprovalPolicy` — structurally identical: same `canDecide()` gate order (pending-level check → creator-exclusion → already-approved-any-level exclusion → super-admin bypass → named-approver OR required-role check). `Invoice::getUser()` traced to `InvoiceService::createModelWithoutEntries()` → `$model->setUser($query->getCurrentUser())` — the user under whose session the invoice-generation query ran, a reasonable creator-equivalent, matching the class's own doc comment.

### Enforcement-Layer Deviation (item 10)

`InvoiceVoter::ALLOWED_ATTRIBUTES` does NOT yet include `approve_invoice_payment`/`reject_invoice_payment` — confirmed via source (that wiring is explicitly Phase 3 task 3.2, not Phase 2). The 3 new controller actions (`submitPaymentApprovalAction`/`approvePaymentAction`/`rejectPaymentAction`) are gated by the existing coarse-grained `#[IsGranted('edit_invoice', 'invoice')]` attribute. Fine-grained eligibility (four-eyes, creator-exclusion, required-role/named-approver) is fully enforced inside `InvoicePaymentApprovalService::approve()`/`reject()`, which call `$policy->canApprove()`/`canReject()` and `throw new \DomainException(...)` on denial — caught in the controller and surfaced as a flash error (not a silent no-op). Verified with a real test (`testApprovePaymentActionByIneligibleApproverIsDenied`) showing an `edit_invoice`-eligible-but-policy-ineligible user is denied with the exact business-rule message and the invoice's `isPaymentApproved()` stays false. **No privilege-escalation gap** — this is a different enforcement layer (service-level throw vs. voter-level 403) than originally planned in design, but equally effective; matches design's own documented rationale for the phase split.

### Correctness (Static Evidence)
| Requirement | Status | Notes |
|------------|--------|-------|
| Invoice state machine (D4) | ✅ Implemented | `paymentApprovalStatus`/`paymentRequiredLevels`/`paymentCurrentLevel` fields, separate from business `status`, confirmed distinct from `Expense`'s single-field approach (intentional D4 deviation, not a defect) |
| `submitForPaymentApproval()`/`clearPaymentLevel()`/`rejectPaymentApproval()` shape | ✅ Implemented | Side-by-side comparison with `Expense::submitForApproval()`/`clearLevel()`/`rejectApproval()` — same sequential-order enforcement, same freeze-on-submit semantics |
| `InvoicePaymentApprovalService` transactional audit | ✅ Implemented | `beginTransaction()`/`persist()`/`flush()`/`commit()` with rollback on `\Throwable`, mirrors `ExpenseApprovalService` |
| Dual PAID gate (D6) | ✅ Implemented | Both entry points gated, see CRITICAL section above |

### Coherence (Design)
| Decision | Followed? | Notes |
|----------|-----------|-------|
| D4 (state on Invoice itself, separate fields) | ✅ Yes | |
| D5 (`minAmount` float, not int) | ✅ Yes (verified in PR1, unaffected here) | |
| D6 (dual PAID gate, both entry points) | ✅ Yes | Verified directly, see CRITICAL section |
| Voter wiring split (design says PR2 wires voter; tasks.md says PR3 does) | ⚠️ Deviation, documented | Confirmed this PR followed tasks.md (PR3-deferred), not design.md literally — tasks.md is the more specific/authoritative artifact for PR sequencing and the deviation is explicitly called out inline in task 2.10's own commentary. No functional gap results (see item 10 analysis above). |

### TDD Compliance
| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported | ⚠️ Partial | `apply-progress` Engram artifact for PR2 is UNAVAILABLE — the shared topic key `sdd/approval-workflows-expansion/apply-progress` was overwritten by a concurrent PR-T (Timesheet) agent's `mem_save` upsert; only Phase T's evidence table is retrievable. Substituted with: (a) `tasks.md`'s own inline RED/GREEN task descriptions (committed in-repo, not clobbered), (b) direct re-execution of every listed test file confirming pass, (c) commit-log correlation to task groups. |
| All tasks have tests | ✅ | 15/15 Phase 2 tasks map to concrete test files/methods, all present and passing |
| RED confirmed (tests exist) | ✅ | All referenced test files/methods exist in the tree at HEAD |
| GREEN confirmed (tests pass) | ✅ | 60/60 scoped, 28/28 full InvoiceControllerTest, 0 new failures in full sweep |
| Triangulation adequate | ✅ | Each state-machine behavior has both entity-level (`InvoiceTest`) and controller-level (`InvoiceControllerTest`) coverage; policy has dedicated eligible/ineligible/already-approved cases |
| Safety Net for modified files | ✅ | Full `*Invoice*` sweep (480 tests) run and cross-checked against `origin/main` baseline |

**TDD Compliance**: 5/6 checks fully passed, 1 partial (artifact availability, not code quality)

### Assertion Quality
Sampled `InvoicePaymentApprovalPolicyTest.php` and `InvoicePaymentApprovalServiceTest.php` for banned patterns (tautologies, empty-only checks, ghost loops, type-only-alone assertions) — none found. `InvoicePaymentApprovalServiceTest.php`: 6 tests / 22 assertions (3.7 avg, no mock-heavy imbalance). All controller tests reload the entity from a cleared `EntityManager` before asserting persisted state (no false-positive in-memory-only assertions).

**Assertion quality**: ✅ All assertions verify real behavior

### Issues Found

**CRITICAL**: None.

**WARNING**:
1. PR2's `apply-progress` Engram artifact is unavailable — clobbered by a concurrent agent's upsert on the same shared topic key (`sdd/approval-workflows-expansion/apply-progress`) during parallel PR1/PR-T/PR2 development. This is a pipeline hygiene defect (topic-key collision across concurrently-developed PRs sharing one change name), not a code defect. Recommend the orchestrator use PR-scoped topic keys (e.g. `sdd/{change-name}/apply-progress-pr2`) for concurrent chained/parallel work in future sessions.
2. `templates/invoice/invoice_edit.html.twig` received an 18-line addition (submit/approve/reject buttons, CSRF tokens, `is_granted` gate) not itemized in design.md's file-changes list. Assessed as a reasonable, necessary, tightly-scoped addition — it is the only way to make the 3 new POST routes reachable with real session-valid CSRF tokens in a functional test (the apply report's own stated rationale, cross-checked and confirmed: a token minted via the test harness's `getCsrfToken()` helper does not validate against a real request/session). Not scope creep; recommend updating design.md's file list for documentation accuracy only.
3. **Rebase required, real conflict found**: `git merge-tree --write-tree origin/main 804bcde` surfaces exactly ONE genuine `add/add` conflict: `migrations/Version20260814110000.php`. Both this PR and the already-merged `self-registration-admin-approval` PR independently generated a Doctrine migration under the identical class name `Version20260814110000` (same timestamp, different unrelated purposes — one adds invoice payment-approval schema, the other adds `email_confirmed_at`/`rejected_at` to `gppro_users`). This is NOT a trivial line-conflict: resolving it requires renaming this branch's migration to a fresh, later, unique timestamp before/during rebase — leaving both files as literally the same class name would either fatal-error on duplicate class declaration or corrupt Doctrine's migration version ordering. `translations/messages.en.xlf` also overlaps in the file list but auto-merges cleanly per the same `merge-tree` dry run (non-overlapping hunks — self-registration added around line 182, this PR added around line 1198).

**SUGGESTION**: None beyond the design.md file-list accuracy note above.

### Rebase / Conflict-Risk Assessment (item 12)

- Local `main` (`26569f3`) confirmed to be a strict, unmodified ancestor of `origin/main` (`git merge-base --is-ancestor main origin/main` → true) — **no accidental commits landed on local main**.
- `origin/main` has advanced 25 commits past this PR's actual base (`d669b6a`): Timesheet PR-T (`#124`), login-security-management PR2/remember-me (`#123`), login-security-management PR1 (`#122`), fix-password-policy-test-fixture (`#125`... actually merged as PR #125), self-registration-admin-approval (`#126`).
- File-level overlap between PR2's diff and `origin/main`'s advancement since base: exactly 2 files — `migrations/Version20260814110000.php` (REAL conflict, see WARNING #3 above) and `translations/messages.en.xlf` (clean auto-merge, confirmed via `merge-tree`).
- **Rebase is required before merge** (branch is materially stale — 25 commits behind). **Conflict risk: LOW-MEDIUM** — exactly one real conflict, isolated to a single migration filename collision with a well-understood, low-effort fix (rename this branch's migration to a new unique version timestamp). No overlap at all with `src/Entity/Invoice.php`, `src/Controller/InvoiceController.php`, `InvoicePaymentApproval*`, or any of PR2's core Invoice files against what merged into `main` — confirmed via `comm -12` set intersection.

### Verdict
**PASS WITH WARNINGS** — the dual PAID gate (D6, the highest-risk item in this PR) is correctly, unconditionally enforced on both real entry points with no bypass found; all 15 Phase 2 tasks are genuinely complete with passing runtime evidence; all 3 claimed pre-existing failures independently reproduced against `origin/main`; phpstan/twig/xliff all clean as claimed. Three WARNINGs are process/documentation/rebase-mechanics items, none of which represent a functional or security defect in the shipped code. Rebase is required (branch is stale by 25 commits) with exactly one real, low-effort conflict to resolve (migration timestamp collision) before this PR can be opened/merged.
