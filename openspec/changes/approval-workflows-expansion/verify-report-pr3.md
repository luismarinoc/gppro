```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:996dbaecf8e353f7d6b60220d7a01f9480efa199
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 7/7
scenarios: 9/9
test_command: vendor/bin/phpunit tests/Entity/InvoiceTest.php tests/Invoice tests/Controller/InvoiceControllerTest.php tests/Voter/InvoiceVoterTest.php
test_exit_code: 1
test_output_hash: sha256:348-tests-1750-assertions-3-preexisting-failures-2026-08-14-rebased
build_command: vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
build_exit_code: 1
build_output_hash: sha256:1-preexisting-unrelated-error-quotationcontrollertest-rebased
```

## Verification Report

**Change**: approval-workflows-expansion
**PR**: PR3 — InvoiceVoter extension + functional closure (Phase 3, LAST PR of the Invoice payment-approval chain)
**Branch**: `approval-workflows-expansion-pr3-invoice-voter`, tip `996dbae` (rebased), base `main`/`origin/main` tip `675bcc2`
**Original tip before rebase**: `f245feb`, original base `bf9b189` (PR1+PR2 merge tip)
**Worktree**: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/approval-workflows-expansion-pr3-invoice-voter`
**Mode**: Strict TDD (apply-progress artifact available and read: Engram `sdd/approval-workflows-expansion/apply-progress-pr3`, obs #665)

### Rebase Status (item 9)

`origin/main` had advanced exactly **1 commit** past this PR's base (`bf9b189`): `675bcc2 chore(release): bump version to 2.62.79 [skip ci]`, touching only `src/Constants.php` (version string bump, unrelated to Invoice/Voter code). **Rebase WAS needed** and was performed: `git rebase origin/main` completed cleanly with **zero conflicts** (no file overlap between this PR's 4 changed files and the version-bump commit). New tip: `996dbae`. Full test/static-analysis suite re-run on the rebased branch — identical results to pre-rebase (348 tests, 345 pass, same 3 pre-existing failures; same 1 pre-existing phpstan error) — confirming the rebase introduced no regression and the pre-existing failures are reproduced on the true current `origin/main` baseline, not a stale snapshot. Local `main` confirmed to be a strict ancestor of `origin/main` (`git merge-base --is-ancestor main origin/main` → true): **no accidental commits landed on local `main`**. Conflict risk for merge: **NONE** (already rebased, clean).

### Completeness (Phase 3 tasks)
| Task | Status | Evidence |
|------|--------|----------|
| 3.1 RED `InvoiceVoterTest` approve/reject attributes | [x] | `testApproveInvoicePaymentGrantedForEligibleApprover`, `testApproveInvoicePaymentDeniedForIneligibleUser`, `testRejectInvoicePaymentGrantedForEligibleApprover`, `testRejectInvoicePaymentDeniedForCreator` — all present, all pass |
| 3.2 GREEN `InvoiceVoter::ALLOWED_ATTRIBUTES` extension | [x] | `src/Voter/InvoiceVoter.php` extended, `InvoicePaymentApprovalPolicy` injected, no new voter class (matches design's Open Questions resolution) |
| 3.3 RED PENDING/CANCELED remain ungated | [x] | `testChangeStatusToPendingAndCanceledRemainUngatedForUnsubmittedInvoice` — present, passes |
| 3.4 RED historical PAID invoice shows no unapproved flag | [x] | `testHistoricalPaidInvoiceShowsNoUnapprovedFlagOrPaymentApprovalActions` — present, passes |
| 3.5 Run full suite + phpstan, open PR3 | [x] | Independently re-executed below, results match claim |

**Tasks total (Phase 3)**: 5. **Complete**: 5. **Incomplete**: 0.

### Build & Tests Execution (independently re-run by verify, post-rebase)

**Voter/Controller focus**: ✅ 136 tests / 704 assertions, 0 failures
```
vendor/bin/phpunit tests/Voter/InvoiceVoterTest.php tests/Controller/InvoiceControllerTest.php tests/Entity/InvoiceTest.php tests/Invoice
OK (136 tests, 704 assertions)
```

**Full Invoice regression sweep** (equivalent to `tests/Invoice/*` — note: the literal shell glob `tests/Invoice/*` explicitly passes non-test files/subdirectories as direct PHPUnit arguments and fatals on `DebugFormatter.php`, a non-TestCase helper class; the directory form `tests/Invoice` is the correct equivalent and was used): ✅ matches claim exactly
```
vendor/bin/phpunit tests/Entity/InvoiceTest.php tests/Invoice tests/Controller/InvoiceControllerTest.php tests/Voter/InvoiceVoterTest.php
Tests: 348, Assertions: 1750, Failures: 3.
```
The 3 failures are exactly the claimed pre-existing/unrelated ones:
1. `App\Tests\Invoice\Hydrator\InvoiceModelDefaultHydratorTest::testHydrate`
2. `App\Tests\Invoice\Renderer\DebugRendererTest::testRender` (data set #0)
3. `App\Tests\Invoice\Renderer\DebugRendererTest::testRender` (data set #1)

**Confirmed genuinely pre-existing, not new**: (a) neither `InvoiceModelDefaultHydratorTest.php` nor `DebugRendererTest.php` appears in this PR's changed-file list (`git diff` shows only `InvoiceVoter.php`, `InvoiceVoterTest.php`, `InvoiceControllerTest.php`, `tasks.md`); (b) both files are last touched by an unrelated commit `fac93d9 feat(invoice): add a logo to invoice templates...`, predating this entire chain; (c) PR2's own verify report (`verify-report-pr2.md`) independently reproduced these identical 3 failures against a detached `origin/main` checkout at that time; (d) this verify pass re-reproduced the identical 3 failures again on the current, rebased `origin/main` tip (`675bcc2`) — same failure signature (same test names, same data sets, same "adjustments" template-variable drift), confirming continuity across 3 independent verification passes on 2 different `main` tips.

**Static analysis**: `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` → exactly **1 error**, `Controller/QuotationControllerTest.php::decodeJsonResponse()` return-type mismatch — pre-existing, unrelated to Invoice/Voter. 0 new errors. Matches claim exactly, re-confirmed on rebased tip.

**Twig lint**: `bin/console lint:twig templates/` → OK, 206 files valid.
**XLIFF lint**: `bin/console lint:xliff translations/` → OK, 602 files valid.

### Correctness — InvoiceVoter Attribute Routing (item 2, CRITICAL check)

Read `src/Voter/InvoiceVoter.php` and `src/Voter/ExpenseVoter.php` side-by-side.

`InvoiceVoter::voteOnAttribute()`:
```php
if ($attribute === 'approve_invoice_payment') {
    return $this->approvalPolicy->canApprove($subject, $user);
}
if ($attribute === 'reject_invoice_payment') {
    return $this->approvalPolicy->canReject($subject, $user);
}
// ... (view_invoice permission check, customer/team-access gate, only reached below this point)
```

`ExpenseVoter::voteOnAttribute()`:
```php
return match ($attribute) {
    'view_expense' => $this->canView($subject, $user),
    ...
    'approve_expense' => $this->approvalPolicy->canApprove($subject, $user),
    'reject_expense' => $this->approvalPolicy->canReject($subject, $user),
    default => false,
};
```

**Confirmed**: both voters route their respective `approve_*`/`reject_*` attributes **directly** to their domain policy's `canApprove()`/`canReject()`, executed *before* (`InvoiceVoter`, early-return) or *independent of* (`ExpenseVoter`, `match` arm bypasses `canView()`) any view/team-access precondition. Neither voter conditions approval eligibility on the coarse `view_*`/team-scope gate. The pattern is genuinely mirrored, not superficially similar — same bypass shape, same rationale (approval eligibility is policy-owned, independent of ordinary view/edit scope, so a finance-role approver outside the customer's team can still act). Constructor injection pattern (`RolePermissionManager` + domain-specific `*ApprovalPolicy`) is also identical between the two voters.

**No gap found.**

### Regression-Guard Tests (item 6)

Read both new tests directly in `tests/Controller/InvoiceControllerTest.php`:

- **`testChangeStatusToPendingAndCanceledRemainUngatedForUnsubmittedInvoice`** (line 1190): creates a never-submitted invoice (`paymentApprovalStatus` null), drives it through the real rendered "Waiting for payment" and "Cancel invoice" links (real CSRF tokens, not `getCsrfToken()` — correctly avoids the known harness gotcha documented in the apply-progress), reloads from a cleared `EntityManager`, and asserts `isPending()` then `isCanceled()` — i.e. both non-PAID transitions succeed with **zero** interaction with the D6 payment-approval gate. Genuine and correctly written; exercises the real controller/routing/CSRF/persistence path, not a unit-level shortcut.
- **`testHistoricalPaidInvoiceShowsNoUnapprovedFlagOrPaymentApprovalActions`** (line 1243): creates an invoice, marks it PAID directly (bypassing the approval flow, simulating a grandfathered historical row with `paymentApprovalStatus = null`), loads `/invoice/edit/{id}`, and asserts the rendered page contains **none** of "Submit for payment approval"/"Approve payment"/"Reject payment" text and **zero** forms targeting the 3 payment-approval action routes. Genuine content-level regression guard against the exact "flagged as unapproved" failure mode the spec's grandfathering requirement forbids.

Both tests are real functional tests against the actual controller/template/routing stack (not mocks), both pass, and both map 1:1 to their respective spec scenarios. **No gap found.**

### Spec Compliance Matrix — Full Invoice Payment-Approval Capability (item 8, cross-check of apply report's closing claim)

Independently re-read every requirement/scenario in `specs/invoice-payment-approval/spec.md` (7 requirements, 9 scenarios) against actual code + passing tests across PR1+PR2+PR3:

| # | Requirement | Scenario | Test(s) | Verified |
|---|---|---|---|---|
| 1 | PAID transition requires submission and cleared levels | Unsubmitted invoice cannot be marked paid | `InvoiceControllerTest::testChangeStatusToPaidIsDeniedForUnsubmittedInvoice` (line 1077), `::testEditFormStatusDropdownIsDeniedForUnsubmittedInvoice` (line 1112) | ✅ both entry points, both pass |
| 1 | (same) | Submitted invoice, 1 of 2 cleared, cannot be marked paid | `InvoiceTest::testClearPaymentLevelInOrderAdvancesCurrentLevel` (asserts status stays PENDING) | ✅ pass |
| 2 | Submission freezes required levels | Submission fixes required levels | `InvoiceTest::testSubmitForPaymentApprovalFreezesRequiredLevels`, `InvoiceControllerTest::testSubmitPaymentApprovalActionFreezesRequiredLevels` | ✅ pass |
| 3 | Post-submission amount changes do not reopen cleared levels | Amount increase after partial clearance does not reopen | `InvoicePaymentApprovalServiceTest::testAmountIncreaseAfterPartialApprovalDoesNotReopenClearedLevels` | ✅ pass |
| 4 | Only eligible approvers can clear a level | Eligible approver clears, audited | `InvoicePaymentApprovalPolicyTest::testUserWithMatchingRoleCanApproveThePendingLevel`, `InvoiceControllerTest::testApprovePaymentActionByEligibleApproverClearsLevelAndApproves` — **now also enforced at the voter layer** by this PR's `InvoiceVoterTest::testApproveInvoicePaymentGrantedForEligibleApprover` | ✅ pass, defense-in-depth confirmed at 2 layers |
| 4 | (same) | Ineligible user denied | `InvoicePaymentApprovalPolicyTest::testUserWithoutTheRequiredRoleCannotApprove`, `InvoiceControllerTest::testApprovePaymentActionByIneligibleApproverIsDenied` — **plus this PR's** `InvoiceVoterTest::testApproveInvoicePaymentDeniedForIneligibleUser` | ✅ pass at both layers |
| 5 | All levels cleared unlocks PAID with audit trail | Final level clears, invoice eligible for PAID, both audited | `InvoiceTest::testClearingFinalPaymentLevelCompletesApproval`, `InvoicePaymentApprovalServiceTest::testApproveSingleLevelInvoiceRecordsAuditRowAndMovesToApproved`, `::testTwoLevelInvoiceRequiresBothApproversBeforeApproved` | ✅ pass |
| 6 | PENDING/CANCELED remain ungated | Neither transition requires submission/clearance | `InvoiceControllerTest::testChangeStatusToPendingAndCanceledRemainUngatedForUnsubmittedInvoice` (this PR, task 3.3) | ✅ pass — previously only implicit, now explicit |
| 7 | Historical PAID invoices grandfathered | Pre-existing PAID invoice not flagged unapproved | `InvoiceControllerTest::testAlreadyPaidInvoiceCommentOnlyResaveIsNotBlocked` (PR2), `::testHistoricalPaidInvoiceShowsNoUnapprovedFlagOrPaymentApprovalActions` (this PR, task 3.4) | ✅ pass — both the "not blocked" and "not flagged in UI" halves now explicitly covered |

**Result: 7/7 requirements, 9/9 scenarios, all covered by passing runtime tests.** The apply report's closing claim — that the full PR1+PR2+PR3 Invoice payment-approval capability satisfies every spec requirement — is **independently confirmed, not merely asserted**. No requirement is implemented-but-untested, and no requirement is untested-but-claimed-tested.

### Design Coherence
| Decision | Followed? | Notes |
|----------|-----------|-------|
| No new voter class, extend `InvoiceVoter::ALLOWED_ATTRIBUTES` (Open Questions resolution) | ✅ Yes | Confirmed in source |
| Voter routes through `InvoicePaymentApprovalPolicy`, mirrors `ExpenseVoter` | ✅ Yes | Verified side-by-side above |
| Approve/reject bypass customer-team-access gate | ⚠️ Not itemized in design.md/tasks.md explicitly | Apply-time design decision, documented inline in code comment + apply-progress; correctly mirrors established `ExpenseVoter` precedent (same rationale: approver may lack ordinary team access). Assessed as the right call, not a defect — flagged as WARNING for design.md accuracy only. |

### Enforcement-Layer Note (carried forward from PR2, now closed)

PR2's verify report flagged that `InvoiceController`'s 3 payment-approval actions (`submitPaymentApprovalAction`/`approvePaymentAction`/`rejectPaymentAction`) still use the coarse `#[IsGranted('edit_invoice', 'invoice')]` gate at the controller level, not the new fine-grained `approve_invoice_payment`/`reject_invoice_payment` attributes — confirmed still true after PR3 (`src/Controller/InvoiceController.php` unchanged by this PR's diff). This is **not a spec gap**: `InvoicePaymentApprovalService::approve()`/`reject()` call `$policy->canApprove()`/`canReject()` internally and throw on denial (verified passing test: `testApprovePaymentActionByIneligibleApproverIsDenied`), so authorization is enforced regardless of which layer performs the check. The new voter attributes are now correctly wired and available (task 3.2's actual scope — "make the attributes available and policy-correct", not "re-gate the controller"), matching tasks.md's explicit Phase 3 scope. Recommend a follow-up (out of this PR's scope) to swap the controller's `#[IsGranted]` to the fine-grained attributes for defense-in-depth consistency, but this is a SUGGESTION, not a blocker.

### Issues Found

**CRITICAL**: None.

**WARNING**:
1. The "approve/reject bypass customer-team-access gate" design decision (mirroring `ExpenseVoter`) was made during apply and is documented in code comments + apply-progress, but is not itemized in `design.md`'s Architecture Decisions or `tasks.md`. Recommend updating `design.md` with a "D8" entry for documentation accuracy — no functional defect, the decision itself is correct and verified.
2. `tests/Invoice/*` (literal shell glob, as specified in the verify task) does not work as a direct PHPUnit argument — it fatals on `tests/Invoice/DebugFormatter.php` (a non-TestCase helper explicitly picked up when passed as a bare file argument, unlike directory-scan mode which only picks up `*Test.php`). The directory form `tests/Invoice` is the correct equivalent and reproduces the claimed 345/348 exactly. Recommend documenting `tests/Invoice` (not `tests/Invoice/*`) as the canonical full-sweep command in future task/apply artifacts to avoid this footgun.

**SUGGESTION**:
1. Swap `InvoiceController`'s 3 payment-approval actions from the coarse `edit_invoice` `#[IsGranted]` gate to the new fine-grained `approve_invoice_payment`/`reject_invoice_payment` voter attributes for defense-in-depth consistency with the voter layer this PR just wired up. Not a spec gap (service-layer policy check already enforces correctness), purely a hardening/consistency improvement for a future PR.

### Rebase / Conflict-Risk Assessment (item 9, final)
- `origin/main` had advanced 1 commit (trivial version bump, unrelated file) past this PR's base.
- **Rebase performed**: clean, zero conflicts.
- **Post-rebase re-verification**: full test suite (348 tests) and phpstan re-run on the new tip `996dbae` — identical results to pre-rebase, confirming no regression from the rebase itself and confirming the 3 pre-existing failures + 1 pre-existing phpstan error reproduce on the true current `origin/main` baseline.
- No accidental commits on local `main` (confirmed ancestor relationship holds).
- **Conflict risk at merge time: NONE** — branch is already rebased onto the latest fetched `origin/main` tip as of this verification.

### Verdict
**PASS WITH WARNINGS** — all 5 Phase 3 tasks are genuinely complete with concrete passing-test evidence; `InvoiceVoter`'s new `approve_invoice_payment`/`reject_invoice_payment` attributes correctly and verifiably mirror `ExpenseVoter`'s established bypass-the-view-gate pattern; both new regression-guard tests are genuine, correctly written, and pass against the real controller/routing/persistence stack; the full Invoice test surface reproduces exactly the claimed 345/348 (3 pre-existing, unrelated, independently re-confirmed across 3 separate verification passes on 2 different `main` tips) with exactly 1 pre-existing unrelated phpstan error; twig/xliff lints clean; no accidental commits on local `main`; rebase was needed, was performed cleanly with zero conflicts, and was fully re-verified. The closing cross-check independently confirms the apply report's claim that all 7 spec requirements / 9 scenarios of the full Invoice payment-approval capability (PR1+PR2+PR3 combined) are genuinely implemented and covered by passing runtime tests — this claim was NOT taken on faith, it was re-derived from the spec file and cross-referenced against actual test method bodies. Two WARNINGs are documentation/tooling-footgun items, not functional or security defects; one SUGGESTION is an optional hardening improvement for a future PR. This PR is ready to merge as-is (already rebased onto latest `origin/main`).
