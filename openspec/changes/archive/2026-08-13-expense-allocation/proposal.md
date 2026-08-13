# Proposal: Expense Allocation

## Intent

gppro records internal cost only as `Timesheet.internalRate`, never aggregated.
Overhead (salaries, rent, licenses) is invisible per project, so real project
cost cannot be stated and internal cost cannot be cross-charged to a client.
Introduce a first-class internal expense that is split across projects by
manual percentage, approved through amount-based approval levels, and then
chargeable to that project's quotation.

## Naming

`Expense` (header) + `ExpenseAllocation` (line), tables `gppro_expenses` /
`gppro_expense_allocations`, namespace `App\Expense`. Mirrors the existing
`Quotation`/`QuotationLine` header+lines precedent. A single flat `Allocation`
entity was rejected: it would duplicate amount, date, recurrence, and approval
state across every row of the same expense.

Approval adds `ExpenseApprovalLevel` (`gppro_expense_approval_levels`, global
configuration) and `ExpenseApproval` (`gppro_expense_approvals`, audit trail).

## Business Rules

| # | Rule |
|---|------|
| 1 | Internal costing expense, not a client-reimbursable expense |
| 2 | Captured directly in gppro; no ERP/accounting import |
| 3 | Amount always CLP; no `ClpConverter`, no currency field |
| 4 | Recurrence: one-off or monthly |
| 5 | Split across `Project` only |
| 6 | Manual percentage per project, entered at capture time |
| 7 | Lifecycle `draft → pending_approval → approved` (any approver may `reject`); only approved is usable |
| 8 | Number of approval levels required depends on the expense amount: larger amounts require more levels |
| 9 | Each approval level is bound to a system role, not to named users: anyone holding that role can clear that level |
| 10 | Approval levels are a single global configuration; they do not vary by project or customer |
| 11 | An expense is `approved` only after every required level has been cleared |
| 12 | An approved allocation can become a line on that project's quotation |
| 13 | Header+lines model: one expense, N allocations |

## Approval Level Model (decision)

**Configuration → new Doctrine entity, not `SystemConfiguration`.**
`Configuration.value` is a flat scalar column (`setValue(string|int|bool|null)`,
no serialization) and `App\Configuration\SystemConfiguration` is a fixed getter
facade over dotted keys backed by a static form model. A variable-length,
ordered list of `(threshold, role)` tuples would force JSON-in-a-string or
synthetic dotted keys plus a hand-built dynamic collection form, and would lose
validation and referential integrity against `gppro_roles`. The project already
has the right precedent for runtime-editable list configuration: `Role` /
`RolePermission` and `FxRate`. So:

`ExpenseApprovalLevel`: `level` (int, unique, 1..N), `minAmount` (CLP int,
amount from which this level becomes required), `requiredRole` (role name
validated against `gppro_roles`).

**Threshold semantics — cumulative, monotonic.** An expense of amount `X`
requires every level whose `minAmount <= X`. Level 1 must have `minAmount = 0`
so at least one approval is always required. Example:

| level | minAmount | requiredRole | effect |
|---|---|---|---|
| 1 | 0 | ROLE_TEAMLEAD | every expense |
| 2 | 1.000.000 | ROLE_ADMIN | expenses ≥ 1M CLP |
| 3 | 10.000.000 | ROLE_SUPER_ADMIN | expenses ≥ 10M CLP |

No bracket table, no gaps, no overlap: the required-level count is simply the
number of rows at or below the amount.

**Progress → one audit row per level, plus a derived counter.**
`ExpenseApproval` (`expense`, `level`, `decision` approve/reject, `approvedBy`,
`approvedAt`, optional `note`) is the source of truth and answers "who cleared
which level and when". `Expense.currentLevel` (int) and
`Expense.requiredLevels` (int, snapshotted at submit) are denormalized in the
same transaction for cheap listing and "pending my approval" queries. A bare
counter alone was rejected: it is not auditable. State is therefore
`pending_approval` with `currentLevel < requiredLevels`; the UI labels it
"pending level N+1".

