# Verify Report: Expense Approval By Person

```yaml
schema: gentle-ai.verify-result/v1
verdict: pass
blockers: 0
critical_findings: 0
requirements: 2/2
scenarios: 14/14
test_command: vendor/bin/phpunit --group=integration tests/Entity/Expense*.php tests/Expense/ tests/Form/Expense*.php tests/Controller/Expense*.php tests/Repository/Expense*.php tests/Voter/ExpenseVoterTest.php tests/Command/ExpensesGenerateRecurringCommandTest.php
test_exit_code: 0
test_output_hash: sha256:24a80c70a2baa1cdffcd68802369e7a8d227bca26186f86dd6deded985956ea3
build_command: vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
build_exit_code: 1
build_output_hash: sha256:1fb3addfa79f58cbda285c021c2a545ac0cf50ea1436bb255418025f5908d5b0
```

**Change**: expense-approval-by-person
**Mode**: hybrid (Engram + OpenSpec)
**Verified on branch**: `expense-approval-by-person` (6 commits ahead of `main`; none pushed)
**Date**: 2026-08-13

## Completeness

| Artifact | Status |
|---|---|
| Proposal | present (`openspec/changes/expense-approval-by-person/proposal.md`) |
| Design | present — 8 architecture decisions (D1-D8) |
| Spec | present — 2 MODIFIED requirements, 14 scenarios |
| Tasks | 25/25 checked (`[x]`) — independently re-verified via `grep -c "^- \[x\]" tasks.md` = 25, `grep -c "^- \[ \]"` = 0 |

## Build / Test Evidence

- **PHPUnit, expense namespace + integration group** (`vendor/bin/phpunit --group=integration tests/Entity/Expense*.php tests/Expense/ tests/Form/Expense*.php tests/Controller/Expense*.php tests/Repository/Expense*.php tests/Voter/ExpenseVoterTest.php tests/Command/ExpensesGenerateRecurringCommandTest.php`): **68 tests, 283 assertions, 0 failures**.
- **`bin/console lint:twig templates/expense_approval_level/`**: `[OK] All 2 Twig files contain valid syntax.`
- **`bin/console lint:xliff translations/messages.en.xlf translations/messages.es.xlf`**: `[OK] All 2 XLIFF files contain valid syntax.`
- **`vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress`**: 1 error — confirmed by identity to be the **pre-existing, unrelated** `Controller/QuotationControllerTest.php::decodeJsonResponse()` return-type mismatch (line 296). No error touches any file changed by this change. Exit code 1 is expected/accepted for this single known finding, not a regression.

## Spec Compliance Matrix (2 requirements / 14 scenarios)

| # | Requirement | Scenario | Covering test | Result |
|---|---|---|---|---|
| 1 | Approve each level | Correct-role user clears a role-only level | `ExpenseApprovalPolicyTest::testUserWithMatchingRoleCanApproveThePendingLevel` | PASS |
| 1 | Approve each level | Creator cannot approve own expense | `ExpenseApprovalPolicyTest::testCreatorCannotApproveOwnExpense` | PASS |
| 1 | Approve each level | Same approver cannot clear two levels | `ExpenseApprovalPolicyTest::testUserWhoAlreadyClearedALevelCannotClearAnother` | PASS |
| 1 | Approve each level | SUPER_ADMIN clears any level | `ExpenseApprovalPolicyTest::testSuperAdminApprovesRegardlessOfConfiguredRole` | PASS |
| 1 | Approve each level | Final level completes approval | `ExpenseApprovalServiceTest::testTwoLevelExpenseRequiresBothApproversBeforeApproved` | PASS |
| 1 | Approve each level | Role holder clears a level that names a different approver | `ExpenseApprovalPolicyTest::testRoleHolderClearsALevelThatNamesADifferentApprover` | PASS |
| 1 | Approve each level | Named approver clears a level without holding the role | `ExpenseApprovalPolicyTest::testNamedApproverClearsALevelWithoutHoldingTheRole` | PASS |
| 1 | Approve each level | Named approver who is the creator is still denied | `ExpenseApprovalPolicyTest::testCreatorNamedAsApproverIsStillDenied` | PASS |
| 1 | Approve each level | Reassigning the named approver applies live to a pending level | `ExpenseApprovalPolicyTest::testReassigningTheNamedApproverAppliesLiveToAPendingLevel` | PASS |
| 1 | Approve each level | Disabled or removed named approver falls back to role-based decision | `ExpenseApprovalServiceTest::testDeletedNamedApproverFallsBackToRoleBasedDecision` (real EM, `ON DELETE SET NULL`) | PASS |
| 2 | Manage approval level configuration | Unauthorized user cannot edit levels | `ExpenseApprovalLevelControllerTest::testApprovalLevelRoutesAreSecured`, `testAdminCannotAccessApprovalLevelManagement` | PASS |
| 2 | Manage approval level configuration | Non-monotonic threshold is rejected | `ExpenseApprovalLevelControllerTest::testNonMonotonicThresholdIsRejected` | PASS |
| 2 | Manage approval level configuration | Last remaining level cannot be deleted | `ExpenseApprovalLevelControllerTest::testLastRemainingLevelCannotBeDeleted` | PASS |
| 2 | Manage approval level configuration | Level saved with and without a named approver | `ExpenseApprovalLevelControllerTest::testSuperAdminCanCreateApprovalLevelWithANamedApprover` (with) + `testSuperAdminCanListAndCreateApprovalLevel` (without, pre-existing) | PASS |

