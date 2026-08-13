# Expense Allocation Specification

## Purpose

Internal CLP expense capture, per-project percentage allocation, amount-based
multi-level approval, optional monthly recurrence, and manual cross-charge
into a project quotation.

## Requirements

### Requirement: Create expense draft

The system MUST allow creating an `Expense` in `draft` state with CLP amount,
description, date, an optional monthly recurrence flag, and a required
company-defined category chosen from a fixed set (`CATEGORY_RENT`,
`CATEGORY_EQUIPMENT`, `CATEGORY_ELECTRICITY`, `CATEGORY_PHONE`,
`CATEGORY_SERVICES`, `CATEGORY_OTHER`). Category MUST NOT affect amount,
approval levels, status transitions, or cross-charge.

#### Scenario: Draft created with required fields including category

- GIVEN a user with expense-create permission
- WHEN they submit amount, description, date, and category `rent`
- THEN an `Expense` is created in `draft` state with `category = rent`

#### Scenario: Missing category is rejected at create

- GIVEN a user with expense-create permission
- WHEN they submit amount, description, and date without selecting a category
- THEN the submission is rejected by validation

#### Scenario: Invalid category value is rejected

- GIVEN a user with expense-create permission
- WHEN they submit a category value outside the fixed set
- THEN the submission is rejected by `Assert\Choice`

### Requirement: Allocate expense by percentage

Each `Expense` MUST split into `ExpenseAllocation` rows by manual percentage
per project. The sum MUST NOT exceed 100% at any time and MUST equal exactly
100% to submit for approval.

#### Scenario: Sum over 100% is rejected

- GIVEN a draft with allocations totaling 90%
- WHEN the user adds one that would total 110%
- THEN the change is rejected

#### Scenario: Submit requires exactly 100%

- GIVEN a draft with allocations totaling 90%
- WHEN the user submits for approval
- THEN submission is rejected until the sum equals 100%

### Requirement: Submit freezes required approval levels

On submit, the system MUST compute `requiredLevels` from the expense amount
against current `ExpenseApprovalLevel` rows and store it. Later edits to the
level configuration MUST NOT change an already-submitted expense's value.

#### Scenario: Required levels computed at submit

- GIVEN levels 0/ROLE_TEAMLEAD and 1.000.000/ROLE_ADMIN
- WHEN a 2.000.000 CLP expense is submitted
- THEN `requiredLevels` is set to 2

#### Scenario: Later config change does not affect in-flight expense

- GIVEN a submitted expense with `requiredLevels = 2`
- WHEN a new level 3 is added to the configuration
- THEN the expense's `requiredLevels` stays 2

### Requirement: Approve each level

An approval level MUST be cleared by a user holding that level's
`requiredRole`, OR by the level's optional named `approverUser` if one is
set, or by `ROLE_SUPER_ADMIN`. The named `approverUser` is additive: it MUST
NOT replace `requiredRole`, and it MUST NOT bypass the creator exclusion or
the distinct-approver exclusion. Both the role and the named-approver checks
MUST be evaluated live against the level's current configuration at decision
time — no submit-time snapshot of either. If the named `approverUser` is
unset, disabled, or removed (FK `SET NULL`), the level MUST remain
approvable by any `requiredRole` holder. The expense creator MUST NOT
approve any of its own levels, even when named as that level's
`approverUser`. A user who cleared one level of an expense MUST NOT clear
another level of the same expense, even when named as `approverUser` on it.
Clearing the last required level MUST move the expense to `approved`.
(Previously: a level was clearable only by a `requiredRole` holder or
`ROLE_SUPER_ADMIN`; no named-approver path existed.)

#### Scenario: Correct-role user clears a role-only level

- GIVEN an expense pending at level 1 requiring ROLE_TEAMLEAD with no
  `approverUser` set
- WHEN a ROLE_TEAMLEAD user approves
- THEN `currentLevel` becomes 1, audited in `ExpenseApproval`

#### Scenario: Creator cannot approve own expense

