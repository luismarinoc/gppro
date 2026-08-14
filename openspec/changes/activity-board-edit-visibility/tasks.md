# Tasks: Activity Board Edit Visibility

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~180-230 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Board card icon, permission routing, JS click/filter, denial message, stepper, translations | PR 1 | `php bin/phpunit tests/Controller/ActivityBoardControllerTest.php tests/Controller/ActivityControllerTest.php` | Manual QA checklist (Task 6.1) — no JS runner exists (D8) | Revert the single PR; `dblclick` editing keeps working, no entity/migration change |

## Phase 1: Board card affordance (RED then GREEN)

- [x] 1.1 RED: In `tests/Controller/ActivityBoardControllerTest.php`, assert `.activity_board_card a.activity_board_card_edit` count equals card count, is always present (not hover-gated), and `href` resolves to `activity_details`. Run and confirm failure.
- [x] 1.2 RED: Assert `.activity_board_card[data-can-edit]` is `"1"` for `ROLE_ADMIN` and `"0"` for a team-scoped user with `view_*` but not `edit_*_activity`.
- [x] 1.3 GREEN: In `templates/project/_board_card.html.twig`, add `data-can-edit="{{ is_granted('edit', card.activity) ? '1' : '0' }}"` to `.activity_board_card` (A1, no `ActivityBoardController` change).
- [x] 1.4 GREEN: Add `<a class="activity_board_card_edit" href="{{ path('activity_details', {id: card.activity.id}) }}" title="{{ 'activity_board.edit_activity'|trans }}">{{ icon('edit') }}</a>` inside `.activity_board_card_badges` (A4, A5).
- [x] 1.5 Re-run 1.1-1.2, confirm GREEN.

## Phase 2: Board URL contract + drag safety (RED then GREEN)

- [x] 2.1 RED: Assert `#activity_board_box[data-details-url]` contains `/details` and `data-edit-url` is still present, in `ActivityBoardControllerTest`.
- [x] 2.2 RED: Re-run existing filter/column/`data-search` assertions after the markup edit to lock the no-regression requirement.
- [x] 2.3 GREEN: In `templates/project/board.html.twig` `block box_attributes`, add `data-details-url="{{ path('activity_details', {id: '000'}) }}"` alongside `data-edit-url` (A2).
- [x] 2.4 GREEN: In `assets/js/widgets/GpproActivityBoard.js` constructor, add `this.detailsUrlTemplate = element.dataset.detailsUrl;` and add `filter: '.activity_board_card_edit', preventOnFilter: false` to the `Sortable.create()` options (A3).
- [x] 2.5 GREEN: Add a delegated `click` listener on `this.element` for `.activity_board_card_edit`, calling `event.preventDefault(); event.stopPropagation();` then a shared `openCard(card)` branch: `data-can-edit === '1'` opens the edit modal via `editUrlTemplate`, else sets `window.location.href` from `detailsUrlTemplate`.
- [x] 2.6 GREEN: Update `onCardDoubleClicked()` to call the same `openCard(card)` branch instead of unconditionally opening the modal.
- [x] 2.7 Re-run 2.1-2.2, confirm GREEN.

## Phase 3: Denial message on read-only detail (RED then GREEN)

- [x] 3.1 RED: In `tests/Controller/ActivityControllerTest.php`, assert `#activity_edit_denied` is present with the exact es string "No tenés permiso para editar esta actividad" for a non-editor viewer, and absent for `ROLE_ADMIN`.
- [x] 3.2 GREEN: In `translations/messages.es.xlf` and `messages.en.xlf`, add `activity.edit_denied` (id `gpActBrd25`, continuing the `gpActBrd24` sequence added in Phase 1): es "No tenés permiso para editar esta actividad", en "You do not have permission to edit this activity".
- [x] 3.3 GREEN: In `templates/activity/details.html.twig`, add `{% if not can_edit %}<div id="activity_edit_denied" class="alert alert-warning">{{ 'activity.edit_denied'|trans }}</div>{% endif %}` guarded by the existing `can_edit` variable (line 5).
- [x] 3.4 Re-run 3.1, confirm GREEN.

## Phase 4: Read-only stage stepper (RED then GREEN)

- [x] 4.1 RED: In `ActivityControllerTest`, persist board state `in_review` for a project activity, GET `/admin/activity/{id}/edit` as a modal request, assert 4 `.step-item` elements and `.step-item.active` text equals "En revisión"/"In review".
- [x] 4.2 RED: Assert no stepper (`.steps` count == 0) for an activity where `project === null` (global).
- [x] 4.3 RED: For a stateless project activity, assert the stepper's `active` item is `todo` AND `ActivityBoardStateRepository::count([])` is unchanged after the request (transient default, no write).
- [x] 4.4 GREEN: In `src/Controller/ActivityController.php::editAction()`, inject `ActivityBoardStateRepository`, resolve `board_status` as `ActivityBoardStatus|null` — non-null only when `null !== $activity->getId() && !$activity->isGlobal()` (same gate as `ActivityEditForm:132`) — via `findOrCreate($activity)->getStatus()`, pass it to the `activity/edit.html.twig` render array.
- [x] 4.5 GREEN: Create `templates/activity/_board_status_stepper.html.twig`: horizontal Tabler `.steps`/`.step-item` stepper over a Twig map `{'todo': 0, 'in_progress': 1, 'in_review': 2, 'done': 3}` (map lookup, not `set` inside a `for`, since Twig does not leak loop-scoped variables), marking `.step-item.active` for the current `board_status`, reusing `activity_board.status.*` translation keys, no interactive control (D3).
- [x] 4.6 GREEN: In `templates/activity/edit.html.twig`, include the stepper first inside `block form_body`, guarded by `board_status|default(null) is not null`.
- [x] 4.7 GREEN: Add translation keys `activity_board.edit_activity` and `activity_board.stage` to `messages.{es,en}.xlf`.
- [x] 4.8 Re-run 4.1-4.3, confirm GREEN.

## Phase 5: Regression + docs

- [ ] 5.1 Run the full `ActivityBoardControllerTest` and `ActivityControllerTest` suites; confirm no unrelated regression.
- [ ] 5.2 Add `.activity_board_card_edit { cursor: pointer; }` plus a hover colour rule to `assets/sass/board.scss`.
- [ ] 5.3 Confirm `ActivityVoter` and `assets/js` drag/onEnd logic were not modified (grep diff for these paths).

## Phase 6: Manual QA (D8 — no new JS test runner)

- [ ] 6.1 Manual QA checklist on the board (`project_board` route), NOT automated, before merge:
  - [ ] Clicking the edit icon opens the modal for an editor and never starts a drag (`onEnd` does not fire, card stays in its column).
  - [ ] Clicking the edit icon for a view-only user navigates to `activity_details` and shows the denial message.
  - [ ] Double-clicking a card body still opens the correct target (modal or details) exactly as before.
  - [ ] Dragging a card body (not the icon) to another column still updates its stage.
  - [ ] Existing search/status/priority/assignee filters and column counts behave unchanged.
  - [ ] Edit modal shows the stepper with the correct stage highlighted for a project activity, and no stepper for a global activity.