**14/14 scenarios have a passing covering test at runtime.**

## Security-Relevant Verification (rigorous check per task instructions)

1. **`canDecide()` branch order** — read `src/Expense/ExpenseApprovalPolicy.php:47-81` directly. Confirmed order exactly matches proposal/design: `null === $pendingLevel` deny → `createdBy === $user` deny → `hasUserApprovedAnyLevel()` deny → `isSuperAdmin()` allow → `findLevel()` / `null === $level` deny → **`$level->getApproverUser() === $user`** allow → `hasRole($level->getRequiredRole())` allow. The named-approver branch sits strictly after all three negative gates and after the super-admin branch, never before. **Confirmed.**
2. **Creator-named-as-approver denied** — `ExpenseApprovalPolicyTest::testCreatorNamedAsApproverIsStillDenied` builds an expense created by user D, a level naming user D as `approverUser`, asserts `canApprove()` is `false`. Passed at runtime (part of the 68/68 run). **Confirmed real, passing.**
3. **Already-approved user named on a later level denied** — `ExpenseApprovalPolicyTest::testAlreadyApprovedUserNamedOnLaterLevelIsStillDenied` mocks `hasUserApprovedAnyLevel()` → `true` for a user named `approverUser` on level 2, asserts denial. Passed at runtime. **Confirmed real, passing.**
4. **OR-semantics both directions** — re-read the spec's literal scenario wording (not assumed): "Role holder clears a level that names a different approver" (a *different* role holder, not the named user, clears a level that names someone else) is covered by `testRoleHolderClearsALevelThatNamesADifferentApprover`; "Named approver clears a level without holding the role" is covered by `testNamedApproverClearsALevelWithoutHoldingTheRole` (approver explicitly holds no roles). Both match the spec scenario text verbatim, not a paraphrase or a different assumption. **Confirmed, both directions, both passing.**
5. **Live/retroactive reassignment (A2)** — `testReassigningTheNamedApproverAppliesLiveToAPendingLevel` asserts the original approver can decide, then calls `$level->setApproverUser($newApprover)` on the *same* level/expense instance (no snapshot), then asserts the new approver can decide and the original approver can no longer decide (absent the role). No re-submission, no frozen copy involved. **Confirmed, passing.**
6. **`SET NULL` fallback via real EntityManager** — `ExpenseApprovalServiceTest::testDeletedNamedApproverFallsBackToRoleBasedDecision` (`#[Group('integration')]`, `AbstractRepositoryTestCase`, real Doctrine EM against the test DB) persists a level naming a real `User` row, submits an expense, `$em->remove($namedApprover); $em->flush();`, then `$em->refresh($levelOne)`, asserts `getApproverUser()` is `null`, then asserts a role holder can still approve and the expense reaches `approved`. This exercises the actual FK `ON DELETE SET NULL` in the database, not a mock. **Confirmed real integration test, passing.**
7. **`include_users` set per D6** — `src/Form/ExpenseApprovalLevelForm.php:37-43` passes `'include_users' => ($level?->getApproverUser() !== null ? [$level->getApproverUser()] : [])`, matching design's exact interface block. **Confirmed present in code.**

## Correctness (Static Evidence)

