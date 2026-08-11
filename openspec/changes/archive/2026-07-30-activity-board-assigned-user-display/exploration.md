## Exploration: ActivityBoard assigned-user display

### Current State

The ActivityBoard flow is a server-rendered Symfony/Twig feature. `ActivityBoardController::boardAction()` asks `ActivityBoardService::createBoard()` for project-scoped columns. The service combines visible, non-global activities with persisted `ActivityBoardState` records and wraps each pair in an `ActivityBoardCard`. The card exposes `assignedTo`, `technicalUser`, and `functionalUser` through the state entity.

`templates/project/_board_card.html.twig` currently includes the activity name and technical/functional users in the card's `data-search` metadata. It renders technical and functional user avatars, but does not render `card.assignedTo`. Its fallback condition checks only technical and functional users, so a card with only an assigned user incorrectly displays the translated `Unassigned` label. The existing controller regression test already creates a persisted `assignedTo` user and expects that user's display name in the rendered card (`tests/Controller/ActivityBoardControllerTest.php:130-160`); this expectation currently fails because the template omits the assigned user.

The board's client-side search consumes the server-rendered `data-search` value (`assets/js/widgets/GpproActivityBoard.js`), so assigned-user searchability belongs in the template metadata rather than in a separate client-side lookup. The state relation is nullable and uses `SET NULL`, while the service already persists and validates `assignedTo`; no schema or controller/API change is needed for display behavior.

### Product Problem and Scope

Users cannot see who is assigned to an ActivityBoard card, and cannot find a card by its assigned user's name. The UI also reports `Unassigned` when an assigned user exists but no technical or functional user is set. This is a presentation and search-metadata defect in the separate product change `activity-board-assigned-user-display`.

In scope:

- Include `card.assignedTo.displayName` in the card's search metadata when an assigned user exists.
- Render the assigned user's display name in the card using the existing board/card presentation patterns.
- Show `Unassigned` only when `assignedTo`, `technicalUser`, and `functionalUser` are all absent.
- Preserve existing technical/functional role rendering and translation behavior.
- Add or strengthen focused regression coverage for assigned-user rendering/search metadata and the three-user-null fallback rule.

Out of scope:

- Changes to assignment authorization, persistence, DTO parsing, routes, controller actions, or the ActivityBoard service.
- Changes to the ActivityBoard database schema or migrations.
- Changes to card movement, status, priority, due-date, or drag-and-drop behavior.
- Changes to the meaning, translation, or permissions of technical and functional users.
- Changes to baseline measurement/documentation artifacts; `gppro-codebase-analysis` remains documentation/measurement-only.
- Deployment, production data repair, or changes under `var/plugins/`.

### Affected Areas

- `templates/project/_board_card.html.twig` — primary presentation fix: add assigned-user search/display output and correct the fallback condition.
- `tests/Controller/ActivityBoardControllerTest.php` — existing failing regression assertion and likely additional coverage for metadata/fallback behavior.
- `src/Activity/ActivityBoardCard.php` — existing read-only card accessors already expose the required `assignedTo` relation; implementation should reuse them rather than add a new abstraction.
- `src/Entity/ActivityBoardState.php` — existing nullable `assignedTo` relation defines the source of truth and null semantics; no change is expected.
- `src/Activity/ActivityBoardService.php` — existing board construction and assignment persistence provide the populated state; inspect during design/apply to ensure no service change is accidentally introduced.
- `assets/js/widgets/GpproActivityBoard.js` — consumes `data-search`; verify that server-side inclusion is sufficient and preserve the existing client-side matching contract.
- `assets/sass/board.scss` — existing card assignee/role styles may be reused; only change if the established markup requires a minimal presentation adjustment.
- `tests/DataFixtures/ActivityFixtures.php` — existing generic activity fixture flow can support broader tests, but a test-local persisted board state is likely clearer for this regression.

### Approaches

1. **Minimal template and controller regression coverage** — update `_board_card.html.twig` to merge the assigned user's display name into `searchNames`, render the assigned user alongside the existing role users, and make the fallback require all three user relations to be null; retain the current persisted-state test and add a focused metadata/fallback assertion if needed.
   - Pros: smallest blast radius; uses existing `ActivityBoardCard` accessors, Twig translation/macros, and client search contract; no schema or service change; directly addresses the observed failure.
   - Cons: exact visual placement must follow existing board conventions; HTML-level assertions may be somewhat coupled to markup.
   - Effort: Low

2. **Introduce a dedicated presentation/view-model layer for card assignees** — normalize assigned, technical, and functional users into a new DTO/view model before Twig rendering.
   - Pros: could centralize future card presentation rules and reduce Twig conditionals.
   - Cons: unnecessary abstraction for one nullable relation; expands change surface across controller/service/tests; risks duplicating existing `ActivityBoardCard` behavior and exceeding the bounded defect scope.
   - Effort: Medium

### Compatibility Considerations

- No persisted data, database schema, route, API response, authorization rule, or service contract changes are required.
- Existing cards with only technical or functional users must continue to show their role avatars and must not show `Unassigned`.
- Stateless cards with no users must continue to show `Unassigned`.
- Cards with an assigned user must display that user's `displayName`, must be searchable by that name, and must not show `Unassigned` merely because the other two user roles are empty.
- Twig's normal escaping should be preserved for display text and `data-search` output; do not introduce raw HTML or client-side interpolation.
- The assigned user may be null after deletion because the relation uses `SET NULL`; the all-null fallback must remain safe for that case.

### Recommendation

Choose the minimal template and regression-coverage approach. The defect is already isolated: the domain model, service, persistence relation, and controller test establish that `assignedTo` is available and populated. The correct product fix is therefore a bounded presentation change in `_board_card.html.twig`, with tests proving rendered display, search metadata, and the all-three-null fallback. Keep this change independent from `gppro-codebase-analysis` so the baseline retains its documentation/measurement-only scope.

### Risks

- A markup change could accidentally alter existing role-avatar layout or accessibility text; preserve the existing `widgets.user_avatar` and translation conventions and verify the focused controller suite.
- Search metadata is a lower-cased concatenation consumed by JavaScript; omitting the assigned display name or changing its escaping could leave the visible name correct but search behavior broken.
- A fallback condition that checks only two roles will reproduce the current defect; the condition must require all three user relations to be absent.
- The existing worktree contains unrelated untracked SDD/measurement artifacts and the temporary template change; implementation must isolate only the independent product change and must not broaden the baseline artifact.
- Full-suite evidence exists from the prior temporary fix (4,286 tests / 59,769 assertions, one warning, four skips), but the new change still needs its own focused verification and should not treat that prior run as a substitute.

### Ready for Proposal

Yes. The product problem, bounded scope, affected flow, compatibility behavior, and implementation recommendation are sufficiently clear for `sdd-propose`, followed by focused specification/design work. The proposal should explicitly state that this is a new independent change and that the baseline change must remain documentation/measurement-only.
