# Proposal: Activity Workspace

## Intent

Working an activity in gppro today means bouncing between screens. The Kanban
board (`project_board`) shows cards but opens a full-page edit form on click;
the global activity list (`admin_activity`) is a cross-project DataTable whose
`detailsAction` is again a full-page navigation; and there is nowhere at all to
discuss an activity — `ProjectComment` and `CustomerComment` exist, but no
comment thread has ever been attached to an `Activity`.

The product owner shared a support-ticketing tool as visual inspiration and
asked for a single screen, reached from the "Actividades" menu, with three
panels: the project's activities on the left, the selected activity's detail in
the middle, and its comment thread on the right — "si alguien ha comentado eso
u otra cosa".

The product owner's framing, as stated:

> Una pantalla nueva, aparte, sin tocar el Kanban: la lista de todas las tareas
> de un proyecto, al seleccionar una se ve su descripción y detalles, y al lado
> los comentarios de esa actividad.

This is explicitly a **new, additive screen**. The Kanban board is not modified,
not replaced, and not read from.

## Naming

`ActivityComment`, table `gppro_activities_comments`, cloning
`ProjectComment` (`gppro_projects_comments`) exactly: `implements
CommentInterface` + `use CommentTableTypeTrait` (`id`, `message`, `createdBy`,
`createdAt`, `pinned`) plus one required `ManyToOne` to `Activity` with
`nullable: false, onDelete: 'CASCADE'` and a constructor taking the owner.
No new abstraction, no generalization of `CommentInterface`, no new namespace.

The screen is `ActivityWorkspaceController`, route
`GET /admin/project/{id}/workspace/{activity}` (name
`project_activity_workspace`, `activity` optional), mirroring the per-project
`project_board` route shape. A generalized/polymorphic comment or attachment
entity was rejected: it would touch `ProjectComment`/`CustomerComment`, entities
this change does not own, to serve a second consumer that does not exist yet.

## Business Rules

| # | Rule |
|---|------|
| 1 | The workspace is always scoped to exactly one project; there is no cross-project workspace |
| 2 | Panel 1 lists every non-global activity of that project — the same data the project's existing activities tab already shows |
| 3 | Selecting an activity in panel 1 loads its detail (panel 2) and its comment thread (panel 3) on the same screen |
| 4 | Panel 2 shows base `Activity` fields only; Kanban board state (status, priority, due date, assignee) is never shown or written |
| 5 | Comments are plain text, posted by ordinary form POST + redirect; no real-time push, no polling |
| 6 | Anyone who may comment on the project may comment on any activity of that project — no per-activity assignee restriction |
| 7 | A comment belongs to exactly one activity; deleting the activity deletes its comments |
| 8 | Comments are create-only in this change: no edit, no delete, no pin from this screen |
| 9 | The Kanban board is not modified in any way by this change |
| 10 | The pre-existing global cross-project activity list keeps working unchanged and stays reachable from the menu |

## Entry Point (decision)

**"Actividades" becomes a project picker; the global list is re-labelled, not
removed.** The workspace is per-project, so the menu cannot link straight to it.
This exact problem was already solved in this codebase for the Kanban board:
`ProjectController::indexAction` carries two extra route names
(`admin_project_board_picker`, `admin_project_board_picker_paginated`) and a
`$boardMode` flag that only changes the pagination route, the page title, and
the row link target (`templates/project/index.html.twig` →
`widgets.project_row_attr(entry, now, board_mode)`). That is the precedent
followed here verbatim: two more route names, a `workspaceMode` flag, one more
row-link branch. No new project-list controller, query, or template.

**Nothing is orphaned.** `ActivityController` and the `admin_activity` route
are untouched — including `detailsAction` and every `admin_activity_create` /
`activity_details` / `admin_activity_edit` / `admin_activity_delete` child
route, which remain the only CRUD path for activities. Only the menu item moves:

| menu item | before | after |
|---|---|---|
| `activities` ("Actividades") | → `admin_activity` (global list) | → `admin_project_activity_workspace_picker` (project picker → workspace) |
| `activities_all` ("Todas las actividades") | — | → `admin_activity`, unchanged global list, same `view_activity` guard, same child routes |

The alternative — dropping the global list from the menu and reaching it only by
URL — was rejected: it silently orphans a working screen and its CRUD entry
points. A second sibling entry mirrors how `projects` and `activity_board`
already coexist as separate menu items over the same project list.

## Working Assumptions (confirm in spec/design)

