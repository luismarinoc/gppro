# Tasks: Activity Workspace

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~550–650 (9 new files + 5 modified files: new entity/migration/repository/form/controller, 2 new templates, 2 test files, plus route/menu/widget/translation edits) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 |
| Delivery strategy | ask-on-risk |
| Chain strategy | feature-branch-chain (resolved — PO approved; tracker branch `activity-workspace-tracker`, PR1 branch `activity-workspace-tracker-pr1-data-layer`) |

Decision needed before apply: No — resolved this session
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

Larger than both prior Expense-approval changes (single PR, ~180–300 lines each): this
change adds a new entity + migration + repository + form + controller (2 routes) + 2
templates + a modified controller/widget/menu/translation surface. `ActivityWorkspaceController`
alone (2 actions, 2 guards, panel composition) plus its functional test (7 RED scenarios)
likely exceeds 250 lines on its own.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Data layer: `ActivityComment` + migration + `ActivityCommentRepository` + `ActivityCommentForm`, no controller/UI | PR 1 | `phpunit tests/Entity/ActivityCommentTest.php tests/Repository/ActivityCommentRepositoryTest.php` | `doctrine:migrations:migrate` on test DB, confirm table+FK+index | revert migration `down()` (drops FKs, table) + delete entity/repo/form files |
| 2 | `ActivityWorkspaceController` (both actions) + `templates/activity_workspace/*` | PR 2 (base: PR 1) | `phpunit tests/Controller/ActivityWorkspaceControllerTest.php` | manual: open `/admin/project/{id}/workspace/{activity}` in staging, post a comment | delete controller + templates + their tests; route names never referenced elsewhere yet |
| 3 | Navigation: `ProjectController` picker routes, `widgets.html.twig`, `project/index.html.twig`, `MenuSubscriber`, translations | PR 3 (base: PR 2) | `phpunit tests/Controller/ProjectControllerTest.php tests/EventSubscriber/MenuSubscriberTest.php` | manual: click "Actividades" in staging menu, confirm picker → workspace, confirm "Todas las actividades" still opens `admin_activity` | revert the 5 modified files to pre-change state; `ActivityWorkspaceController` from PR 2 stays functional but unlinked from the menu |

## Phase 1: Entity + Migration (PR 1)

- [x] 1.1 RED: `tests/Entity/ActivityCommentTest.php` — constructor stamps `createdAt`, holds the given `Activity`; mirrors `ProjectCommentTest`.
- [x] 1.2 GREEN: `src/Entity/ActivityComment.php` — `CommentInterface` + `CommentTableTypeTrait` + required `ManyToOne Activity` (`nullable: false`, `onDelete: 'CASCADE'`), table `gppro_activities_comments`, per design's exact block. Run 1.1 green.
- [x] 1.3 Create `migrations/Version20260813170000.php` — `up()`: `CREATE TABLE gppro_activities_comments` + `activity_id`/`created_by_id` indexes + FKs (`ON DELETE CASCADE`); `down()` drops both FKs then the table, per design DDL.
- [x] 1.4 Verify: run the migration against the test DB; confirm the table, both indexes, and both FKs exist.

## Phase 2: Repository + Form (PR 1)

- [x] 2.1 RED: `tests/Repository/ActivityCommentRepositoryTest.php` — `getComments()` orders `pinned DESC, createdAt DESC`; cascade delete of the owning activity leaves no orphan comment rows. Traces: "Deleting an activity cascades its comments".
- [x] 2.2 GREEN: `src/Repository/ActivityCommentRepository.php` — `getComments(Activity): array`, `saveComment(ActivityComment): void` (persist + flush), body from `ProjectRepository::getComments` with `comments.activity`. Run 2.1 green.
- [x] 2.3 Create `src/Form/ActivityCommentForm.php` — mirrors `ProjectCommentForm`: `data_class: ActivityComment::class`, `csrf_token_id: 'admin_activity_comment'`, `attr['data-form-event']: 'gppro.activityComment'`, single `message` TextareaType. (RED test added: `tests/Form/ActivityCommentFormTest.php`, not originally listed but required by Strict TDD.)

## Phase 3: Controller (PR 2 — security-relevant RED tests first)

