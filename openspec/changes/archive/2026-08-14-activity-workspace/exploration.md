# Exploration: activity-workspace

## Current State

gppro today has three separate, non-overlapping activity surfaces — none is a 3-column list+detail+chat workspace:

1. **Kanban board** (`src/Controller/ActivityBoardController.php` + `templates/project/board.html.twig`) — per-project `todo/in_progress/in_review/done` board at `GET /admin/project/{id}/board`. Card click navigates to a full-page edit form; no inline detail panel. Confirmed out of scope (PO: do not touch).
2. **Global Activity CRUD list** (`src/Controller/ActivityController.php`) — `indexAction` at `GET /admin/activity` (route `admin_activity`) is a cross-project DataTable, and it is what the **"Actividades" menu item currently points to** (`src/EventSubscriber/MenuSubscriber.php:195`). It also has `detailsAction` (`GET /admin/activity/{id}/details`) — a real single-activity detail page but full-page nav, not an inline panel.
3. **The exact query panel 1 needs already exists**: `ProjectController::activitiesAction` (`GET /admin/project/{id}/activities/{page}`) already does `ActivityQuery->addProject($project)->setExcludeGlobals(true)` against `ActivityRepository::getActivitiesForQuery()`. No new repository method is needed for "todas las tareas de ese proyecto."

Entity-shape finding that changes the estimate: `Activity` (`src/Entity/Activity.php`) has `name`, `comment` (description), `visible`, `billable`, `number`, budget/timeBudget, `project`, `milestone`, meta fields, `teams`. The Kanban-style fields (status/priority/due date/assignee) live in a **separate** entity, `ActivityBoardState` (`src/Entity/ActivityBoardState.php`), a unidirectional `OneToOne` — its own docblock states "Activity itself carries no relation back... never touched by this feature." Panel 2 must read `ActivityBoardStateRepository` separately (read-only, safe) if it wants those fields.

Comment infrastructure: `CommentInterface` + `CommentTableTypeTrait` (`src/Entity/CommentInterface.php`, `src/Entity/CommentTableTypeTrait.php`) define `id`, `message`, `createdBy`, `createdAt`, `pinned` — nothing else. `ProjectComment`/`CustomerComment` each add one required `ManyToOne` owner + constructor. No attachment relation exists on this interface anywhere. Comment posting today (`ProjectController::addCommentAction`, lines 213-231) is a synchronous form POST + full-page redirect — confirming **zero real-time infrastructure exists anywhere in gppro today**.

File attachments: broad grep for `Attachment`/`Upload`/`File` across `src/**/*.php` confirms only `InvoiceDocumentUploadForm` (`src/Form/InvoiceDocumentUploadForm.php`) — single file, one document per invoice, no polymorphic/reusable attachment entity.

## Affected Areas