| Requirement | Status | Notes |
|------------|--------|-------|
| `ExpenseApprovalLevel.$approverUser` | Implemented | Nullable `ManyToOne User`, `approver_user_id`, `onDelete: 'SET NULL'`, nullable setter — matches design's exact interface block |
| Migration `Version20260813160000` | Implemented | Additive column + index + `ON DELETE SET NULL` FK referencing `gppro_users(id)`, same convention as `created_by_id`/`approved_by_id`/`supervisor_id`; ordering after `Version20260813150000` confirmed |
| `ExpenseApprovalLevelForm` | Implemented | `approverUser` via `UserType::class`, `required: false`, `include_users` (D6), `help` key (D5) |
| `templates/expense_approval_level/index.html.twig` | Implemented | New `<th>`/`<td>` column using `widgets.label_user()`, muted em-dash for null, `colspan` 4→5 |
| `templates/expense_approval_level/edit.html.twig` | Correctly unchanged | Confirmed byte-identical to precedent — `form_widget(form)` renders the new field generically (D5) |
| Translations | Implemented | `gpExpense50`/`gpExpense51` present in both `messages.en.xlf` and `messages.es.xlf` with matching ids/resnames; `es` file carries `xml:space="preserve"` and `state="translated"` per project convention |

## Coherence (Design)

| Decision | Followed? | Notes |
|----------|-----------|-------|
| D1 OR-semantics | Yes | Both directions tested (see item 4 above) |
| D2 Branch placement below gates | Yes | Verified by direct source read (see item 1 above), not inferred |
| D3 Live read, no snapshot | Yes | `findLevel()` re-reads `levelRepository` on every `canDecide()` call; no field caches the resolved rule |
| D4 `ON DELETE SET NULL` | Yes | Confirmed in migration SQL and exercised by a real-EM integration test |
| D5 Help text via form option | Yes | `edit.html.twig` correctly left untouched |
| D6 `include_users` for disabled assignee | Yes | Present verbatim in `ExpenseApprovalLevelForm.php` |
| D7 `===` identity check | Yes | `$level->getApproverUser() === $user`, mirrors `getCreatedBy() === $user` |
| D8 Raw-SQL migration style, `UPPER_SNAKE` names | Yes | `IDX_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER`, `FK_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER` |

## Issues Found

**CRITICAL**: None.

**WARNING**: None.

**SUGGESTION**:
1. `phpstan analyse` reports exit code 1 due to the single pre-existing `QuotationControllerTest::decodeJsonResponse()` finding, unrelated to this change and already flagged in the archived `expense-allocation` verify report's lineage. No action needed for this change; carried forward as a known, accepted background finding.
2. `ExpenseApprovalLevelFormTest::testApproverUserFieldIsPresent` asserts field presence only (not `required === false` via `$form->get()`), consistent with the pre-existing `requiredRole`/`UserType` unmockable-constructor limitation in `TypeTestCase`. The `required === false` behavior is instead proven end-to-end by the controller integration test (`testSuperAdminCanCreateApprovalLevelWithANamedApprover` succeeding, and the pre-existing `testSuperAdminCanListAndCreateApprovalLevel` succeeding without the key). This is a documented, deliberate test-layer tradeoff, not a gap.
3. None of the 6 local commits on `expense-approval-by-person` have been pushed. Pushing/opening a PR is a user decision, not a verify blocker.

## Verdict

**PASS**

Both modified requirements / all 14 scenarios have passing covering tests, independently re-run (68/68 tests, 283 assertions, 0 failures) rather than trusted from `tasks.md`. 25/25 tasks independently confirmed complete via direct grep. The security-relevant `canDecide()` branch order was verified by direct source inspection, not by re-stating the design doc: the named-approver check sits strictly after the null-pending-level, creator, and four-eyes gates, and after the super-admin branch — it can never bypass them. All six specifically-requested security proofs (creator-named-as-approver denied, already-approved-named-on-later-level denied, OR-semantics both directions matching the spec's literal wording, live/retroactive reassignment, real-EntityManager `SET NULL` fallback, and `include_users` presence) are backed by real, passing tests, with the FK-cascade proof running against a genuine Doctrine EM rather than a mock. Twig and XLIFF lints pass. PHPStan shows exactly one pre-existing, unrelated finding and zero new errors. `edit.html.twig` is correctly untouched. No CRITICAL or WARNING findings.