| # | Assumption |
|---|------------|
| A1 | **Entry point (PO decision, locked).** "Actividades" opens a project picker; picking a project opens the 3-panel workspace scoped to it. The global cross-project list is not removed or changed — it keeps its route, permissions, and child CRUD routes, and gains its own sibling menu entry per the table above |
| A2 | **Text-only comments, form POST + reload (PO decision, locked).** Posting mirrors `ProjectController::addCommentAction` exactly: POST → `saveComment()` → `redirectToRoute` back to the workspace. No WebSocket, Mercure, SSE, or polling — gppro has zero real-time infrastructure today and this change does not introduce the first |
| A3 | **Attachments are out of scope (PO decision, locked).** `CommentInterface` carries no attachment relation and the only upload precedent (`InvoiceDocumentUploadForm`) is single-file, invoice-specific. Attachments are a possible follow-up change, not part of this proposal |
| A4 | **Permissions reuse the existing project `comments` gate (PO decision, locked).** `#[IsGranted('comments', 'project')]` on the POST endpoint and `isGranted('comments', $project)` around rendering panel 3, exactly as `ProjectController` does for project comments. No new voter, no new permission, no per-activity assignee check |
| A5 | `ActivityComment` clones the `ProjectComment` shape byte for byte — `CommentInterface` + `CommentTableTypeTrait` + one required `ManyToOne Activity` (`nullable: false`, `onDelete: 'CASCADE'`) + constructor. `createdBy` is set on the unsaved comment before the form is built, as `getCommentForm()` already does |
| A6 | Panel 1 needs **no new repository method**. `ProjectController::activitiesAction` already runs `ActivityQuery->addProject($project)->setExcludeGlobals(true)` against `ActivityRepository::getActivitiesForQuery()`, which returns exactly "todas las tareas de ese proyecto" |
| A7 | **Panel 2 reads base `Activity` fields only** (`name`, `comment`/description, `visible`, `billable`, `number`, budget/timeBudget, `project`, `milestone`). `ActivityBoardState` is documented in its own class as board-owned and "never touched by this feature"; not reading it keeps this screen fully decoupled from the Kanban board, matching the PO's explicit "no tocar Kanban". Even read-only access is deferred |
| A8 | Activity selection is a URL segment (`/workspace/{activity}`), not client-side state. No activity selected renders panels 2 and 3 as empty states. This keeps the screen bookmarkable and consistent with A2's no-JS posture |

Assumptions follow this project's convention: locked here so implementation is
unambiguous, superseded by an explicit PO correction if one arrives.

## Scope

### In Scope
- `src/Controller/ActivityWorkspaceController.php` — project-scoped workspace
  route `GET /admin/project/{id}/workspace/{activity}` (activity optional),
  `#[IsGranted('view', 'project')]`, composing panels 1–3 in one render.
- One Twig template `templates/activity_workspace/index.html.twig` with the
  three panels, reusing the list/detail conventions proven by this session's
  Expense/Quotation templates and adapting
  `templates/project/embed_activities.html.twig` for panel 1.
- `src/Entity/ActivityComment.php` + migration creating
  `gppro_activities_comments` + `ActivityCommentRepository` (list-by-activity,
  `saveComment`) + `src/Form/ActivityCommentForm.php` (mirroring
  `ProjectCommentForm`) + POST endpoint
  `/admin/project/{id}/workspace/{activity}/comment_add` guarded by
  `#[IsGranted('comments', 'project')]`.
- Navigation change per A1: `admin_project_activity_workspace_picker` /
  `_paginated` route names + `workspaceMode` flag on
  `ProjectController::indexAction`, the row-link branch in
  `templates/project/index.html.twig` / `project_row_attr`, and the
  `MenuSubscriber` change repointing `activities` and adding `activities_all`.
- Translation keys for the workspace, its three panel headings, empty states,
  the comment form, and both menu labels, in `messages.en.xlf` and
  `messages.es.xlf`.
- Test coverage for: the workspace route rendering with and without a selected
  activity, panel 1 returning only that project's non-global activities, the
  `comments` gate hiding panel 3, comment creation persisting with `createdBy`,
  and cascade deletion of comments with their activity.

### Out of Scope
- **Any change to the Kanban board** — `ActivityBoardController`,
  `templates/project/board.html.twig`, and board routes are untouched (Rule 9).
- **Attachments on comments** (A3) — explicit follow-up candidate, not deferred
  polish: it needs a genuinely new entity with no in-repo abstraction to reuse.