- GIVEN an expense pending approval, created by user A
- WHEN user A attempts to approve
- THEN the approval is denied

#### Scenario: Same approver cannot clear two levels

- GIVEN user B already approved level 1
- WHEN user B attempts to approve level 2 of the same expense
- THEN the approval is denied

#### Scenario: SUPER_ADMIN clears any level

- GIVEN an expense pending at any level
- WHEN a ROLE_SUPER_ADMIN user approves
- THEN the level clears regardless of its configured role or approver

#### Scenario: Final level completes approval

- GIVEN `requiredLevels = 2` and `currentLevel = 1`
- WHEN the level-2 approver approves
- THEN the expense moves to `approved`

#### Scenario: Role holder clears a level that names a different approver

- GIVEN a level requires ROLE_TEAMLEAD and names user C as `approverUser`
- WHEN a ROLE_TEAMLEAD user who is not user C approves
- THEN the level clears (OR-semantics, not replace-semantics)

#### Scenario: Named approver clears a level without holding the role

- GIVEN a level requires ROLE_TEAMLEAD and names user C as `approverUser`,
  where user C does not hold ROLE_TEAMLEAD
- WHEN user C approves
- THEN the level clears

#### Scenario: Named approver who is the creator is still denied

- GIVEN an expense created by user D, pending at a level naming user D as
  `approverUser`
- WHEN user D attempts to approve
- THEN the approval is denied (creator exclusion outranks the named-approver
  match)

#### Scenario: Reassigning the named approver applies live to a pending level

- GIVEN an expense pending at a level naming user C as `approverUser`
- WHEN an admin reassigns that level's `approverUser` to user E while the
  expense is still pending at that level
- THEN user E can now approve that level and user C alone (absent the role)
  can no longer approve it

#### Scenario: Disabled or removed named approver falls back to role-based decision

- GIVEN a level names user C as `approverUser` and requires ROLE_TEAMLEAD,
  and user C is deleted (column set to `NULL` via `ON DELETE SET NULL`) or
  disabled
- WHEN any user holding ROLE_TEAMLEAD approves
- THEN the level clears with no error

### Requirement: Reject discards accumulated approvals

A `reject` at any pending level MUST move the `Expense` to `rejected` and
MUST discard all previously cleared levels of that expense.

#### Scenario: Rejection ends the flow

- GIVEN level 1 already cleared, pending at level 2
- WHEN the level-2 approver rejects
- THEN the expense moves to `rejected` and its cleared levels no longer count

### Requirement: Generate monthly recurring copies

For an expense flagged monthly, the system MUST generate one new `Expense`
copy per period in `draft`, with the same allocation split and the same
category as the source expense. Generation MUST be idempotent per source
expense and period.

#### Scenario: No duplicate for an already-generated period

- GIVEN a monthly expense already generated for August
- WHEN generation runs again for August
- THEN no duplicate copy is created

#### Scenario: New period generates a fresh draft

- GIVEN a monthly expense with no copy for September
- WHEN generation runs for September
- THEN a new `draft` expense is created with the same split

#### Scenario: Generated copy carries the source category

- GIVEN a monthly expense with category `rent`
- WHEN generation runs for a new period
- THEN the generated `draft` copy has `category = rent`

### Requirement: Category required on edit and rendered as a translated label

Editing an existing `Expense` MUST require selecting a category from the
fixed set before the save succeeds, including expenses saved before this
change with no category. Category MUST render as a translated label
(`expense.category.<value>`) in the expense list, view, and edit screens in
both `en` and `es` locales, with a null-safe fallback only for legacy rows
not yet edited.

#### Scenario: Editing any expense requires a category

- GIVEN an existing `Expense` with category `equipment`
- WHEN a user edits it and clears the category field
- THEN the save is rejected until a category is selected

#### Scenario: Legacy uncategorized expense requires a category on save

- GIVEN a legacy `Expense` created before this change with no category
- WHEN a user opens it for edit and saves without picking a category
- THEN the save is rejected by validation

