# Proposal: Expense Access Scoping

## Intent

`ExpenseVoter::view_expense` (`src/Voter/ExpenseVoter.php:59-67`) grants
`view_expense` purely by role permission and never inspects the `Expense`
subject. Any `ROLE_TEAMLEAD` user can view any expense by ID
(`/expense/{id}`) — a global IDOR, not even team-scoped. Separately,
`ExpenseRepository::findForListing()` (`src/Repository/ExpenseRepository.php:31-42`)
applies zero creator/team filtering, so the expense list already shows every
user's expenses to any `ROLE_TEAMLEAD`. This is the same class of bug as an
IDOR already fixed this session for activity-workspace, now confirmed in
Expense.

Fix scope-of-visibility to match the PO's locked three-tier model
(`ROLE_USER` → own only; `ROLE_TEAMLEAD` → team-assigned projects; admin →
everything), with an explicit approver carve-out so the existing approval
workflow keeps working.

## Locked Decisions

| # | Decision |
|---|----------|
| D1 | `ROLE_ADMIN`/`ROLE_SUPER_ADMIN` (`User::canSeeAllData()`) see every expense — unchanged. |
| D2 | Otherwise, visible iff: (a) the expense has an `ExpenseAllocation` whose `Project` is team-accessible per `ProjectRepository::getPermissionCriteria()`'s exact team-join logic (reused, not reimplemented), OR (b) the user is an eligible approver per `ExpenseApprovalPolicy` (reused, not reimplemented), OR (c) the user created the expense (`createdBy`). |
| D3 | `Expense` has no direct `Project` — visibility for (a) joins through `Expense.allocations[].project` (one-to-many); an expense with any allocation on a team-visible project is visible. |
| D4 | Timesheet is untouched — already correct (team-scoped via `ProjectRepository`). |
| D5 | Invoice is untouched — exploration found it already team/customer-scoped (`InvoiceRepository::addPermissionCriteria()`, `InvoiceVoter::checkTeamAccessCustomer()`), which is close enough to the locked model that it is not treated as a bug here. Flagged as a SUGGESTION only: a short follow-up verification pass (not a full SDD cycle) could confirm this with a live test if the PO wants certainty. |
| D6 | `findPendingForUser()` (approval queue) is unaffected — it is a different, already-correct query, not a visibility gate. |

## Scope

### In Scope
- `ExpenseVoter::view_expense`: check the `Expense` subject per D2 instead of role-only.
- `ExpenseRepository::findForListing()`: add the same D2 filter (team-via-allocations OR approver-eligible OR creator), scoped by the requesting user; admin bypass per D1.
- Decide and implement filtering mechanism: either inline QueryBuilder joins in `findForListing()`, or introduce a minimal `ExpenseQuery`-style object mirroring the Timesheet/Invoice convention. Tradeoff: inline is smaller/contained; a Query object matches codebase convention but adds new plumbing (no `ExpenseQuery` exists today). Leaning inline given the narrow, single-caller scope — confirm in design.
- Tests: team-visible project shows, non-team project hidden, own expense always visible even off-team, eligible approver sees others' pending expenses regardless of project, non-approver/non-team/non-creator gets 403 on detail and is excluded from the list, admin bypass unchanged.

### Out of Scope
- Timesheet — do not touch (D4).
- Invoice — no code change (D5); note only.
- Approval decision logic itself (`ExpenseApprovalPolicy::canDecide()`) — reused as-is, not modified.
- New admin screens or UI beyond what the corrected list/detail visibility implies.
- `edit_expense`, `delete_expense`, `charge_expense`, `approve_expense`, `reject_expense` voter branches — unchanged; only `view_expense` is in scope.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `expense-allocation`: new requirement — expense visibility (list and
  detail) MUST be limited to team-assigned-project scope, eligible-approver
  scope, or the creator, for non-admin users; admins see all (D1-D3).

## Approach

Reuse two proven mechanisms instead of inventing scoping logic:
`ProjectRepository::getPermissionCriteria()`'s team-join pattern (already the
source of truth for Timesheet's correct behavior) for the project-team
branch, and `ExpenseApprovalPolicy` for the approver branch. The voter adds
one subject check before returning true; the repository adds a join through
`allocations.project` plus the same OR conditions, parameterized by the
current user.

## Affected Areas

| Area | Impact | Description |
|------|--------|--------------|
| `src/Voter/ExpenseVoter.php` | Modified | `view_expense` becomes subject-aware (D2) |
| `src/Repository/ExpenseRepository.php` | Modified | `findForListing()` filtered by D2 |
| `src/Controller/ExpenseController.php` | Modified | pass current user into `findForListing()` |
| `tests/` | New | voter + repository scoping coverage |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Filter breaks approver's ability to view/decide expenses outside their team | Med | Approver OR-branch (D2b) reuses `ExpenseApprovalPolicy` untouched |
| Join through `allocations[].project` under-matches expenses with no allocations yet (drafts) | Low | Creator branch (D2c) always covers own drafts regardless of allocation state |
| Invoice left unfixed if PO actually wanted stricter behavior | Low | Explicitly flagged (D5); no code risk, only a documentation gap pending confirmation |

## Rollback Plan
Revert voter and repository changes; both are additive filters with no
migration or entity change, so reverting restores prior (buggy but
functionally identical) behavior with no data impact.

## Dependencies
`ProjectRepository::getPermissionCriteria()`, `ExpenseApprovalPolicy` (both reused, unmodified).

## Success Criteria
- [ ] `ROLE_TEAMLEAD` cannot view/list an expense outside their team's projects unless they created it or are an eligible approver.
- [ ] Eligible approvers still see and decide on out-of-team expenses.
- [ ] Own expenses always visible regardless of project-team scope.
- [ ] Admin/super-admin behavior unchanged.
- [ ] Timesheet and Invoice code untouched.

## Open Items (non-blocking)
- SUGGESTION only (D5): confirm Invoice's existing team/customer scoping meets the PO's expectation with a short live-test verification pass, if desired. Not required to proceed with this change.