**Config changes do not rewrite history.** `requiredLevels` is snapshotted when
the expense leaves `draft`, so editing the level table never silently
re-opens or auto-approves in-flight expenses.

## Working Assumptions (confirm in spec/design)

| # | Assumption |
|---|------------|
| A1 | New permission set `EXPENSES: view/create/edit/delete_expense` + `charge_expense`, granted like `QUOTATIONS`; `ExpenseVoter` follows `QuotationVoter` |
| A2 | The flat `approve_expense` permission is dropped. `ExpenseVoter::approve` resolves dynamically: the user must hold the `requiredRole` of the expense's next pending level. Sub-rules: (a) the expense creator cannot approve any of its levels; (b) each level must be cleared by a distinct user; (c) ROLE_SUPER_ADMIN is a break-glass approver for any level; (d) a `reject` at any level returns the expense to `rejected` and discards accumulated levels; (e) a new permission `manage_expense_approval_levels` (ROLE_SUPER_ADMIN, alongside `system_configuration`) guards editing the level table |
| A3 | Percentages must total exactly 100% to submit for approval; drafts may be under 100% but never over. Amounts are rounded to whole CLP with the remainder absorbed by the last allocation |
| A4 | Monthly recurrence uses `recurrence = null\|'month'` (same convention as `BudgetTrait.budgetType`) plus `gppro:expenses:generate-recurring`, modeled on `FxRatesSyncCommand`; generated copies are idempotent per period and land in `draft`, so each period is approved independently through the same level rules |
| A5 | Cross-charge is an explicit user action on an allocation of a fully approved expense, never automatic on approval. It appends a `QuotationLine` (quantity 1, unitPrice = allocated CLP) to a draft quotation of that project, marks the allocation `charged`, and blocks double-charging and non-CLP quotations |
| A6 | An approved expense is immutable; corrections require a new expense |

## Scope

### In Scope
- `Expense` + `ExpenseAllocation` entities, repository, migration.
- `ExpenseApprovalLevel` configuration entity, CRUD screen, seed of a single
  level-1 row so the system is usable on day one.
- `ExpenseApproval` audit entity and multi-level state machine
  (`draft/pending_approval/approved/rejected` + `currentLevel`).
- Percentage validation, CLP amount derivation, guarded transitions.
- Monthly recurrence flag and generation command.
- CRUD UI, "pending my approval" list, `ExpenseVoter`, permission set.
- Manual cross-charge of an approved allocation into a project quotation line.

### Out of Scope
- Per-project, per-customer, or per-category approval level configuration.
- Named-user approvers, delegation, out-of-office substitution, escalation SLAs.
- Approval notifications (email/in-app) — levels are pulled from a list, not pushed.
- Parallel approvals within one level; levels are strictly sequential.
- Multi-currency and FX conversion.
- ERP/accounting integration or import.
- Automatic proration by hours, budget, or headcount.
- A new cost-center entity.
- Direct `Invoice` line creation (reached via the existing quotation→invoice conversion).
- Profitability/margin reporting of cost vs. billed.
- Client-reimbursable expenses and receipt attachments.

## Capabilities

### New Capabilities
- `expense-allocation`: capture an internal CLP expense, split it across projects by manual percentage.
- `expense-approval-levels`: configure amount thresholds and their required roles, and drive an expense through every level its amount requires.
- `expense-cross-charge`: turn an allocation of an approved expense into a quotation line for its project, exactly once.

### Modified Capabilities
- None.

## Approach

