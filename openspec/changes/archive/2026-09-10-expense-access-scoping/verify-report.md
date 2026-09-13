```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:468c86602132365cb58112e1defd9a4b222b6492a482c1086838217190fc8ba0
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 2/2
scenarios: 7/7
test_command: vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php tests/Voter/ExpenseVoterTest.php tests/Repository/ExpenseRepositoryTest.php tests/Form/ExpenseFormTest.php tests/Entity/ExpenseValidationTest.php tests/Command/ExpensesGenerateRecurringCommandTest.php tests/Controller/ExpenseApprovalLevelControllerTest.php; vendor/bin/phpunit $(find tests -path '*Expense*Test.php' -print | sort) tests/Voter/ExpenseVoterTest.php; vendor/bin/phpunit tests/Controller/QuotationControllerTest.php; ./php-cs-fixer.sh core; ./phpstan.sh test
test_exit_code: 0
test_output_hash: sha256:3a5bb5d1d44b57d0cb3ef0f40e214573b4c16d230bfc8ec63a7411cfc38e010b
build_command: not_applicable
build_exit_code: 0
build_output_hash: sha256:3a5bb5d1d44b57d0cb3ef0f40e214573b4c16d230bfc8ec63a7411cfc38e010b
```

# Verify Report: Expense Access Scoping

## Status

Verified with one correction applied during verification.

The original implementation closed the primary expense IDOR in voter, listing, and controller merge paths. Verification found one list/detail mismatch in the repository DQL branch: allocation-less expenses created by another user could match the left-joined public-project condition because the joined project alias was `NULL`. The fix now requires a real joined project (`p.id IS NOT NULL`) before the team-accessible allocation branch can match.

## Correction Evidence

| Step | Command | Result |
| --- | --- | --- |
| RED | Temporarily ran the new regression against the pre-fix repository expression | Failed as expected: `Failed asserting that an array does not contain 1.` |
| GREEN focused | `vendor/bin/phpunit tests/Repository/ExpenseRepositoryTest.php --filter testFindForListingExcludesAllocationLessExpenseForStranger` | OK: 1 test, 1 assertion |
| GREEN repository | `vendor/bin/phpunit tests/Repository/ExpenseRepositoryTest.php` | OK: 17 tests, 28 assertions |

## Validation Commands

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php tests/Voter/ExpenseVoterTest.php tests/Repository/ExpenseRepositoryTest.php tests/Form/ExpenseFormTest.php tests/Entity/ExpenseValidationTest.php tests/Command/ExpensesGenerateRecurringCommandTest.php tests/Controller/ExpenseApprovalLevelControllerTest.php` | OK: 105 tests, 421 assertions; 24 remaining self deprecation notices |
| `vendor/bin/phpunit $(find tests -path '*Expense*Test.php' -print \| sort) tests/Voter/ExpenseVoterTest.php` | OK: 226 tests, 708 assertions; 48 remaining self deprecation notices |
| `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` | OK: 16 tests, 129 assertions |
| `./php-cs-fixer.sh core` | OK / clean after fixing one unrelated extra blank line in `tests/Controller/QuotationControllerTest.php`; warning: local runtime is PHP 8.5.8 while project minimum is PHP 8.2 |
| `./phpstan.sh core` | Failed with 2 pre-existing out-of-scope Doctrine DQL errors in `src/Repository/TimesheetRepository.php` lines 1013 and 1037 (`Timesheet` has no `approvalStatus` field) |
| `./phpstan.sh test` | OK: no errors |
| LSP primary diagnostics for changed PHP files | OK: 0 diagnostics |

A transient test database reset failure occurred after the RED run (`FK_GPPRO_LOGIN_ATTEMPTS_USER` drop mismatch / missing tables). The disposable `kimai2_test` schema was dropped and recreated with the configured test credentials, after which PHPUnit reset and reran successfully.

## Requirement Mapping

| Requirement / scenario | Verification |
| --- | --- |
| Team-accessible allocation grants visibility | Covered by `ExpenseVoterTest`, `ExpenseRepositoryTest`, `ExpenseControllerTest`; included in focused and full expense test runs. |
| Approver carve-out grants visibility without a team-project match | Covered by voter/controller tests and controller merge through `ExpenseApprovalPolicy::canApprove()`. |
| Creator always sees their own expense | Covered by voter/repository/controller tests; creator branch remains independent of allocation scope. |
| Visibility is OR across multiple allocations | Covered by repository distinct/multi-allocation regression. |
| Admin and super-admin see every expense unchanged | Covered by voter/repository/controller tests; `canSeeAllData()` bypass preserved. |
| Unauthorized direct URL access denied with 403 and excluded from list | Covered by functional controller test; voter denies subject access and listing excludes the row. |
| Allocation-less stranger expense is not list-visible | Added during verification; RED failed pre-fix and GREEN passes after requiring `p.id IS NOT NULL`. |

## Files Changed During Verification

| File | Purpose |
| --- | --- |
| `src/Repository/ExpenseRepository.php` | Require `p.id IS NOT NULL` before the team-accessible branch can match, preventing left-join NULL rows from acting like public projects. |
| `tests/Repository/ExpenseRepositoryTest.php` | Add regression for allocation-less stranger expenses. |
| `tests/Controller/QuotationControllerTest.php` | PHP-CS-Fixer removed one extra blank line. |
| `openspec/changes/expense-access-scoping/verify-report.md` | This verification report. |

## Risks and Follow-ups

- `./phpstan.sh core` is not green because of two out-of-scope, pre-existing `TimesheetRepository` DQL errors. No errors were reported in the expense repository/controller/voter surface or in tests.
- Status filter normalization remains unchanged: invalid `status` query values are treated by the repository as unfiltered, while the controller only merges approver-visible pending rows when the status is null or `pending_approval`. No current acceptance scenario covers invalid status strings, so this is not treated as a blocker for this change.
- No templates, translations, migrations, generated assets, plugins, vendor files, runtime data, or production schema were intentionally changed.

## Next Recommended

Run SDD archive for `expense-access-scoping` once the maintainer accepts the out-of-scope PHPStan core baseline errors as non-blocking for this candidate.