- `src/Controller/ActivityController.php` — existing global CRUD; a new controller is needed for the project-scoped workspace, but its `detailsAction` is the closest panel-2 precedent.
- `src/Repository/ActivityRepository.php` + `ActivityQuery::addProject()` — panel 1's entire data need already covered, no new method required.
- `src/Entity/ActivityBoardState.php` / `ActivityBoardStateRepository` — read-only source for status/priority/due date/assignee if panel 2 wants them; must not write through this path.
- `src/Entity/CommentInterface.php`, `CommentTableTypeTrait.php`, `ProjectComment.php` — direct shape precedent for a new `ActivityComment`.
- `src/Form/InvoiceDocumentUploadForm.php` — closest but insufficient attachment precedent (single-file only).
- `src/EventSubscriber/MenuSubscriber.php:195` — "Actividades" menu currently routes to the global list, not project-scoped; navigation gap to resolve.
- `templates/expense/index.html.twig` + `view.html.twig` (this session's Expense feature), `templates/project/embed_activities.html.twig` — proven list/detail conventions and an existing partial to adapt for panel 1.

## Approaches

1. **Mirror `ProjectComment` for panel 3 (text-first, periodic refresh), reuse panels 1+2 verbatim** — new `ActivityComment` cloning the `CommentInterface`/`CommentTableTypeTrait` shape; comment posting via ordinary form POST + refresh (no push); panels 1+2 built entirely on existing `ActivityQuery`/`Activity` fields.
   - Pros: Near-zero new pattern risk; consistent with the fact no comment thread in gppro is real-time today; smallest diff; panels 1+2 need almost no new backend code.
   - Cons: Not truly "live" like the Zendesk-style inspiration; attachments, if wanted, are still one wholly new entity with no in-repo abstraction to lean on.
   - Effort: Low-Medium overall (Low for panels 1+2, Low-Medium for text-only panel 3; Medium if attachments are added).

2. **Real-time chat with a generalized/polymorphic attachment entity reusable app-wide**
   - Pros: Matches "chat"/Zendesk framing most literally; a shared `Attachment` entity would serve future comment types too.
   - Cons: Zero existing real-time infra anywhere in gppro (first such feature); generalizing `CommentInterface` touches `ProjectComment`/`CustomerComment`, entities this feature doesn't own — scope creep; builds shared infrastructure before a second consumer is confirmed to need it.
   - Effort: High.

## Recommendation

Approach 1 — mirror `ProjectComment`, keep `ActivityComment` scoped to this feature only, periodic refresh for v1 — pending PO answers to the open questions below (they directly swing panel 3's effort). Do not generalize `CommentInterface`/attachments app-wide as part of this change.

## Open Product Questions (blocking `sdd-propose` for full scope, not for panels 1+2)

1. **Menu entry point** — "Actividades" today opens the global cross-project list. Since this workspace is project-scoped, how is it reached: a project picker under "Actividades", a new action from an existing project screen, or something else?
2. **Live vs. periodic chat** — true real-time push (new infra) or refresh/reload-on-post (near-zero new infra, mirrors `ProjectComment`) for v1?
3. **Who can comment** — any viewer of the activity/project, or only assigned/team members (`ActivityBoardState.technicalUser`/`functionalUser`/`assignedTo`)?
4. **Attachments** — needed at all in v1? If yes: max size, allowed types (mirror `InvoiceDocumentUploadForm`'s allowlist?), attach-at-post-time only or also to existing messages?
5. **Panel 2 field scope** — show `ActivityBoardState` fields (status/priority/due date/assignee) too, or only base `Activity` fields?
6. **Comment permissions** — reuse the `comments` gate pattern seen on `ProjectController::addCommentAction`, per-activity, or a new voter?

## Recommended Phased Approach

1. **Phase 1 — Panels 1+2**: new project-scoped controller/route + one Twig template composing existing `ActivityQuery`/`Activity` data into a two-column layout. Near-zero new backend code, matches this session's Expense/Quotation convention. Lowest risk, ships fastest.
2. **Phase 2 — Panel 3, text-only, periodic refresh**: `ActivityComment` (mirrors `ProjectComment`) + repository + form + POST endpoint, rendered as the third column.
3. **Phase 3 — Attachments and/or live updates**, only if PO confirms need: separate, explicitly-scoped follow-up change, since both are genuinely new infrastructure.

## Risks

- Navigation gap: "Actividades" points to the global list today; shipping without resolving Open Question 1 risks a confusing duplicate entry point or reviewers assuming the global list should change (it must not).
- `ActivityBoardState` is documented board-owned; panel 2 must only read it, never write, to avoid coupling this screen to board side effects.
- Building attachments/real-time before PO confirms need risks the over-engineering path (Approach 2).
- No existing voter precedent found for "who can comment on an activity" beyond `project`'s `comments` gate — Open Question 6 blocks knowing if a new permission is needed.

## Ready for Proposal

Partial — panels 1+2 are well-understood enough that `sdd-propose` could scope those with high confidence now; panel 3's true effort and the navigation entry point remain blocked on the open product questions above.
