# Verification Report — PR4 (Approvals Dashboard)

**Change**: approval-workflows-expansion
**PR**: PR4 — Approvals Dashboard (FINAL PR of the entire change)
**Branch**: `approval-workflows-expansion-pr4-dashboard`, tip `4bebe5a`
**Worktree**: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/approval-workflows-expansion-pr4-dashboard`
**Mode**: Full artifacts (proposal + specs + design + tasks present); hybrid persistence (OpenSpec file + Engram)
**Verdict**: **PASS WITH WARNINGS**

## 1. Rebase / Conflict-Risk Assessment

- Fetched `origin/main`: advanced from merge-base `befd5a1` to `49f1689`, exactly **one** commit ahead: `chore(release): bump version to 2.62.84 [skip ci]`, touching only `src/Constants.php` (2 lines).
- PR4 branch's full diff vs `befd5a1` touches only: `openspec/changes/approval-workflows-expansion/tasks.md`, `src/Controller/ApprovalsDashboardController.php`, `src/Repository/InvoiceRepository.php`, `src/Repository/TimesheetRepository.php`, `templates/approvals_dashboard/index.html.twig`, `tests/Controller/ApprovalsDashboardControllerTest.php`, `tests/Repository/InvoiceRepositoryTest.php`, `tests/Repository/TimesheetRepositoryTest.php`, `translations/messages.en.xlf`.
- File-set intersection between PR4's changes and origin/main's advance: **empty** (`comm -12` confirms zero overlap).
- **Rebase needed: No.** **Conflict risk: None (zero file overlap, single trivial version-bump commit).** Rebase is optional/cosmetic only; safe to merge as-is.
- Local `main` confirmed untouched: at `befd5a1`, exactly 1 commit behind `origin/main`, no accidental commits landed on it.
- Confirmed via `git log`: Invoice chain PR1 (#merge in main history), PR2 (`Merge pull request #128`), PR3 (`Merge pull request #129`), and Timesheet PR-T (`Merge pull request #124`) are all present in `main`'s history at `befd5a1`, i.e. all merged before PR4 branched.

## 2. Task Completion (Phase 4, tasks.md)

All 12 Phase 4 tasks (4.1–4.12) are marked `[x]` and verified genuinely complete by source inspection + runtime test evidence:

| Task | Claim | Verified |
|---|---|---|
| 4.1/4.2 | `TimesheetRepository::findPendingApprovalForUser()` — real team-lead eligibility filter | PASS — query joins `project→teams→members`, filters `tm.user = :user AND tm.teamlead = true AND t.approvedAt IS NULL` |
| 4.3/4.4 | `InvoiceRepository::findPendingPaymentApprovalForUser()` — deliberately naive, creator-exclusion only | PASS — confirmed naive (`paymentApprovalStatus = PENDING`, excludes creator only, no role/approver filtering) |
| 4.5–4.9 | `ApprovalsDashboardControllerTest` RED scenarios (3-domain aggregation, single-domain scoping, empty state, permission leakage, cross-domain isolation, navigation-only, historical-PAID grandfathering) | PASS — all present and green |
| 4.10 | `ApprovalsDashboardController::index()` — per-item `is_granted()` re-check on all 3 domains before render | PASS — independently reproduced (see §3) |
| 4.11 | `templates/approvals_dashboard/index.html.twig` | PASS — read directly, no forms/POST, navigation-only |
| 4.12 | Test run + phpstan + lint | PASS — reproduced independently (see §4) |

## 3. CRITICAL Check — Permission-Leakage Re-Verification (independently reproduced)

Read `src/Controller/ApprovalsDashboardController.php` directly. All three domains are filtered via `array_filter` + `$this->isGranted(...)` per item, immediately before merge into the render payload:

