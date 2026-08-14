# Tasks: Expense Access Scoping

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~300–380 (3 modified prod files: Voter/Repository/Controller; 3 modified test files, no new files — `tests/Repository/ExpenseRepositoryTest.php` already exists, contra design's "Create") |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

Two enforcement points (voter, repository) plus one controller merge, all reusing existing injected services (`RolePermissionManager`, `ExpenseApprovalPolicy`) — no new classes, no migration. Smaller footprint than the `expense-approval-by-person` precedent (no entity/migration/form/template/translations). The 6 touched files are all tightly coupled halves of one IDOR fix, not independently shippable — single work unit.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Full `expense-access-scoping` fix: voter, repository, controller, tests | PR 1 (single) | `phpunit tests/Voter/ExpenseVoterTest.php tests/Repository/ExpenseRepositoryTest.php tests/Controller/ExpenseControllerTest.php` | manual: log in as a non-team ROLE_TEAMLEAD, request `/expense/{id}` for an out-of-team expense in staging, confirm 403 | revert the 3 prod files (Voter/Repository/Controller) + 3 test files; additive filter only, no migration/data impact |

## Phase 1: Voter (`ExpenseVoter::view_expense`)

- [x] 1.1 RED: fix `testUserWithPermissionCanViewADraftExpense` in `tests/Voter/ExpenseVoterTest.php` — a bare subject-less `new Expense()` (no creator, no allocations) for `ROLE_TEAMLEAD` must assert `ACCESS_DENIED`, not `ACCESS_GRANTED` as it currently (incorrectly) does. (Renamed to `testUnrelatedTeamleadCannotViewABareExpenseWithoutAllocationsOrApproval`.)
- [x] 1.2 RED: add creator-can-always-view case — `Expense` with `createdBy` = acting user, allocation on a project the team cannot access — assert `ACCESS_GRANTED`. Traces: "Creator always sees their own expense".
- [x] 1.3 RED: add team-accessible-project case — real `Team`/`Project`/`Customer` fixtures (RolePermissionManager/ExpenseApprovalPolicy are `final`, cannot be mocked; used the real `checkTeamAccessProject()` via entity graph, matching ProjectVoterTest precedent), user is not creator — assert `ACCESS_GRANTED`. Traces: "Team-accessible allocation grants visibility".
- [x] 1.4 RED: add eligible-approver case — team check `false` (off-team project), real `ExpenseApprovalPolicy` driven to `canApprove()=true` via mocked `ExpenseApprovalLevelRepository`/`ExpenseApprovalRepository` (established `assertApprovalVote` pattern), not creator — assert `ACCESS_GRANTED`. Traces: "Approver carve-out grants visibility without a team-project match".
- [x] 1.5 RED: add unrelated-teamlead case — not creator, team check `false`, `canApprove()` `false` — assert `ACCESS_DENIED`. Traces: "Unauthorized direct URL access is denied, not silently filtered" (voter half).
- [x] 1.6 RED: add admin-always-can-view case — `ROLE_ADMIN` with `initCanSeeAllData(true)`, no creator/team/approver match — assert `ACCESS_GRANTED` via `canSeeAllData()` bypass. Traces: "Admin and super-admin see every expense unchanged".
- [x] 1.7 GREEN: implement `src/Voter/ExpenseVoter.php` `view_expense` branch per D1/D2: `canSeeAllData()` → creator (`O(1)`) → loop `$subject->getAllocations()` calling `checkTeamAccessProject()` → `approvalPolicy->canApprove()` → deny. Ran 1.1–1.6 green (18/18 passing).

## Phase 2: Repository (`ExpenseRepository::findForListing()`)

- [x] 2.1 RED: extend `tests/Repository/ExpenseRepositoryTest.php` (existing file, NOT new) — Team/Project/Customer fixture, expense allocated to a team-accessible project, visible via `findForListing($user)`. Traces: "Team-accessible allocation grants visibility".
- [x] 2.2 RED: same file — expense allocated to a non-team project, created by another user, excluded from `findForListing($user)`. Traces: "Unauthorized user is excluded from the list".
- [x] 2.3 RED: same file — expense created by the user but allocated to a non-team project still included (creator branch). Traces: "Creator always sees their own expense".
- [x] 2.4 RED: same file — expense with two allocations (team-accessible P, non-team Q) appears exactly once for a user with access only to P (`->distinct()` pitfall — duplicate-row regression guard). Traces: "Visibility is OR across multiple allocations".
- [x] 2.5 RED: same file — admin user sees expenses regardless of team/allocation/creator. Traces: "Admin and super-admin see every expense unchanged".
- [x] 2.6 GREEN: change `src/Repository/ExpenseRepository.php` signature `findForListing(?string $status = null)` → `findForListing(User $user, ?string $status = null)`; add `leftJoin('e.allocations','ea')->leftJoin('ea.project','p')->leftJoin('p.customer','c')`, OR of team/customer `SIZE()=0`/`isMemberOf()` (mirrors `TimesheetRepository` ~L420–438) plus `e.createdBy = :user`, `->distinct()` per D3. Ran 2.1–2.5 green (10/10 passing). Fixture gotcha found and fixed: Doctrine only tracks owning-side ManyToMany diffs on a `PersistentCollection` — a freshly-persisted `Project`'s `teams` collection stays a plain `ArrayCollection` after its first flush, so `addTeam()` must run *before* the initial persist/flush, not after, or the join-table row is silently never inserted.
- [x] 2.7 Fix pre-existing `testFindForListingFiltersByStatus` (same file, L114) — update call site to pass a `$user` alongside `Expense::STATUS_DRAFT` under the new signature so it stays green.

## Phase 3: Controller merge (`ExpenseController::index()`)

- [x] 3.1 Update `src/Controller/ExpenseController.php` L40 call site — pass `$this->getUser()` as the new required first arg to `findForListing()`.
- [x] 3.2 Per D4: add `ExpenseApprovalPolicy $approvalPolicy` param to `index()`; merge `$repository->findPendingForUser($this->getUser())` filtered through `$approvalPolicy->canApprove($expense, $user)` into the (a)+(c) result set, dedupe by `getId()`, re-sort by `createdAt` DESC. Merge is additionally scoped to skip when an active status filter could never include a pending-approval expense.
- [x] 3.3 Verify `templates/expense/index.html.twig` needs no change — confirmed it iterates `expenses` generically (L16); no template task required.

## Phase 4: Functional IDOR-closure tests (`ExpenseControllerTest.php`)

- [x] 4.1 RED: unauthorized `ROLE_TEAMLEAD` (no team access, not approver, not creator) requests `/expense/{id}` directly — assert `assertResponseStatusCodeSame(403)`, response body contains none of the expense's data. Shaped after the app's existing `assertAccessDenied()` 403 pattern per design D6. Traces: "Unauthorized direct URL access is denied, not silently filtered".
- [x] 4.2 RED: same unauthorized user's `GET /expense` list excludes that expense id. Traces: "Unauthorized user is excluded from the list".
- [x] 4.3 RED: team-accessible, approver-eligible, creator, and admin users each see the expense in both list and `/expense/{id}` detail. Traces: "Team-accessible allocation grants visibility", "Approver carve-out grants visibility without a team-project match", "Creator always sees their own expense", "Admin and super-admin see every expense unchanged".
- [x] 4.4 GREEN: no new production code needed beyond Phases 1–3; ran 4.1–4.3 (18/18 in `ExpenseControllerTest.php`). Two fixture gotchas found and fixed: (1) a freshly-created `User` with no wizard step marked seen gets redirected to `/wizard/intro` by `WizardSubscriber` before reaching any expense route — fixed via `setWizardAsSeen('intro'/'profile')`; (2) unlike a same-process repository test (where `$user->getTeams()` reads the in-memory membership graph directly), a functional/WebTestCase test crosses a real HTTP boundary where Symfony reloads the `User` fresh from DB per request, so `Team::addUser()` on an already-flushed `Team` (in-memory-only membership, never round-tripped) is silently invisible to that reloaded user — fixed by persisting a `TeamMember` join row explicitly.

## Phase 5: Full regression + verification

- [x] 5.1 Run `phpunit tests/Controller/ExpenseControllerTest.php tests/Voter/ExpenseVoterTest.php tests/Repository/ExpenseRepositoryTest.php tests/Form/ExpenseFormTest.php tests/Entity/ExpenseValidationTest.php tests/Command/ExpensesGenerateRecurringCommandTest.php tests/Controller/ExpenseApprovalLevelControllerTest.php` — 76/76 green, zero regression to `expense-allocation`/`expense-approval-by-person`. Additionally ran the FULL expense test surface (all 21 `*Expense*` test files across Command/Controller/Entity/Expense/Form/Repository/Voter, plus `tests/Voter/ExpenseVoterTest.php`) — 146/146 green — and `tests/Controller/QuotationControllerTest.php` (cross-charge integration) — 13/13 green.
- [x] 5.2 Run `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` — exactly 1 pre-existing unrelated error (`QuotationControllerTest::decodeJsonResponse()`), zero new errors. (Fixed 4 new `argument.type` errors surfaced in `ExpenseControllerTest.php` by narrowing `$expense->getId()` with `self::assertIsInt()` before use.)
- [x] 5.3 Confirm no templates/translations touched (design + 3.3 confirm none) — `lint:twig`/`lint:xliff` skipped, confirmed unnecessary via `git diff --stat` (only `.php` files touched). No migration file created (pure query/voter logic change per design D5) — confirmed via `git status --short migrations/` (empty).
