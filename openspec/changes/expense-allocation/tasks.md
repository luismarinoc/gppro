# Tasks: Expense Allocation

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~4,800–5,500 (26 new files + 3 modified; entities/controllers/templates in this repo run 200–320 lines each, e.g. `Quotation.php` 259, `quotation/edit.html.twig` 321) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 → PR10 (10 units, see below) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending — needs user decision |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

Three PRs (as first proposed) still land 900–1,900 lines each — still 2–5x over budget. Splitting to 10 units keeps each near 300–650 lines. Units 1, 4, 7, 9 stay slightly above 400 because splitting further would break atomic entity+migration+test or controller+template deliverables — flag these for maintainer size awareness rather than fragmenting unsafely.

### Suggested Work Units

| Unit | Goal | PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| 1 | Migration + `Expense`/`ExpenseAllocation` entities + tests | PR1 | `phpunit tests/Entity/ExpenseTest.php tests/Entity/ExpenseAllocationTest.php` | `doctrine:migrations:migrate` on test DB | drop new tables, revert migration |
| 2 | `ExpenseApprovalLevel`/`ExpenseApproval` entities + repos + tests | PR2 | `phpunit tests/Entity tests/Repository/Expense*` | KernelTestCase + DB | delete 2 entity/repo files, no FK from PR1 |
| 3 | Pure domain services: Splitter/Validator/Resolver/Policy + unit tests | PR3 | `phpunit tests/Expense/AllocationSplitterTest.php tests/Expense/ApprovalLevelResolverTest.php tests/Expense/ExpenseApprovalPolicyTest.php` | none (pure PHP) | delete `src/Expense/{Splitter,Validator,Resolver,Policy}` |
| 4 | Transactional services: ApprovalService/CrossCharge/RecurringGenerator + integration tests | PR4 | `phpunit tests/Expense/ExpenseApprovalServiceTest.php tests/Expense/ExpenseCrossChargeServiceTest.php` | KernelTestCase + DB | delete 3 service files, unused by PR1-3 |
| 5 | `ExpenseVoter` + `gppro.yaml` EXPENSES set + voter tests | PR5 | `phpunit tests/Voter/ExpenseVoterTest.php` | N/A — pure voter logic | revert yaml block, delete voter |
| 6 | 5 forms (`ExpenseForm`, allocation/level/charge/decision) | PR6 | manual form render smoke (no dedicated form tests) | `symfony console debug:form` | delete `src/Form/Expense*` |
| 7 | `ExpenseController` CRUD + list/edit/view templates + MenuSubscriber + translations | PR7 | `phpunit tests/Controller/ExpenseControllerTest.php` | staging manual click-through: create/edit/view expense | delete controller+templates, revert menu/i18n diff |
| 8 | Workflow actions (submit/approve/reject/charge) + pending template | PR8 | `phpunit tests/Controller/ExpenseControllerTest.php --filter=Workflow` | staging: submit → approve x2 → charge | revert action methods added in PR8 only |
| 9 | `ExpenseApprovalLevelController` + admin templates + tests | PR9 | `phpunit tests/Controller/ExpenseApprovalLevelControllerTest.php` | staging: manage levels as SUPER_ADMIN | delete controller+templates |
| 10 | `gppro:expenses:generate-recurring` command + idempotency tests | PR10 | `phpunit tests/Command/ExpensesGenerateRecurringCommandTest.php` | `bin/console gppro:expenses:generate-recurring --dry-run` | delete command+test, no other file depends on it |

## Phase 1: Entities, Migration, Repositories

- [x] 1.1 Migration `Version20260812140000`: create 4 tables, FKs, indexes, seed level 1 (0, ROLE_TEAMLEAD); `down()` drops FKs then tables
- [x] 1.2 RED→GREEN: `tests/Entity/ExpenseTest.php` then `src/Entity/Expense.php` — `submitForApproval`/`clearLevel`/`rejectApproval`/`isEditable` throw `DomainException` on illegal transitions, no public `setStatus`
- [x] 1.3 RED→GREEN: `tests/Entity/ExpenseAllocationTest.php` then `src/Entity/ExpenseAllocation.php` — `markCharged` throws when already charged
- [x] 1.4 GREEN: `src/Entity/ExpenseApprovalLevel.php` — `Assert\Callback` monotonic invariant (level-1-zero, self-contained), `Constraints\Role` requiredRole via `RoleService` (reused existing `App\Validator\Constraints\Role`)
- [x] 1.5 GREEN: `src/Entity/ExpenseApproval.php` — unique `(expense_id, level)`
- [x] 1.6 GREEN: `src/Repository/ExpenseRepository.php`, `ExpenseApprovalLevelRepository.php` (refuse deleting level 1), `ExpenseApprovalRepository.php`
- [x] 1.7 Verify: run migration on test DB, confirm 4 tables + seed row — confirmed via `SHOW TABLES` + seed row query