#### Scenario: Legacy uncategorized expense displays without error

- GIVEN a legacy `Expense` with no category
- WHEN it is rendered in the list or view screen
- THEN it displays a null-safe placeholder instead of a translation error

#### Scenario: Category displays as a translated label

- GIVEN an `Expense` with category `electricity`
- WHEN the list, view, or edit screen renders it
- THEN the label appears translated in the active locale (`en` or `es`)

### Requirement: Cross-charge an approved allocation

An `ExpenseAllocation` of a fully `approved` `Expense` MAY be manually
cross-charged: it MUST add a line to a `draft` `Quotation` of the same
project. The action MUST be blocked when the quotation is not CLP or when
the allocation was already charged.

#### Scenario: Allocation charged to a draft CLP quotation

- GIVEN an approved allocation and a draft CLP quotation of the same project
- WHEN the user cross-charges it
- THEN a quotation line is added and the allocation is marked charged

#### Scenario: Non-CLP quotation is blocked

- GIVEN an approved allocation and a draft quotation in another currency
- WHEN the user attempts to cross-charge
- THEN the action is rejected

#### Scenario: Double charge is blocked

- GIVEN an allocation already marked charged
- WHEN the user attempts to cross-charge it again
- THEN the action is rejected

### Requirement: Manage approval level configuration

Only a user with `manage_expense_approval_levels` MAY create, edit, or
delete `ExpenseApprovalLevel` rows. `level` MUST be unique, `minAmount`
MUST strictly increase with `level`, level 1 MUST have `minAmount = 0`, and
the table MUST NOT be left empty. Each level MAY additionally specify one
named `approverUser`; this field is optional and MUST NOT replace the
mandatory `requiredRole`, and MUST NOT affect `level` uniqueness,
`minAmount` monotonicity, or the level-1-zero-amount invariant.
(Previously: levels had no `approverUser` field.)

#### Scenario: Unauthorized user cannot edit levels

- GIVEN a user without `manage_expense_approval_levels`
- WHEN they attempt to edit the level configuration
- THEN the action is denied

#### Scenario: Non-monotonic threshold is rejected

- GIVEN level 2 with `minAmount = 1.000.000`
- WHEN a level 3 is saved with `minAmount = 500.000`
- THEN the save is rejected

#### Scenario: Last remaining level cannot be deleted

- GIVEN a single level-1 row exists
- WHEN a user attempts to delete it
- THEN the deletion is rejected

#### Scenario: Level saved with and without a named approver

- GIVEN an authorized user editing a level
- WHEN they save it with `approverUser` set, and separately save another
  level with `approverUser` left empty
- THEN both saves succeed, and the level with no `approverUser` behaves
  exactly as it did before this change

### Requirement: Expense permission set

The system MUST enforce an `EXPENSES` permission set (view, create, edit,
delete), granted per role, mirroring `QUOTATIONS`.

#### Scenario: View-only user cannot mutate expenses

- GIVEN a user with only view-expense permission
- WHEN they open the expense list
- THEN they can view but cannot create, edit, or delete an expense

### Requirement: Approved expense is immutable

An `Expense` in `approved` state MUST NOT be editable. Corrections MUST be
made by creating a new `Expense`.

#### Scenario: Edit attempt on approved expense is blocked

- GIVEN an approved expense
- WHEN a user attempts to edit its amount or allocations
- THEN the edit is rejected

## Acceptance Criteria

- Allocation sums are capped at 100% always, and exactly 100% to submit.
- `requiredLevels` is computed once at submit and frozen thereafter.
- Approval enforces per-level role, creator exclusion, distinct-approver
  exclusion, and SUPER_ADMIN override; any rejection discards prior levels.
- Recurring generation never duplicates a period.
- Cross-charge is manual, CLP-only, and single-use per allocation.
- Level configuration stays unique, monotonic, level-1-at-zero, non-empty.
- Approved expenses are immutable.
