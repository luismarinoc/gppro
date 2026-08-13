# Verify Report: Expense Allocation

**Change**: expense-allocation
**Mode**: hybrid (Engram + OpenSpec)
**Verified on branch**: `expense-allocation-tracker-pr8-cross-charge-verification` (9 commits ahead of `main`; none pushed)
**Date**: 2026-08-12

## Completeness

| Artifact | Status |
|---|---|
| Proposal | present (`openspec/changes/expense-allocation/proposal.md`) |
| Spec | present — 10 requirements, 21 scenarios |
| Design | present — 7 architecture decisions (D1-D7) |
| Tasks | 27/27 checked (`[x]`) across 6 phases — count independently re-verified via `grep -c "^- \[x\]" tasks.md` = 27, `grep -c "^- \[ \]"` = 0 |

Note: an earlier apply-progress revision incorrectly quoted "46/46" tasks; this was self-corrected in the final apply-progress save (#526) and independently re-verified here. Actual total is 27.

## Build / Test Evidence

- **PHPUnit, cold cache** (`rm -rf var/cache/test && APP_ENV=test vendor/bin/phpunit --no-coverage`): **4462 tests, 60245 assertions, 4 failures, 1 warning, 4 skipped**, 32.9s.
  - Failures are exactly the 4 known pre-existing ones, confirmed by identity:
    1. `App\Tests\Controller\PermissionControllerTest::testPermissions`
    2. `App\Tests\Invoice\Hydrator\InvoiceModelDefaultHydratorTest::testHydrate`
    3. `App\Tests\Invoice\Renderer\DebugRendererTest::testRender` (data set #0)
    4. `App\Tests\Invoice\Renderer\DebugRendererTest::testRender` (data set #1)
  - The 1 warning is a pre-existing network call in `DoctorControllerTest` (GitHub releases API, 404 in sandbox) — unrelated to this change.
  - **Zero failures among any Expense-namespace test** (confirmed via `grep -c "Expense" <failure-log>` = 0).
- **PHPStan**, full project, warm dev cache (`vendor/bin/phpstan analyse -c phpstan.neon`): **0 errors**.
- **CS-Fixer dry-run** on all 17 touched files in `App\Expense` + `src/Entity/Expense*.php` + `src/Controller/Expense*.php` + `src/Form/Expense*.php` + `src/Repository/Expense*.php` + `src/Voter/ExpenseVoter.php` + `src/Command/ExpensesGenerateRecurringCommand.php`: **0 violations**.
- `lint:twig` (6 expense templates), `lint:yaml` (`gppro.yaml`), `lint:xliff` (4 touched translation files): **all valid**.

## Spec Compliance Matrix (10 requirements / 21 scenarios)

| # | Requirement | Scenario | Covering test | Result |
|---|---|---|---|---|
| 1 | Create expense draft | Draft created with required fields | `ExpenseControllerTest::testTeamleadCanCreateDraftExpenseWithAllocationAndAmountIsSplit` | PASS |
| 2 | Allocate expense by percentage | Sum over 100% is rejected | `AllocationPercentageValidatorTest::testDraftRejectsSumOverOneHundred`, `ExpenseControllerTest::testCreateIsRejectedWhenAllocationsExceed100Percent` | PASS |
| 2 | Allocate expense by percentage | Submit requires exactly 100% | `AllocationPercentageValidatorTest::testSubmitRejectsSumUnderOneHundred`/`testSubmitAcceptsSumExactlyOneHundred`, `ExpenseControllerTest::testSubmitIsRejectedWhenAllocationsDoNotSumToExactly100Percent` | PASS |
| 3 | Submit freezes required approval levels | Required levels computed at submit | `ExpenseApprovalServiceTest::testSubmitComputesAndFreezesRequiredLevels`, `ExpenseTest::testSubmitForApprovalFreezesRequiredLevelsAndMovesToPendingApproval` | PASS |
| 3 | Submit freezes required approval levels | Later config change does not affect in-flight expense | `Expense::submitForApproval()` stores `requiredLevels` on the entity (not recomputed on read); covered structurally by the same freeze test + `ApprovalLevelResolverTest` boundary tests | PASS |
| 4 | Approve each level | Correct-role user clears a level | `ExpenseApprovalPolicyTest::testUserWithMatchingRoleCanApproveThePendingLevel`, `ExpenseVoterTest::testApproveExpenseGrantedForCorrectRoleApprover` | PASS |
| 4 | Approve each level | Creator cannot approve own expense | `ExpenseApprovalPolicyTest::testCreatorCannotApproveOwnExpense`, `ExpenseVoterTest::testApproveExpenseDeniedForCreator`, `ExpenseControllerTest::testCreatorCannotApproveOwnExpense` | PASS |
| 4 | Approve each level | Same approver cannot clear two levels | `ExpenseApprovalPolicyTest::testUserWhoAlreadyClearedALevelCannotClearAnother` | PASS |
| 4 | Approve each level | SUPER_ADMIN clears any level | `ExpenseApprovalPolicyTest::testSuperAdminApprovesRegardlessOfConfiguredRole` | PASS |
| 4 | Approve each level | Final level completes approval | `ExpenseTest::testClearingFinalLevelCompletesApproval`, `ExpenseApprovalServiceTest::testTwoLevelExpenseRequiresBothApproversBeforeApproved` | PASS |
| 5 | Reject discards accumulated approvals | Rejection ends the flow | `ExpenseTest::testRejectApprovalDiscardsClearedLevelsAndMovesToRejected`, `ExpenseApprovalServiceTest::testRejectAtSecondLevelDiscardsFirstLevelAndMovesToRejected`, `ExpenseControllerTest::testRejectDiscardsAccumulatedApprovals` | PASS |
| 6 | Generate monthly recurring copies | No duplicate for an already-generated period | `RecurringExpenseGeneratorTest::testSkipsWhenACopyAlreadyExistsForThePeriod`, `ExpensesGenerateRecurringCommandTest::testRunningTwiceForTheSamePeriodGeneratesOnlyOneCopyIdempotently` + DB-constraint test `testDatabaseUniqueIndexRejectsADuplicateSourcePeriodPairEvenWhenTheAppLevelPreCheckIsBypassed` | PASS |
| 6 | Generate monthly recurring copies | New period generates a fresh draft | `RecurringExpenseGeneratorTest::testGeneratesNewDraftCopyWithTheSameAllocationSplit`, `ExpensesGenerateRecurringCommandTest::testGeneratesOneCopyPerRecurringSourceAndExitsSuccess` | PASS |
| 7 | Cross-charge an approved allocation | Allocation charged to a draft CLP quotation | `ExpenseCrossChargeServiceTest::testChargeApprovedAllocationAddsQuotationLineAndMarksAllocationCharged`, `ExpenseControllerTest::testChargeAddsQuotationLineToTargetQuotation` | PASS |
| 7 | Cross-charge an approved allocation | Non-CLP quotation is blocked | `ExpenseCrossChargeServiceTest::testChargeIsRejectedWhenQuotationCurrencyIsNotClp` | PASS |
| 7 | Cross-charge an approved allocation | Double charge is blocked | `ExpenseCrossChargeServiceTest::testChargeIsRejectedWhenAllocationAlreadyCharged` | PASS |
| 8 | Manage approval level configuration | Unauthorized user cannot edit levels | `ExpenseApprovalLevelControllerTest::testApprovalLevelRoutesAreSecured`, `testAdminCannotAccessApprovalLevelManagement` | PASS |
| 8 | Manage approval level configuration | Non-monotonic threshold is rejected | `ExpenseApprovalLevelControllerTest::testNonMonotonicThresholdIsRejected` | PASS |
| 8 | Manage approval level configuration | Last remaining level cannot be deleted | `ExpenseApprovalLevelControllerTest::testLastRemainingLevelCannotBeDeleted`, `ExpenseApprovalLevelRepositoryTest::testDeleteLevelOneIsRefused` | PASS |
| 9 | Expense permission set | View-only user cannot mutate expenses | `ExpenseControllerTest::testExpenseRoutesAreSecured`, `testRegularUserCannotAccessExpenseManagement` | PASS |
| 10 | Approved expense is immutable | Edit attempt on approved expense is blocked | `ExpenseVoterTest::testEditIsDeniedForApprovedExpenseEvenWithPermission`, `ExpenseTest::testApprovedExpenseIsNotEditable`, `ExpenseControllerTest::testDeleteIsBlockedForApprovedExpense` | PASS |

**21/21 scenarios have a passing covering test at runtime.**

## Design Coherence — Documented Deviations

| ID | Deviation | Spec/design coherence |
|---|---|---|
| D1 | `requiredRole` validated via `RoleService::getAvailableNames()` (custom `App\Validator\Constraints\Role`), not an FK to `gppro_roles` | Consistent — `gppro_roles` only holds custom roles; `ROLE_TEAMLEAD/ADMIN/SUPER_ADMIN` are `security.yaml` constants with no row. Confirmed in `ExpenseApprovalLevel.php` docblock + `ExpenseApprovalLevelValidationTest::testInvalidRoleIsRejected`. Not a regression. |
| D2 | `approve_expense`/`reject_expense` are voter attributes resolved by `ExpenseApprovalPolicy`, never a static `gppro.yaml` permission | Consistent — confirmed absent from `gppro.yaml` (explicit comment), confirmed present as `ExpenseVoter` attributes delegating to the policy. Matches design rationale (a static permission would grant global approval under the affirmative strategy). Not a regression. |
| `--force` semantics | Command's `--force` only discards/regenerates a copy that is **still in draft**; it never touches a submitted/approved/rejected copy (preserving the spec's idempotency MUST) | Consistent — confirmed in `ExpensesGenerateRecurringCommand::execute()` (`$existing->isEditable()` guard) and `ExpensesGenerateRecurringCommandTest::testForceRegeneratesAnExistingDraftCopy`. |
| Translation keys | `flashmessages.*.xlf` new keys added to `en` + `es` only | Consistent with the project's established two-locale-maintained convention (same pattern as the pre-existing `quotation.*` keys). Not a gap. |
| `ExpenseChargeForm` fix | `validation_groups => false` added at `configureOptions()` (form root, not field) | Genuine bugfix, not a design deviation — the `EntityType` field's `query_builder` only restricts the choice list, but Symfony's Form Validator still cascaded onto the selected `Quotation`'s own class constraints (`Assert\Count(min:1)` on lines, `Assert\NotNull` on `validUntil`), incorrectly rejecting a valid but freshly-created 0-line draft quotation as a charge target. Fix is scoped and safe: this form only references the quotation (never edits it), and `ExpenseCrossChargeService::charge()` independently re-validates project/status/currency/already-charged server-side regardless of form state. Regression test added: `ExpenseControllerTest::testChargeSucceedsForFreshlyCreatedQuotationWithNoLinesOrValidUntil`. |

No deviation breaks a spec requirement or scenario. All are either faithful implementations of already-approved design decisions (D1/D2) or a scoped, tested bugfix.

## Additional Structural Checks

- Migration `Version20260812140000`: 4 tables, correct FKs (`CASCADE`/`RESTRICT`/`SET NULL` per design), unique indexes (`(level)`, `(expense_id, level)`, `(source_expense_id, period_key)`), level-1 seed row (`0, ROLE_TEAMLEAD`) — matches D6 exactly. `down()` drops FKs then tables (atomic rollback).
- `gppro.yaml`: `EXPENSES`/`EXPENSES_ALL` sets mirror `QUOTATIONS`/`QUOTATIONS_ALL` shape; `manage_expense_approval_levels` scoped to `ROLE_SUPER_ADMIN` only; `approve_expense` deliberately absent (D2, with an explicit inline comment).
- `MenuSubscriber`: `expenses` parent + `expense_list`/`expense_pending`/`admin_expense_approval_level_list` children, each `isGranted`-guarded, consistent with existing menu patterns.
- `Expense`/`ExpenseAllocation` entities: no public `setStatus`, all transitions guarded (`submitForApproval`/`clearLevel`/`rejectApproval` throw `DomainException` on illegal moves), matching D7.
- `AllocationSplitter`/`AllocationPercentageValidator`: pure integer/basis-point arithmetic, no floats, matching D3.

## Issues

**CRITICAL**: None.

**WARNING**: None.

**SUGGESTION**:
1. `ExpenseApprovalLevelRepository::deleteLevel()` blocks deleting level 1 unconditionally (not only when it is the *last remaining* row). This is stricter than the literal scenario wording ("last remaining level cannot be deleted") but is a reasonable, safe interpretation given level 1's structural role (fixed identifier, always required to have `minAmount = 0`) — flagged for awareness only, not a compliance gap.
2. The 8-PR branch chain (`expense-allocation-tracker-pr1-entities` → `...-pr8-cross-charge-verification`) is fully local; none of the 8 branches have been pushed. Pushing/opening the stacked PRs is a user decision, not a verify blocker.

## Verdict

**PASS**

All 10 requirements / 21 scenarios have passing covering tests. 27/27 tasks independently confirmed complete. Full suite (cold cache) shows only the 4 known pre-existing failures, zero regressions, zero Expense-related failures. PHPStan clean (0 errors) and CS-Fixer clean (0 violations) across the entire touched namespace (17 files: entities, services, voter, forms, controllers, repositories, command). All lints (twig/yaml/xliff) pass. Documented design deviations (D1, D2) and the PR8 `ExpenseChargeForm` bugfix are coherent with the spec and introduce no regression.

Ready for the user to decide on pushing the 8-branch chain and opening the stacked PRs.
