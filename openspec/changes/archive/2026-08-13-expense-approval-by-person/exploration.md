# Exploration: expense-approval-by-person

## Current State

`ExpenseApprovalLevel` (`src/Entity/ExpenseApprovalLevel.php`) has exactly three fields: `level` (int, unique), `minAmount` (int CLP), `requiredRole` (string, validated against `RoleService::getAvailableNames()`). No person/approver field exists today.

`ExpenseApprovalPolicy::canDecide()` (`src/Expense/ExpenseApprovalPolicy.php`) resolves authorization in this exact order: (1) no pending level → `false`; (2) creator === approver → `false`; (3) `hasUserApprovedAnyLevel` (four-eyes, repository-wide not per-level) → `false`; (4) `isSuperAdmin()` → `true` unconditional break-glass; (5) `$user->hasRole($level->getRequiredRole())` — the ONLY positive path today.

`ApprovalLevelResolver` maps amount → required levels via `minAmount` only; never touches `requiredRole` — confirmed orthogonal.

**Original design intent** (verified against `openspec/changes/archive/2026-08-13-expense-allocation/proposal.md`/`design.md`): Business Rule #9 states explicitly "Each approval level is bound to a system role, not to named users." "Named-user approvers, delegation..." is explicitly listed Out of Scope. The rationale (D1 + Risk #1 in proposal.md) is turnover/vacation resilience, mitigated today by (a) a config-screen warning when a role has zero active users, and (b) `ROLE_SUPER_ADMIN` break-glass. Any new proposal must explicitly re-litigate this tradeoff.

## Affected Areas

- `src/Entity/ExpenseApprovalLevel.php` — add nullable `approverUser` (`ManyToOne User`). `validateLevelOneMinAmount` is orthogonal (only inspects `level`/`minAmount`) — no change needed.
- `src/Expense/ExpenseApprovalPolicy.php` — `canDecide()`'s final `hasRole()` line is the one branch point requiring change.
- `src/Form/ExpenseApprovalLevelForm.php` — add 4th field via existing `UserType::class` widget.
- `src/Controller/ExpenseApprovalLevelController.php` — no change; `isMonotonic()` is keyed only on `level`/`minAmount`.
- `templates/expense_approval_level/edit.html.twig` — likely no change (generic `form_widget(form)`).
- `templates/expense_approval_level/index.html.twig` — needs a new column; currently a hardcoded 4-column table built around `widgets.label_role(level.requiredRole)`.
- `src/Repository/ExpenseApprovalLevelRepository.php` — no change; field-agnostic.
- `migrations/` — new migration adding nullable `approver_user_id` + index + FK.
- `translations/messages.*.xlf` — new label key(s).
- No change needed in `ApprovalLevelResolver`, `ExpenseApprovalService`, `ExpenseVoter`, `Expense`/`ExpenseAllocation` — blast radius is fully contained to the level entity/form/policy/admin screens.

## Investigation Answers

**Q1 — Reusable widget**: Yes. `App\Form\Type\UserType` (`src/Form/Type/UserType.php`, extends `EntityType`) is the established single-user picker. Near-exact precedent: `User::$supervisor` (`src/Entity/User.php:253`, nullable `ManyToOne User`, `onDelete: 'SET NULL'`) wired in `src/Form/UserEditType.php:101` as `->add('supervisor', UserType::class, ['required' => false, ...])`. Reuse `UserType::class`, not `UserRoleType`.

**Q2 — Replace vs. additional**: Two shapes evaluated; recommend **replace-when-set with mandatory role fallback stored on the entity** (not OR-semantics) — matches the PO's literal wording ("que no sea el líder del proyecto" implies exclusion, not addition), and keeping `requiredRole` non-nullable preserves a graceful fallback target for user deletion (Q5). OR-semantics is flagged as a real behavioral fork to confirm explicitly with the PO, not silently default.

**Q3 — Four-eyes logic**: Confirmed orthogonal — creator/four-eyes checks are structurally blind to `requiredRole`/approver concept. Edge case: if `approverUser` equals the expense's creator, that level becomes uncompletable by that person (same stall category as "role has zero active users").

**Q4 — Concrete touch points**: `ExpenseApprovalLevelForm.php` add `->add('approverUser', UserType::class, ['required' => false, ...])`; `index.html.twig` add a column showing `level.approverUser.displayName` when set, else the existing role badge; translations for the new label.

**Q5 — Migration/deletion**: This project has an established, consistent `ON DELETE SET NULL` convention for every `User` FK (`created_by_id`, `approved_by_id`, `source_expense_id`, `supervisor_id`). No `RESTRICT` precedent exists anywhere for a `User` FK. Recommend `approver_user_id INT DEFAULT NULL` + indexed FK `ON DELETE SET NULL` — deleting the user gracefully falls back to the stored role, which is exactly why `requiredRole` must stay mandatory (Q2).

**Q6 — `validateLevelOneMinAmount`**: Confirmed orthogonal — the callback body only reads `level`/`minAmount`; the controller's `isMonotonic()` cross-row check is likewise keyed only on those two fields. No change needed.

## Approaches

| Approach | Pros | Cons | Effort |
|---|---|---|---|
| 1. Optional `approverUser` FK, replace-semantics (recommended) | Minimal additive schema; zero blast radius outside the level entity/policy/admin UI; reuses `UserType` + existing `SET NULL` convention; backward compatible (`null` = today's behavior); graceful degradation on user deletion | Two auth paths to test; needs explicit OR-vs-replace decision; needs explicit mid-flight/snapshot decision (level's approver rule isn't snapshotted at submit today, unlike `requiredLevels`) | Low–Medium |
| 2. OR-semantics (named person ADDITIONAL to role) | Strictly additive, never narrows approvers vs. today | Contradicts PO's literal ask ("not the role"); can't lock a level to exactly one person | Low–Medium |
| 3. Drop `requiredRole` entirely, all-named-users | None over Approach 1 for this ask | Destroys original scalability rationale for every level, not just the one the PO is looking at; breaking migration | High |

## Recommendation

Approach 1 — optional `approverUser` FK, replace-semantics when set, falls back to the stored `requiredRole` when null. Two forks must be confirmed with the PO before `sdd-propose` locks the design (not defaulted silently): (a) replace vs. OR semantics, (b) whether the effective approver rule should be snapshotted at `submitForApproval()` time the way `requiredLevels` already is.

## Risks

- No snapshot of a level's approver rule at submit time — an admin changing `approverUser` mid-flight retroactively changes who can decide a `pending_approval` expense's current level, with no audit trail of the switch.
- Named approver could coincide with the expense's own creator, or become disabled, stalling that level (same category as "role has zero active users" risk — same break-glass mitigation should be reused).
- Q2 (replace vs. OR) is an unresolved behavioral fork; defaulting the wrong way risks rework.
- `UserType`'s `include_disabled: false` default blocks picking a disabled user as a NEW approver, but doesn't auto-clear an already-assigned user who later becomes disabled — pre-existing gap in `canDecide()` today (doesn't check `enabled` for the role path either either), not introduced by this change.

## Ready for Proposal

Yes, with the two flagged decisions surfaced explicitly to the user rather than defaulted.