## Phase 2: Domain Services (pure + transactional)

- [x] 2.1 RED→GREEN: `AllocationSplitterTest.php` then `AllocationSplitter.php` — 40/60, 33.33x3 remainder-to-last
- [x] 2.2 RED→GREEN: `AllocationPercentageValidatorTest.php` then `AllocationPercentageValidator.php` — ≤100% draft, ===100% submit
- [x] 2.3 RED→GREEN: `ApprovalLevelResolverTest.php` then `ApprovalLevelResolver.php` — 500k→1, 2M→2, boundary `amount===minAmount`
- [x] 2.4 RED→GREEN: `ExpenseApprovalPolicyTest.php` then `ExpenseApprovalPolicy.php` — creator exclusion, repeat-approver exclusion, role match, SUPER_ADMIN break-glass
- [x] 2.5 RED→GREEN (KernelTestCase): `ExpenseApprovalServiceTest.php` then `ExpenseApprovalService.php` — transactional submit/approve/reject, audit row+counter+status
- [x] 2.6 RED→GREEN: `ExpenseCrossChargeServiceTest.php` then `ExpenseCrossChargeService.php` — project match, draft+CLP quotation, double-charge rejected
- [x] 2.7 RED→GREEN: `RecurringExpenseGeneratorTest.php` then `RecurringExpenseGenerator.php` + result VO/status enum — GENERATED/SKIPPED_EXISTING/SKIPPED_NOT_RECURRING

## Phase 3: Voter + Permissions

- [x] 3.1 RED→GREEN: `tests/Voter/ExpenseVoterTest.php` then `src/Voter/ExpenseVoter.php` — static attrs via `RolePermissionManager`, approve/reject via `ExpenseApprovalPolicy`, edit/delete require `isEditable()`, charge requires `isApproved()`
- [x] 3.2 `config/packages/gppro.yaml` — EXPENSES/EXPENSES_ALL sets, role maps, `manage_expense_approval_levels` on SUPER_ADMIN (no `approve_expense` permission — D2)

## Phase 4: Forms + Controllers + Templates

- [x] 4.1 GREEN: `ExpenseForm.php`, `ExpenseAllocationForm.php`, `ExpenseApprovalLevelForm.php`, `ExpenseChargeForm.php`, `ExpenseApprovalDecisionForm.php`
- [x] 4.2 RED→GREEN: `ExpenseControllerTest.php` (CRUD actions) then `ExpenseController.php` list/create/edit/view — `DomainException` → `flashError`
- [x] 4.3 Templates: `expense/index.html.twig`, `expense/edit.html.twig` (allocation prototype, live % total), `expense/view.html.twig` — minimal (quotation_catalog-style) templates, not the full Quotation-parity ones
- [x] 4.4 RED→GREEN: extend `ExpenseControllerTest.php` (workflow) then `submit/approve/reject/charge` actions
- [x] 4.5 Template: `expense/pending.html.twig`
- [x] 4.6 RED→GREEN: `ExpenseApprovalLevelControllerTest.php` then `ExpenseApprovalLevelController.php` (index/create/edit/delete, gated `manage_expense_approval_levels`)
- [x] 4.7 Templates: `expense_approval_level/index.html.twig`, `edit.html.twig` — minimal; "warn zero active users for role" enhancement deferred, not required by spec
- [ ] 4.8 `MenuSubscriber.php` — `expenses` parent + children, `isGranted`-guarded; `translations/messages.*.xlf` new keys

## Phase 5: Recurrence Command

- [ ] 5.1 RED→GREEN (CommandTester): `ExpensesGenerateRecurringCommandTest.php` then `ExpensesGenerateRecurringCommand.php` — `--period`/`--force`/`--dry-run`, idempotent per `(source_expense_id, period_key)`

## Phase 6: Cross-Charge / Quotation Integration Verification

- [ ] 6.1 Extend `ExpenseCrossChargeServiceTest.php` — assert real `QuotationLine` created on target `Quotation` (qty `'1'`, unitPrice=amountClp, description `"<desc> (<date>)"`), no duplicated quotation logic
- [ ] 6.2 Integration test: `(source_expense_id, period_key)` unique index enforces idempotency under concurrent command runs