Follow the `Quotation` precedent end to end: header entity with guarded
transition methods throwing `\DomainException`, `OneToMany` allocations with
`cascade: ['persist','remove']` and `orphanRemoval: true`, `Assert\Count`
bounds, and controller-level `#[IsGranted]`. Put pure logic in `src/Expense/`
(percentage validator, CLP splitter, recurring generator, cross-charge service,
`ApprovalLevelResolver` that maps an amount to its ordered level list) per the
`src/Milestone/` and `src/FxRate/` screaming-architecture convention. The level
configuration screen follows the `FxRate` / role-permission admin CRUD pattern.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/Entity/Expense.php`, `src/Entity/ExpenseAllocation.php` | New | Header + allocation lines, `currentLevel`/`requiredLevels` |
| `src/Entity/ExpenseApprovalLevel.php` | New | Global threshold → role configuration |
| `src/Entity/ExpenseApproval.php` | New | Per-level audit trail |
| `src/Repository/ExpenseRepository.php`, `ExpenseApprovalLevelRepository.php` | New | Queries, ordered level lookup, pending-for-user |
| `src/Expense/` | New | Splitter, validator, `ApprovalLevelResolver`, approval service, recurring generator, cross-charge service |
| `src/Controller/ExpenseController.php`, `ExpenseApprovalLevelController.php` | New | CRUD, submit/approve/reject, level configuration |
| `src/Voter/ExpenseVoter.php` | New | Dynamic role-per-level approval check |
| `src/Command/ExpensesGenerateRecurringCommand.php` | New | Monthly generation |
| `migrations/Version*.php` | New | Four new tables + level-1 seed |
| `config/packages/gppro.yaml` | Modified | `EXPENSES` set, `manage_expense_approval_levels`, role maps |
| `src/Entity/QuotationLine.php` | Reused | Cross-charge target; no entity change expected |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| A level's `requiredRole` has no active user, so expenses above that threshold stall forever | Med | Warn on the configuration screen when a level's role has zero active users; ROLE_SUPER_ADMIN break-glass approval (A2c); documented as an operational, not technical, failure |
| Misconfigured thresholds (no level at `minAmount = 0`, duplicate levels, non-monotonic amounts) leave expenses unapprovable | Med | Entity-level validation: unique `level`, `minAmount` strictly increasing with `level`, level 1 pinned at 0, table can never be emptied |
| Four-eyes rule (A2a/A2b) blocks small teams where one person holds every role | Med | Confirm with the user in spec; if too strict, relax to creator-cannot-approve only |
| Editing levels mid-flight changes what an in-flight expense needs | Low | `requiredLevels` snapshotted at submit |
| Percentage rounding loses or invents CLP | Med | Last-allocation remainder rule (A3) with explicit tests |
| Recurring command double-generates a period | Med | Idempotency key on source expense + period |
| Cross-charge mutates a client-facing quotation unexpectedly | Med | Manual action, draft quotations only, one-time `charged` flag |
| Scope creep into margin reporting | Med | Explicitly out of scope; allocations are the data source for a later report |

## Rollback Plan

Revert the migration (drops the four new tables), remove the `EXPENSES` and
`manage_expense_approval_levels` permissions, and revert the new namespaces.
Quotation, Invoice, Milestone, Timesheet, `Configuration`, and `Role` behavior
is untouched, and no existing table is altered.

## Dependencies

- Existing `Project`, `Quotation`/`QuotationLine`, `Role`/`RolePermission`, and `RolePermissionManager`.

## Success Criteria

- [ ] An expense of $1.000.000 CLP split 40/60 produces two allocations of $400.000 and $600.000.
- [ ] Submitting for approval is rejected unless percentages total exactly 100%.
- [ ] With levels `0/ROLE_TEAMLEAD` and `1.000.000/ROLE_ADMIN`, a $500.000 expense needs one approval and a $2.000.000 expense needs two.
- [ ] A $2.000.000 expense approved only by a teamlead stays `pending_approval` at `currentLevel = 1` and is not chargeable.
- [ ] A user without the next pending level's role cannot approve, even if they could approve a lower level.
- [ ] Every cleared level records approver and timestamp, queryable per expense.
- [ ] Changing the level configuration does not alter the required levels of an already-submitted expense.
- [ ] A monthly expense regenerates once per period as a draft with the same split and re-enters approval.
- [ ] An allocation of an approved expense produces exactly one quotation line and cannot be charged twice.