- **Real-time / push / polling updates** (A2) — the thread refreshes on POST
  redirect and on page load, nothing else.
- **`ActivityBoardState` fields in panel 2** (A7) — no status, priority, due
  date, or assignee, in either direction.
- **Any new comment-permission voter or permission key** (A4) — the existing
  project `comments` gate is reused as-is.
- Editing, deleting, or pinning comments from this screen (Rule 8), and any
  comment threading, mentions, reactions, or read receipts.
- Comment notifications (email or in-app).
- Creating, editing, or deleting activities from the workspace — that stays in
  the untouched `ActivityController` CRUD.
- Any change to `Activity`, `ActivityRepository`, `ActivityQuery`,
  `CommentInterface`, `CommentTableTypeTrait`, `ProjectComment`, or
  `CustomerComment`.
- Removing or altering the global cross-project activity list (A1).
- Filtering, sorting, or searching within panel 1 beyond the ordering
  `activitiesAction` already applies.
- A cross-project or "my activities" workspace variant.

## Capabilities

### New Capabilities
- `activity-workspace`: a project-scoped three-panel screen that lists a
  project's activities, shows the selected activity's base detail, and hosts a
  text-only, append-only comment thread for that activity, reached through a
  project picker on the "Actividades" menu.

This is a genuinely **new** capability. `openspec/specs/` currently holds only
`expense-allocation` and `activity-board-assigned-user-display`; nothing there
covers an activity workspace, an activity comment thread, or the "Actividades"
entry point. Unlike this session's three Expense-module changes — all deltas on
the `expense-allocation` capability — this one creates
`openspec/specs/activity-workspace/spec.md` from scratch.

### Modified Capabilities
- None. `activity-board-assigned-user-display` is deliberately untouched
  (Rule 9 / A7), and no requirement of `expense-allocation` is affected.

## Approach

Compose existing pieces; write almost no new backend logic. Panel 1 reuses the
`ActivityQuery->addProject()->setExcludeGlobals(true)` call
`ProjectController::activitiesAction` already performs (A6). Panel 2 renders
base `Activity` getters straight from the entity resolved by the optional
`{activity}` route segment, validating it belongs to `{id}` before rendering
(A7, A8). Panel 3 clones the project-comment pattern end to end:
`ActivityComment implements CommentInterface` + `CommentTableTypeTrait`,
`ActivityCommentForm` shaped like `ProjectCommentForm`, `createdBy` stamped on
the unsaved entity before the form is built, POST → save → redirect, and the
thread rendered only when `isGranted('comments', $project)` (A2, A4, A5).

The navigation change follows the board-picker precedent literally: extra route
names on the existing `ProjectController::indexAction`, one `workspaceMode`
boolean deciding pagination route, page title, and row-link target — the same
three things `$boardMode` decides today. No new project-list code path exists
after this change; there is one action serving three modes.

