# Delta for Expense Allocation

## MODIFIED Requirements

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