```php
$expenses  = array_filter($expenseRepository->findPendingForUser($user),          fn (Expense $e)   => $this->isGranted('approve_expense', $e));
$timesheets = array_filter($timesheetRepository->findPendingApprovalForUser($user), fn (Timesheet $t) => $this->isGranted('approve_timesheet', $t));
$invoices  = array_filter($invoiceRepository->findPendingPaymentApprovalForUser($user), fn (Invoice $i) => $this->isGranted('approve_invoice_payment', $i));
```

Independent reproduction performed (not trusting the apply report's claim):
1. Backed up the controller, then patched the Invoice filter's callback to `fn (Invoice $invoice): bool => true /* TEMP BYPASS FOR VERIFY */`, bypassing the `is_granted('approve_invoice_payment', ...)` check.
2. Ran `vendor/bin/phpunit --filter testDashboardDoesNotLeakInvoiceRawRepositoryResultToIneligibleApprover tests/Controller/ApprovalsDashboardControllerTest.php`.
3. **Result: RED.** `Failed asserting that '<html>... [invoice number] ...</html>' does not contain "inv-dashboard-...".` — the invoice created by an admin (not the test caller) leaked onto the dashboard once the `is_granted()` check was bypassed, confirming the raw `InvoiceRepository` query alone (creator-exclusion only) is insufficient and the controller-level check is genuinely load-bearing, not decorative.
4. Restored the original file byte-for-byte (`git diff` on the file returned clean).
5. Re-ran the same test: **GREEN** — `OK (1 test, 4 assertions)`.

This independently confirms the apply report's claim rather than merely trusting it.

## 4. Reasoning Check — Naive Invoice Repo Query Is Safe Because of the Controller Re-Check

Read `src/Invoice/InvoicePaymentApprovalPolicy.php::canApprove()`. It performs real per-item eligibility checks: pending-level resolution, creator-exclusion, already-approved-by-this-user exclusion, super-admin bypass, then either named-approver match or role match (`$user->hasRole($level->getRequiredRole())`). `InvoiceVoter::ALLOWED_ATTRIBUTES` wires `approve_invoice_payment` to `InvoicePaymentApprovalPolicy::canApprove()`.

Because nothing renders on the dashboard without also passing `is_granted('approve_invoice_payment', $invoice)` → `InvoicePaymentApprovalPolicy::canApprove()`, the repository query's creator-exclusion-only shape is safe as a first-pass filter — confirmed by the §3 RED/GREEN reproduction. **Reasoning holds.**

`TimesheetRepository::findPendingApprovalForUser()` does real team-lead-eligibility filtering at the query level (not a raw/naive query) — confirmed by direct read of the DQL (`tm.teamlead = true` join condition), consistent with `tests/Repository/TimesheetRepositoryTest.php`'s scenarios.

## 5. Navigation-Only Confirmation

Read `templates/approvals_dashboard/index.html.twig` in full: 3 read-only `<table>` sections (Expense/Invoice/Timesheet), each row is a plain `<a href="{{ path(...) }}">` link — **zero `<form>` elements, zero POST targets, zero inline approve/reject controls**. `ApprovalsDashboardController` declares only one route, `methods: ['GET']`. Confirms decision 7 (navigation-only) and spec requirement "Dashboard rows navigate to the domain's own screen."

## 6. Route-Name Verification

| Domain | Route used in template | Confirmed real route | Location |
|---|---|---|---|
| Expense | `expense_view` | Yes | `src/Controller/ExpenseController.php:101` |
| Invoice | `admin_invoice_edit` | Yes | `src/Controller/InvoiceController.php:292` |
| Timesheet | `admin_timesheet` | Yes | `src/Controller/TimesheetTeamController.php:42` |

## 7. Test Suite Evidence (all independently executed, not trusted from the apply report)

| Command | Result |
|---|---|
| `phpunit tests/Controller/ApprovalsDashboardControllerTest.php` | **8/8 pass** |
| `phpunit tests/Controller/ApprovalsDashboardControllerTest.php tests/Repository/ExpenseRepositoryTest.php tests/Voter/InvoiceVoterTest.php tests/Voter/TimesheetVoterTest.php tests/Repository/TimesheetRepositoryTest.php tests/Repository/InvoiceRepositoryTest.php` | **129/129 pass** — exactly matches apply report's claimed "129/129" |
| Leakage RED reproduction (§3) | **RED confirmed**, then **GREEN confirmed** after restore |
| `phpunit tests/Entity/{Timesheet,Invoice,InvoicePaymentApprovalLevel}Test.php tests/Voter/{Timesheet,Invoice}VoterTest.php tests/Controller/{TimesheetTeam,Invoice,InvoicePaymentApprovalLevel}ControllerTest.php tests/Invoice/{InvoicePaymentApprovalPolicy,InvoicePaymentApprovalService,InvoicePaymentApprovalLevelResolver}Test.php` (full timesheet-approval + invoice-payment-approval capability sweep) | **184/184 pass** |
| Broader combined sweep (3 narrow files + broader Entity/Voter/Controller set from tasks.md 4.12, 12 files total) | **248/248 pass**, zero regressions (this exact 12-file combination totals 248, not literally "137" — see Warnings) |
| `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` | **Exactly 1 error**, pre-existing/unrelated: `QuotationControllerTest.php:296` return-type mismatch, untouched by this change |
| `bin/console lint:twig templates/approvals_dashboard/` | OK, 1 file valid |
| `bin/console lint:xliff translations/` | OK, 602 files valid |
| `git status --short` after all runs | clean — no residual mutations |

## 8. Closing Spec-Completeness Check (entire `approval-workflows-expansion` change, all 4 PRs combined)

Cross-checked all three capability specs against the code merged into `main` (PR1+PR2+PR3+PR-T all confirmed merged at `main@befd5a1`, prior to PR4 branching) plus PR4 itself.

### `timesheet-approval` spec — all 6 requirements implemented and tested
- Team lead can approve a team member's entry — `TimesheetVoter::APPROVE`, `isTeamleadOf()`; `testApproveTimesheetGrantedForTeamLeadOfProject`, `testApproveActionApprovesEntryForTeamLead`.
- Self-approval allowed — `testApproveTimesheetGrantedForSelfApprovalByLead`, `testApproveActionAllowsSelfApprovalByLead`.
- Non-team-lead cannot approve — `testApproveTimesheetDeniedForNonTeamLead`, `testApproveActionDeniedForNonTeamLead`.
- Approved entries read-only — `testApprovedEntryDeniesEditAndDeleteForOwner`, `testApprovedEntryDeniesEditAndDeleteForTeamLead`, `testApprovedEntryIsNoLongerEditableByOwner`; pending entries stay editable — `testUnapprovedEntryStillEditableByOwner`.
- Team lead can reject a pending entry (D3: no-op, stays/returns to unapproved+editable) — `testRejectActionIsNoOpAndKeepsEntryEditable`. Matches design's explicit, documented deviation from a persisted-reject-state model; spec's own "Design note" flags this as an accepted non-blocking assumption.

### `invoice-payment-approval` spec — all 7 requirements implemented and tested
- PAID blocked without submission/uncleared levels — `testChangeStatusToPaidIsDeniedForUnsubmittedInvoice` (both `changeStatusAction`/`editAction` gate points per D6).
- Submission freezes levels — `testSubmitForPaymentApprovalFreezesRequiredLevels`, `testSubmitPaymentApprovalActionFreezesRequiredLevels`.
- Amount change post-submission does not reopen — `testAmountChangeAfterPartialClearanceDoesNotReopenClearedLevels`.
- Only eligible approvers clear a level — `testUserWithMatchingRoleCanApproveThePendingLevel`, `testUserWithoutTheRequiredRoleCannotApprove`, `testApprovePaymentActionByIneligibleApproverIsDenied`.
- All levels cleared unlocks PAID with audit trail — `testClearingFinalPaymentLevelCompletesApproval`, `InvoicePaymentApproval` audit entity + repository present and tested.
- PENDING/CANCELED remain ungated — `testChangeStatusToPendingAndCanceledRemainUngatedForUnsubmittedInvoice`.
- Historical PAID invoices grandfathered — `testAlreadyPaidInvoiceCommentOnlyResaveIsNotBlocked`, `testHistoricalPaidInvoiceShowsNoUnapprovedFlagOrPaymentApprovalActions`, and on the dashboard side `testHistoricalPaidInvoiceNeverAppearsOnDashboard`.

### `approvals-dashboard` spec — all 5 requirements implemented and tested (this PR)
All confirmed above in §2–§6, plus direct test-name mapping: `testDashboardAggregatesPendingItemsAcrossAllThreeDomains`, `testDashboardShowsOnlySingleDomainWhenOthersAreEmptyForUser`, `testDashboardShowsEmptyStateWhenNothingPending`, `testDashboardRowsNavigateToDomainScreensWithNoInlineApproveRejectControls`, `testDashboardDoesNotLeakInvoiceRawRepositoryResultToIneligibleApprover`, `testUserEligibleOnlyForExpenseSeesNoInvoiceOrTimesheetRows`.

**Conclusion**: the entire `approval-workflows-expansion` change (3 capabilities, 4 PRs) is genuinely complete and test-covered as of this PR. Phase 5 (final end-to-end sweep across all 3 domains together, post-merge) has explicitly **not** been run yet, per the apply report's own statement — this is expected and is the correct next step before archive, not a defect of PR4.

## Issues

### CRITICAL
None.

### WARNING
1. **Missing `apply-progress-pr4` Engram artifact.** The required `sdd/approval-workflows-expansion/apply-progress` (or `-pr4`-suffixed) artifact was not found in Engram for this PR (searched multiple query variants; only `apply-progress` for Phase1+2 and `apply-progress-pr3` exist). Verification proceeded on tasks.md's own detailed per-task notes plus direct source/test inspection instead, which was sufficient for a full PASS, but this is a pipeline-hygiene gap the orchestrator/apply phase should close before the next change.
2. **tasks.md 4.12's "137/137" broader-sweep figure is not exactly reproducible from the file list it names.** Running the exact 12-file combination named in 4.12 (3 narrow files + the broader Entity/Voter/Controller set) independently produced **248/248**, not 137. The narrower "129/129" figure *does* reproduce exactly against the 6 files this verification's own instructions specified. All runs are 100% green with zero regressions either way — this is a reporting-precision issue in the apply-phase log, not a correctness or regression risk.
3. **Design deviation (self-documented, non-blocking)**: task 4.10 explicitly deviates from `design.md`'s Data Flow section ("merge, sort by date, render single read-only template") in favor of 3 separately-rendered, per-domain-sorted sections — matching a different part of the same design doc (File Changes table: "single new template, 3 sections... not composed partials"). The spec's "one aggregated list" scenario is still satisfied functionally (one page, all three domains' pending items visible together, confirmed by `testDashboardAggregatesPendingItemsAcrossAllThreeDomains`), so this does not break the spec — flagged per the Decision Gates rule (design deviation → WARNING unless it breaks a spec).

### SUGGESTION
1. Before archive, run Phase 5 (5.1–5.6) as explicitly planned — full 3-domain regression sweep, `expense-access-scoping` (PR #119) IDOR-pattern regression check, phpstan across the whole cumulative diff, full lint sweep, and a clean-DB migration up/down check.
2. Consider an optional pre-merge rebase onto `origin/main` (49f1689) purely for branch hygiene — not required for correctness given the confirmed zero file-overlap.

## Final Verdict: PASS WITH WARNINGS

No CRITICAL issues. The dashboard's permission-leakage defense was independently reproduced (RED→GREEN), not merely trusted. All spec requirements across all three capabilities of the entire `approval-workflows-expansion` change are implemented and covered by passing tests. Safe to merge PR4 without a rebase; proceed to Phase 5 final sweep before archiving the change.
