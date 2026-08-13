# Proposal: Expense Approval By Person

## Intent

The "Editar nivel de aprobación" admin screen can only bind an approval level
to a system role (`requiredRole`, e.g. "Líder de equipo"). There is no way to
say "this specific person clears this level". The product owner hit this while
configuring the level table: some levels have a real named owner, and the role
abstraction cannot express it.

The archived `expense-allocation` proposal deliberately excluded this
(Business Rule #9: "Each approval level is bound to a system role, not to
named users"; "Named-user approvers" listed Out of Scope). That tradeoff is
re-litigated here and **partially reversed, not discarded**: the role binding
stays mandatory and keeps its turnover/vacation resilience, and a named person
is added as an *additional* approver on top of it.

The product owner's framing, as stated:

> "rol o persona" — either the named person or anyone holding the required
> role can decide that level.

## Naming

`ExpenseApprovalLevel.approverUser`, a nullable `ManyToOne` to `User` on the
existing `gppro_expense_approval_levels` table (column `approver_user_id`,
indexed, FK `ON DELETE SET NULL`). No new entity, no new table, no new
namespace, no new permission — the existing
`manage_expense_approval_levels` permission already guards this screen.

The FK shape and the form widget are not invented here: `User::$supervisor`
(`src/Entity/User.php:253`, nullable `ManyToOne User`, `onDelete: 'SET NULL'`,
wired in `src/Form/UserEditType.php:101` as `UserType::class` with
`required: false`) is the near-exact precedent. `SET NULL` is also the only
convention this project uses for a `User` FK (`created_by_id`,
`approved_by_id`, `supervisor_id`); no `RESTRICT` precedent exists.

## Business Rules

| # | Rule |
|---|------|
| 1 | An approval level MAY additionally name one specific approver user |
| 2 | `requiredRole` stays mandatory on every level; the named approver never replaces it |
| 3 | OR-semantics: a level is cleared by the named approver **or** by anyone holding `requiredRole` |
| 4 | A level with no named approver behaves exactly as it does today |
| 5 | The approver rule is not snapshotted; the current configuration decides who may clear a pending level, at decision time |
| 6 | Editing a level's `approverUser` or `requiredRole` applies live to expenses already `pending_approval` at that level |
| 7 | If the named approver is deleted, the level silently falls back to `requiredRole` alone |
| 8 | Four-eyes rules are unchanged: the creator still cannot approve their own expense, and one user still cannot clear two levels of the same expense, regardless of being named |
| 9 | `ROLE_SUPER_ADMIN` break-glass is unchanged |
| 10 | Amount thresholds (`minAmount`), monotonicity, and required-level counting are untouched |

## Approver Resolution (decision)

**OR, not replace.** `ExpenseApprovalPolicy::canDecide()` gains one additional
positive branch alongside — not instead of — the existing `hasRole()` check.
Resolution order becomes: (1) no pending level → deny; (2) creator ===
approver → deny; (3) already cleared another level of this expense → deny;
(4) `ROLE_SUPER_ADMIN` → allow; (5) **user === level's `approverUser`** →
allow; (6) `user->hasRole($level->getRequiredRole())` → allow.

Branches 1–3 are negative gates and stay *above* the new branch, so a named
approver never bypasses four-eyes or the creator exclusion.

Replace-semantics ("only the named person") was rejected by the PO. It would
have narrowed the approver pool below today's behavior, re-creating the exact
single-point-of-failure the original design avoided.

**No submit-time snapshot.** `requiredRole` is already not snapshotted today —
only `requiredLevels` (the *count*) is frozen at `submitForApproval()`.
Snapshotting `approverUser` would make the new field asymmetric with the
existing one it sits next to, and would require a second frozen structure per
expense. Configuration changes therefore apply live to in-flight expenses,
exactly as a `requiredRole` edit does today.

**Graceful degradation is a property of A1 + A2 together, not an accident.**
Because there is no snapshot (A2) and both paths are live (A1), any failure of
the named-approver path — user deleted (`SET NULL`), user disabled, user
happens to be the expense's own creator, user already cleared a lower level —
degrades to "anyone with `requiredRole`" instead of stalling the expense.
This only holds while `requiredRole` remains mandatory, which is why Rule 2 is
non-negotiable and why replace-semantics was the riskier fork.

## Working Assumptions (confirm in spec/design)

| # | Assumption |
|---|------------|
| A1 | **OR-semantics (PO decision, locked).** When a level has both `requiredRole` and `approverUser`, either the named person or any holder of the role may decide that level. Not "only the named person" |
| A2 | **No snapshot (PO decision, locked).** The effective approver rule is read live at decision time. An admin editing `approverUser` or `requiredRole` while an expense sits `pending_approval` at that level changes who may decide it, retroactively. This mirrors today's `requiredRole` behavior — symmetric, not a new exception |
| A3 | `approverUser` is a nullable `ManyToOne User` with `onDelete: 'SET NULL'`, reusing the `User::$supervisor` convention. No new deletion-handling policy is invented |
| A4 | `requiredRole` stays non-nullable. `approverUser` is purely additive: `null` reproduces today's behavior byte for byte |
| A5 | The form field is `UserType::class` with `required: false` — the established single-user picker, not `UserRoleType` and not a new widget |
| A6 | Four-eyes / creator-cannot-approve-own logic is unchanged and orthogonal (verified: those checks are structurally blind to the role/approver concept) |
| A7 | `validateLevelOneMinAmount` and the controller's `isMonotonic()` cross-row check are unchanged and orthogonal (verified: both read only `level` and `minAmount`) |
| A8 | Migration is a single additive nullable column plus index and FK; no data transformation, no backfill |

Assumptions follow this project's convention: locked here so implementation is
unambiguous, superseded by an explicit PO correction if one arrives.

## Scope

### In Scope
- Nullable `approverUser` (`ManyToOne User`, `onDelete: 'SET NULL'`) on
  `src/Entity/ExpenseApprovalLevel.php` with accessors.
- Migration adding `approver_user_id INT DEFAULT NULL`, its index, and the
  `ON DELETE SET NULL` FK to `gppro_expense_approval_levels`.
- One additional positive branch in `ExpenseApprovalPolicy::canDecide()`
  (named-approver match), placed after the negative gates.
- `approverUser` field on `src/Form/ExpenseApprovalLevelForm.php` via
  `UserType::class`, `required: false`.
- New column in `templates/expense_approval_level/index.html.twig` showing
  the named approver when set, alongside the existing role badge.
- Label translation keys in `translations/messages.en.xlf` and
  `messages.es.xlf`.
- Test coverage for: OR-semantics (role path still works with a named
  approver set; named approver clears without holding the role), null
  `approverUser` reproducing today's behavior, four-eyes still blocking a
  named approver who is the creator, and `SET NULL` fallback on user deletion.

### Out of Scope
- **Submit-time snapshotting of the approver rule** — explicitly excluded by
  A2; `requiredLevels` remains the only frozen approval value.
- **Bulk or delegated approver assignment across multiple levels at once** —
  levels are edited one row at a time on the existing screen.
- Any change to `src/Expense/ExpenseApprovalService.php`,
  `ApprovalLevelResolver`, `src/Voter/ExpenseVoter.php`, `src/Entity/Expense.php`,
  or `src/Entity/ExpenseAllocation.php`.
- Replace-semantics ("only the named person can decide") — rejected by A1.
- Multiple named approvers per level, approver groups, or ordered fallbacks.
- Out-of-office substitution, delegation, escalation SLAs, or approval
  notifications — still out of scope from the archived proposal.
- Auto-clearing or warning when an assigned `approverUser` later becomes
  disabled (pre-existing gap: `canDecide()` does not check `enabled` on the
  role path either; not introduced by this change).
- An audit trail of approver-configuration edits.
- Per-project, per-customer, or per-category approval level configuration.
- Any change to amount thresholds, level monotonicity, cross-charge,
  recurrence, or the `EXPENSES` permission set.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `expense-allocation`: requirement **"Approve each level"** changes — a level
  MUST be clearable by its named `approverUser` **or** by a user holding its
  `requiredRole` (currently role-only), with the creator-exclusion and
  distinct-approver gates unchanged. Requirement **"Manage approval level
  configuration"** gains an optional `approverUser` field on the level row;
  its existing `level`/`minAmount` invariants are unchanged.

## Approach

Follow the `User::$supervisor` precedent end to end: nullable `ManyToOne` with
`onDelete: 'SET NULL'` on the entity, `UserType::class` with `required: false`
in the form, and a single additive migration. `ExpenseApprovalPolicy` gets one
new positive branch inserted between the `ROLE_SUPER_ADMIN` break-glass and
the existing `hasRole()` check — the negative gates above it are untouched, so
the change cannot widen who bypasses four-eyes. The index template gains one
column rendering the approver's display name when set. Blast radius stays
contained to entity + form + policy + admin templates + migration +
translations; nothing in the approval service, resolver, voter, or expense
entities is touched.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/Entity/ExpenseApprovalLevel.php` | Modified | Nullable `approverUser` `ManyToOne User`, `onDelete: 'SET NULL'`, accessors |
| `migrations/Version*.php` | New | `approver_user_id INT DEFAULT NULL` + index + FK `ON DELETE SET NULL` |
| `src/Expense/ExpenseApprovalPolicy.php` | Modified | One additional positive branch in `canDecide()` (named-approver match) |
| `src/Form/ExpenseApprovalLevelForm.php` | Modified | `approverUser` via `UserType::class`, `required: false` |
| `templates/expense_approval_level/index.html.twig` | Modified | New approver column beside the role badge |
| `templates/expense_approval_level/edit.html.twig` | Unchanged | Generic `form_widget(form)` renders the new field |
| `translations/messages.en.xlf`, `messages.es.xlf` | Modified | Approver label key(s) |
| `tests/` | Modified/New | OR-semantics, null-approver parity, four-eyes, `SET NULL` fallback |
| `src/Controller/ExpenseApprovalLevelController.php` | Unchanged | `isMonotonic()` reads only `level`/`minAmount` (A7) |
| `src/Repository/ExpenseApprovalLevelRepository.php` | Unchanged | Field-agnostic |
| `src/Expense/ExpenseApprovalService.php`, `ApprovalLevelResolver`, `src/Voter/ExpenseVoter.php`, `src/Entity/Expense.php`, `src/Entity/ExpenseAllocation.php` | Unchanged | Out of scope by design |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Admin edits `approverUser` mid-flight, retroactively changing who may decide a `pending_approval` expense, with no audit of the switch | Med | Accepted by A2 as symmetric with today's `requiredRole` behavior; `ExpenseApproval` still audits who actually decided; a configuration audit trail is a separate, deferred change |
| Named approver is deleted or disabled, or is the expense's own creator, stalling that level | Low | Cannot stall: `requiredRole` stays mandatory (Rule 2) and OR-semantics keeps the role path live, so the level degrades gracefully to role-only; `ROLE_SUPER_ADMIN` break-glass remains |
| Reviewers read "asignar persona" as "only that person can approve" | Med | A1 is the locked decision and must be stated in the spec's scenarios and in the screen's help text; the spec MUST include a scenario where a role holder clears a level that has a different named approver |
| New positive branch accidentally placed above the creator/four-eyes gates, letting a named approver approve their own expense | Med | Branch ordering is fixed in the decision section; an explicit test asserts a named approver who is also the creator is denied |
| `UserType`'s `include_disabled: false` prevents picking a disabled user but does not clear an already-assigned one who is later disabled | Low | Pre-existing gap (the role path does not check `enabled` either); explicitly out of scope, and neutralized in practice by the role-path fallback |
| Scope creep into delegation, substitution, or approver groups | Med | Explicitly out of scope; this change adds exactly one optional person per level |

## Rollback Plan

Revert the migration (drops `approver_user_id`, its index, and its FK) and
revert the entity, policy, form, template, and translation changes. The column
is nullable and additive, so no existing level row is transformed and no
expense data changes. With the column absent — or simply left `NULL` —
`canDecide()` reduces to exactly today's role-only path, so approval behavior
is identical before and after either direction of the change. No existing
table other than `gppro_expense_approval_levels` is altered.

## Dependencies

- Existing `ExpenseApprovalLevel`, `ExpenseApprovalPolicy`,
  `ExpenseApprovalLevelForm`, `App\Form\Type\UserType`, `User`, and the
  `manage_expense_approval_levels` permission (reused, not extended).

## Success Criteria

- [ ] A level can be saved with a named approver and with none; a level with none behaves exactly as before this change.
- [ ] A user who is the level's `approverUser` but holds none of the required roles can clear that level.
- [ ] A user holding the level's `requiredRole` can still clear it even when a *different* user is named as `approverUser` (OR-semantics, not replace).
- [ ] A user who is neither the named approver nor a role holder is still denied.
- [ ] The expense creator is denied even when they are the level's named `approverUser`.
- [ ] A user who already cleared a lower level is denied a second level even when named on it.
- [ ] `ROLE_SUPER_ADMIN` break-glass still clears any level regardless of the named approver.
- [ ] Changing a level's `approverUser` while an expense is `pending_approval` at that level immediately changes who may decide it (A2, no snapshot).
- [ ] Deleting a user assigned as `approverUser` sets the column to `NULL` and the level remains approvable by any `requiredRole` holder.
- [ ] `requiredLevels`, `minAmount` thresholds, monotonicity validation, recurrence, and cross-charge behave identically to before.
- [ ] The named approver is visible in the level list and editable on the level edit screen, in both `en` and `es`.
