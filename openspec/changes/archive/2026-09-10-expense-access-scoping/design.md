# Design: Expense Access Scoping

## Technical Approach

Two independent enforcement points, both reusing existing services with zero
new dependencies: `ExpenseVoter::view_expense` becomes subject-aware using
services it **already has injected** (`RolePermissionManager`,
`ExpenseApprovalPolicy`); `ExpenseRepository::findForListing()` gets a
QueryBuilder filter for branches (a)+(c), with branch (b) merged in from the
existing `findPendingForUser()` query, filtered through
`ExpenseApprovalPolicy::canApprove()`. No new classes, no `ExpenseQuery`
object, no migration.

## Architecture Decisions

| # | Decision | Choice | Rejected | Rationale |
|---|---|---|---|---|
| D1 | Voter mechanism | In-memory check over `$expense->getAllocations()` using `RolePermissionManager::checkTeamAccessProject(Project, User): bool` (existing method, already the exact in-memory SIZE=0/isMemberOf-per-team semantics, already admin-aware) | Delegating to a repository DQL lookup from the voter | `ExpenseVoter` already has a hydrated `Expense` with allocations. `ProjectVoter::voteOnAttribute` (the `access` branch) already does exactly this shape — `checkTeamAccessProject` is the established in-memory precedent, not `ProjectRepository::getPermissionCriteria()` (private, DQL/QB-bound). Zero new voter dependency. |
| D2 | Voter check order | `canSeeAllData()` → creator (c, O(1)) → team-via-allocations loop (a) → `approvalPolicy->canApprove()` (b, DB-backed, most expensive) → deny | Checking (b) first | Cheapest/most-common paths short-circuit first; admin and self-view never touch the DB. |
| D3 | Repository mechanism | QueryBuilder `leftJoin(e.allocations, ea)->leftJoin(ea.project, p)->leftJoin(p.customer, c)`, OR of (team-SIZE/isMemberOf AND customer-SIZE/isMemberOf) with `e.createdBy = :user`, `->distinct()` | Calling `ProjectRepository::getPermissionCriteria()` directly | That method is `private`. `TimesheetRepository` already duplicates this exact DQL block privately (lines 405-447) rather than sharing it cross-repository — this is the established codebase convention (QB criteria are alias-bound, not portable). Mirrors, does not literally reuse, the block — corrects proposal wording. |
| D4 | Approver branch (b) merge | Controller-layer merge: `findForListing()` handles (a)+(c) only; controller merges `findPendingForUser($user)` filtered by `canApprove()`, dedupe by id, re-sort by `createdAt` DESC | Constructor-injecting `ExpenseApprovalPolicy` into `ExpenseRepository` | `ExpenseRepository` is registered via `factory: ['@doctrine.orm.entity_manager', getRepository]` (`services.yaml:285-288`) — this factory only accepts the entity class name, so constructor DI of any service is not possible without changing the registration pattern. `findPendingForUser()` is the existing precedent for a narrow-SQL-then-PHP-filter shape; `canApprove()` is reused unmodified, matching D2(b) of the proposal ("reused, not reimplemented"). |
| D5 | Shared voter/repository helper | None — no `getAccessibleProjectIds()` | A shared helper method | The two enforcement points operate at different levels by design (in-memory single-entity vs. bulk DQL) and reuse different existing services (`checkTeamAccessProject` vs. a QB fragment). `TimesheetRepository`'s own duplicate of `ProjectRepository`'s block confirms per-layer duplication, not a shared helper, is this codebase's norm. |
| D6 | 403 vs 404 | Keep `#[IsGranted('view_expense', 'expense')]` — Symfony's `AccessDeniedException` (403) once the voter denies | `createNotFoundException()` (404), per the `activity-workspace` precedent | That precedent guards a **route-parameter mismatch** (child id doesn't belong to the parent id in the URL), checked before any permission gate. `/expense/{id}` has no such parent/child ambiguity — this is a plain single-subject voter denial, identical in shape to the app's existing `edit_expense`/`delete_expense` denials, which already surface as 403. No controller code changes beyond the voter. |

## Data Flow

    GET /expense/{id} ──IsGranted('view_expense', expense)──> ExpenseVoter
        admin? ──yes──> grant
        creator? ──yes──> grant
        any allocation.project team-accessible (RolePermissionManager)? ──yes──> grant
        approver-eligible (ExpenseApprovalPolicy::canApprove)? ──yes──> grant
        else ──> 403 AccessDeniedException

    GET /expense ──> ExpenseRepository::findForListing(user, status)  [a + c, QB]
                  ──> merge with findPendingForUser(user) filtered by canApprove()  [b, PHP]
                  ──> dedupe by id, sort by createdAt DESC

## File Changes

| File | Action | Description |
|---|---|---|
| `src/Voter/ExpenseVoter.php` | Modify | `view_expense` branch: subject-aware per D1/D2 |
| `src/Repository/ExpenseRepository.php` | Modify | `findForListing(User $user, ?string $status = null)`: QB filter per D3 |
| `src/Controller/ExpenseController.php` | Modify | `index()`: pass `$this->getUser()`, inject `ExpenseApprovalPolicy`, merge per D4 |
| `tests/Voter/ExpenseVoterTest.php` | Modify | Fix `testUserWithPermissionCanViewADraftExpense` (currently asserts GRANTED for a bare `Expense()` — now correctly DENIED unless creator/team/approver); add new scoping cases |
| `tests/Repository/ExpenseRepositoryTest.php` | Create | Kernel test for D3 filter |
| `tests/Controller/ExpenseControllerTest.php` | Modify | 403 on out-of-scope `/expense/{id}`; list excludes it |

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit | Voter: team-visible/off-team/creator/approver/admin combinations | `ExpenseVoterTest`, mocked `RolePermissionManager`/`ExpenseApprovalPolicy` per existing pattern |
| Integration | `findForListing()`: team match, own off-team, admin-all, excludes stranger | `KernelTestCase` + `Team`/`Project`/`Customer`/`Expense`/`ExpenseAllocation` fixtures |
| Functional | Direct-URL IDOR closed (403, not 404); list omits the same row | `ExpenseControllerTest`, shape follows `ActivityWorkspaceControllerTest`'s cross-project guard test, asserting 403 per D6 |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file
classification, or process-integration boundary. The one adversarial
boundary is HTTP authorization (IDOR on an enumerable `/expense/{id}`),
closed by D1/D2 (voter) and D3/D4 (listing), covered by the functional RED
test above.

## Migration / Rollout

None. Pure voter and query-logic change; no entity, column, or index change.

## Open Questions

None blocking. D5 (Invoice follow-up verification) remains a proposal-level
suggestion, not a design blocker.