- [x] 3.1 RED (MANDATORY, security): `tests/Controller/ActivityWorkspaceControllerTest.php` — request `project_activity_workspace` with an `{activity}` belonging to a different project → 404, no data from the other project rendered. Traces: "Activity from another project is rejected".
- [x] 3.2 RED (MANDATORY, security): same file — a user with `view` but not `comments` on the project sees no comment thread and no form; a user with `comments` sees both. Traces: "Unauthorized user sees neither thread nor form" / "Authorized user sees thread and form".
- [x] 3.3 RED: workspace with no `{activity}` renders panels 2 and 3 as empty states without error. Traces: "No activity selected renders an empty state".
- [x] 3.4 RED: selecting an activity renders base fields only (name, description, visible, billable, number, budget/timeBudget, project, milestone) and performs no `ActivityBoardState` read. Traces: "Selected activity renders base fields".
- [x] 3.5 RED: panel 1 lists exactly the requested project's non-global activities, excluding another project's activities. Traces: "Panel 1 shows only this project's activities".
- [x] 3.6 RED: direct URL to a valid `{activity}` renders that activity's detail and comments. Traces: "Direct URL to a selected activity renders its detail".
- [x] 3.7 RED: POSTing `comment_add` persists the comment with `createdBy`/timestamp and redirects; the comment appears in panel 3 after reload. Traces: "Comment appears after redirect".
- [x] 3.8 GREEN: `src/Controller/ActivityWorkspaceController.php` — `indexAction` (`#[IsGranted('view', 'project')]`) and `addCommentAction` (`#[IsGranted('comments', 'project')]`), both starting with the 404 cross-project guard (`$activity->getProject()?->getId() !== $project->getId()`) copied from `ActivityBoardController::updateCardAction`, per design's exact route/guard block. Run 3.1–3.7 green.

## Phase 4: Templates (PR 2)

- [x] 4.1 Create `templates/activity_workspace/embed_activities.html.twig` — adapted copy of `templates/project/embed_activities.html.twig` for panel 1 (D5: copy, not parameterize; keeps the details-tab partial byte-identical).
- [x] 4.2 Create `templates/activity_workspace/index.html.twig` — `{% extends 'base.html.twig' %}`, `page_setup`, 3-column layout; panel 3 is `{% if comments is not null %}{{ include('embeds/comments.html.twig', {'form': commentForm, 'comments': comments}) }}{% endif %}` — verbatim reuse, zero new markup in that partial (D6).
- [x] 4.3 Verify: `lint:twig` on both new templates.

## Phase 5: Navigation — Picker Routes + Menu (PR 3)

- [x] 5.1 RED: extend `tests/Controller/ProjectControllerTest.php` — `admin_project_activity_workspace_picker` renders the project list; a row link targets `project_activity_workspace` when `workspaceMode` is active. Traces: "Selecting a project from the picker opens its workspace".
- [x] 5.2 GREEN: `src/Controller/ProjectController.php` — add `#[Route]` attributes for `admin_project_activity_workspace_picker` / `_paginated` on `indexAction`, `$workspaceMode` flag driving pagination route/title/row-link target, `createPageSetup()` 2nd arg, per the `$boardMode` precedent. Run 5.1 green.
- [x] 5.3 `templates/macros/widgets.html.twig` — `project_row_attr` gains 4th param `workspace` (lines 476–484).
- [x] 5.4 `templates/project/index.html.twig` — call `project_row_attr(entry, now, board_mode ?? false, workspace_mode ?? false)`.
- [ ] 5.5 RED: extend `tests/EventSubscriber/MenuSubscriberTest.php` — `activities` entry points at `admin_project_activity_workspace_picker` under the `view_project` block; new `activities_all` sibling points at unchanged `admin_activity` under the `view_activity` block with its original child routes. Traces: "Global list still renders and is still reachable".
- [ ] 5.6 GREEN: `src/EventSubscriber/MenuSubscriber.php` — repoint `activities` (view_project block, after `activity_board`), add `activities_all` (view_activity block, replacing the old entry, same child routes: `admin_activity_create`, `activity_details`, `admin_activity_edit`, `admin_activity_delete`). Run 5.5 green.

## Phase 6: Translations (PR 3)

- [ ] 6.1 `translations/messages.en.xlf` — add `gpActWs1`–`gpActWs4` (`activity_workspace.title`, `.detail`, `.select_activity`, `activities_all`).
- [ ] 6.2 `translations/messages.es.xlf` — same ids/resnames, `state="translated"`, per design's table.
- [ ] 6.3 `lint:xliff` on both files.

## Phase 7: Full Regression + Verification

- [ ] 7.1 Run `phpunit tests/Entity/ActivityCommentTest.php tests/Repository/ActivityCommentRepositoryTest.php tests/Controller/ActivityWorkspaceControllerTest.php tests/Controller/ProjectControllerTest.php tests/EventSubscriber/MenuSubscriberTest.php` — all green.
- [ ] 7.2 Regression: run `tests/Controller/ActivityBoardControllerTest.php` and confirm `templates/project/board.html.twig` byte-identical (git diff empty) — Kanban board unaffected (Rule 9).
- [ ] 7.3 Regression: run `tests/Controller/ActivityControllerTest.php` and confirm `src/Controller/ActivityController.php` byte-identical (git diff empty) — global list/CRUD unaffected.
- [ ] 7.4 `lint:twig` all new/modified templates, `lint:xliff` both translation files (consolidated final check).
- [ ] 7.5 `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` — no new errors introduced.