The migration is a single additive `CREATE TABLE gppro_activities_comments`
with an index on `activity_id` and an `ON DELETE CASCADE` FK. No existing table
is altered and no data is transformed.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/Controller/ActivityWorkspaceController.php` | New | Workspace render (panels 1–3) + comment POST endpoint |
| `src/Entity/ActivityComment.php` | New | `CommentInterface` + `CommentTableTypeTrait` + required `ManyToOne Activity`, `onDelete: 'CASCADE'` |
| `src/Repository/ActivityCommentRepository.php` | New | `getComments(Activity)`, `saveComment()` |
| `src/Form/ActivityCommentForm.php` | New | Mirrors `ProjectCommentForm` |
| `migrations/Version*.php` | New | `CREATE TABLE gppro_activities_comments` + index + FK |
| `templates/activity_workspace/index.html.twig` | New | Three-panel layout, empty states, comment thread + form |
| `src/Controller/ProjectController.php` | Modified | Two picker route names + `workspaceMode` flag on `indexAction` (board-picker precedent) |
| `templates/project/index.html.twig` + `project_row_attr` widget | Modified | Row link target for workspace-picker mode |
| `src/EventSubscriber/MenuSubscriber.php` | Modified | `activities` repointed to the picker; new `activities_all` sibling for the global list |
| `translations/messages.en.xlf`, `messages.es.xlf` | Modified | Workspace, panel, empty-state, comment, and menu labels |
| `tests/` | New/Modified | Route rendering, panel-1 scoping, `comments` gate, comment persistence, cascade delete |
| `src/Controller/ActivityController.php`, `src/Repository/ActivityRepository.php`, `ActivityQuery` | Unchanged | Global list and its query reused verbatim (A1, A6) |
| `src/Controller/ActivityBoardController.php`, `src/Entity/ActivityBoardState.php`, `templates/project/board.html.twig` | Unchanged | Kanban explicitly untouched (Rule 9, A7) |
| `src/Entity/CommentInterface.php`, `CommentTableTypeTrait.php`, `ProjectComment.php`, `CustomerComment.php` | Unchanged | Cloned, never generalized |
| `config/packages/gppro.yaml` | Unchanged | No new permission; `view`/`comments` gates reused (A4) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Two menu entries over activities ("Actividades" picker + "Todas las actividades") confuse users about where to go | Med | Distinct, explicit labels locked in A1; the same coexistence already exists for `projects` vs `activity_board`; the global entry keeps the original label semantics for anyone who relied on it |
| Reviewers assume the global cross-project list should be deleted or changed | Med | A1 and Rule 10 state the opposite explicitly; `ActivityController` appears as **Unchanged** in Affected Areas, and a test asserts `admin_activity` still renders |
| The workspace drifts into duplicating the Kanban board (status, assignee, drag-drop) | Med | A7 forbids reading `ActivityBoardState` at all in this change; panel 2's field list is enumerated and closed |
| `{activity}` from another project is passed in the URL, leaking an activity across projects | Med | The controller MUST assert `activity.getProject() === project` and 404 otherwise; explicit test case |
| Panel 1 grows unusable on projects with many activities | Med | Reuse the pagination `activitiesAction` already applies; no new filtering is in scope, and the page size is a spec-phase decision |
| Users expect a live chat because the inspiration was a ticketing tool | Med | A2 is locked and must be visible in the spec's scenarios; the thread updates on post and on reload only |
| Comment thread renders for users who may view the project but not comment on it | Low | Panel 3 is wrapped in `isGranted('comments', $project)` exactly as `ProjectController::detailsAction` does; asserted by test |
| Scope creep into attachments or notifications | Med | A3 and Out of Scope name both explicitly as separate follow-up changes |

## Rollback Plan

Revert the migration (drops `gppro_activities_comments`; no other table is
altered), delete the new controller, entity, repository, form, and template, and
revert the `ProjectController`, `templates/project/index.html.twig`,
`MenuSubscriber`, and translation edits. The picker routes and the
`workspaceMode` flag are purely additive branches on an existing action, so
removing them restores `indexAction` to its current two modes. `MenuSubscriber`
reverts to a single `activities` entry pointing at `admin_activity` — the global
list, its controller, and its CRUD child routes were never modified, so no
activity screen is lost in either direction. Kanban board, `Activity`,
`ActivityQuery`, and all existing comment entities are byte-identical before and
after.

## Dependencies

- Existing `Project`, `Activity`, `ActivityRepository` + `ActivityQuery`,
  `CommentInterface` / `CommentTableTypeTrait`, `ProjectComment` /
  `ProjectCommentForm` (as shape precedent), `ProjectController::indexAction`
  picker-mode precedent, and the existing `view` and `comments` project gates
  (reused, not extended).

## Success Criteria

- [ ] "Actividades" in the menu opens a project picker; selecting a project opens the 3-panel workspace scoped to that project.
- [ ] The global cross-project activity list still renders at `admin_activity`, is still reachable from the menu, and its create/edit/details/delete routes still work.
- [ ] The Kanban board renders and behaves identically to before this change, and no board route, template, or `ActivityBoardState` row is touched.
- [ ] Panel 1 lists exactly the project's non-global activities — the same set the project's existing activities tab shows — and no activity from another project.
- [ ] Opening the workspace with no activity selected renders panels 2 and 3 as empty states without error.
- [ ] Selecting an activity renders its base fields (name, description, visible, billable, number, budget, milestone) in panel 2.
- [ ] Panel 2 displays no status, priority, due date, or assignee, and the request performs no `ActivityBoardState` read or write.
- [ ] Requesting the workspace with an `{activity}` belonging to a different project returns 404.
- [ ] A user with the project's `comments` gate can post a text comment; it appears in panel 3 after the redirect with the correct author and timestamp.
- [ ] A user who may view the project but lacks the `comments` gate sees no comment form and no thread.
- [ ] Deleting an activity deletes its comments via `ON DELETE CASCADE`, leaving no orphan rows.
- [ ] No comment attachment field, upload control, or real-time connection exists anywhere in the shipped screen.
- [ ] All workspace, panel, empty-state, comment, and menu labels render translated in both `en` and `es`.
