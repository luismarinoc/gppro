# Design: Activity Workspace

## Technical Approach

One new controller composes three panels in a single GET render, plus one
POST endpoint for comments. Panel 1 reuses `ActivityQuery` exactly as
`ProjectController::activitiesAction` does (A6); panel 2 reads base `Activity`
getters (A7); panel 3 clones the `ProjectComment` stack (A5) and reuses
`templates/embeds/comments.html.twig` verbatim. Navigation follows the
`admin_project_board_picker` precedent literally (A1).

## Architecture Decisions

| # | Decision | Choice | Rejected | Rationale |
|---|---|---|---|---|
| D1 | Class boundary | New `ActivityWorkspaceController` with class-level `#[Route(path: '/admin/project')]` | Adding actions to `ProjectController` (already 652 lines, 20 actions) | Exact precedent: `ActivityBoardController` is a separate class under the same `/admin/project` prefix for the per-project board screen. Only `indexAction`'s picker routes touch `ProjectController`. |
| D2 | Selected activity | Optional route segment `{activity}` with `defaults: ['activity' => null]`, resolved to `?Activity` by the entity resolver | Query string; client-side JS state | A8: bookmarkable, no-JS, mirrors `project_board_card_update`'s `/{id}/board/{activity}` shape |
| D3 | Panel-1 pagination | `page` as query string via the existing `pagination()` twig function (`routeParams` merge → `?page=N`, see `src/Twig/PaginationExtension.php:75-79`) | A third `_paginated` route name with `{page}` | `{activity}` is already optional; a `{page}` segment after an optional segment is not expressible. `Router::generate` emits unknown params as query string. |
| D4 | Panel-1 page size | `25` (private const in the workspace controller) | `activitiesAction`'s `5`; `BaseQuery::DEFAULT_PAGESIZE` = 50 | `5` is sized for a details-page card, not for the primary navigation column; 50 makes the column scroll far past the detail panel. Not configurable — no system-config precedent for per-screen page size. |
| D5 | Panel 1 template | New `templates/activity_workspace/embed_activities.html.twig`, adapted copy | Modifying `templates/project/embed_activities.html.twig` | That partial is live on `project_details`; it hardcodes `project_activities` pagination and `activity_row_attr` (links to activity edit). Copying keeps the details tab byte-identical. |
| D6 | Panel 3 template | Reuse `templates/embeds/comments.html.twig` **verbatim**, passing only `{form, comments}` | A workspace-specific comment partial | Omitting `route_pin`/`route_delete` makes them `null`, which the partial already handles → create-only thread (Rule 8) with zero new markup. |
| D7 | Permissions | `#[IsGranted('view', 'project')]` on GET, `#[IsGranted('comments', 'project')]` on POST, `isGranted('comments', $project)` around panel 3 | New `ActivityVoter` `comments` attribute | A4. `ProjectVoter::ALLOWED_ATTRIBUTES` already carries `comments`; `ActivityVoter` has no `comments` attribute and gains none — activity-level authorization is derived from its project, exactly as `ActivityVoter::voteOnAttribute` already walks `$subject->getProject()`. |
| D8 | Comment persistence | New `ActivityCommentRepository` (`#[ORM\Entity(repositoryClass:)]`) | Adding `getComments`/`saveComment` to `ActivityRepository` | `ActivityRepository` is declared Unchanged in scope; `ProjectComment` hangs off `ProjectRepository` only for historical reasons. |

## Data Flow

    GET /admin/project/{id}/workspace/{activity}
       │  IsGranted('view', project)
       ├─ guard: activity.project.id === project.id, else 404
       ├─ panel1: ActivityQuery(project, excludeGlobals) → ActivityRepository::getPagerfantaForQuery
       ├─ panel2: $activity getters (no ActivityBoardState read)
       └─ panel3: isGranted('comments', project)
                    ? ActivityCommentRepository::getComments($activity) + form
                    : null → both hidden

    POST .../comment_add ──IsGranted('comments', project)──→ saveComment()
        └──302──→ project_activity_workspace {id, activity}

## File Changes

| File | Action | Description |
|---|---|---|
| `src/Controller/ActivityWorkspaceController.php` | Create | `indexAction` (panels 1-3) + `addCommentAction` |
| `src/Entity/ActivityComment.php` | Create | `CommentInterface` + `CommentTableTypeTrait` + `ManyToOne Activity` |
| `src/Repository/ActivityCommentRepository.php` | Create | `getComments(Activity): array`, `saveComment(ActivityComment): void` |
| `src/Form/ActivityCommentForm.php` | Create | Mirror of `ProjectCommentForm` |
| `migrations/Version2026MMDDHHMMSS.php` | Create | `CREATE TABLE gppro_activities_comments` |
| `templates/activity_workspace/index.html.twig` | Create | 3-column layout, `{% extends 'base.html.twig' %}`, `page_setup` |
| `templates/activity_workspace/embed_activities.html.twig` | Create | Panel 1 (adapted from `project/embed_activities.html.twig`) |
| `src/Controller/ProjectController.php` | Modify | +2 route attributes on `indexAction`, `$workspaceMode` flag, `createPageSetup()` 2nd arg |
| `templates/project/index.html.twig` | Modify | `project_row_attr(entry, now, board_mode ?? false, workspace_mode ?? false)` |
| `templates/macros/widgets.html.twig` | Modify | `project_row_attr` gains 4th param `workspace` (line 476-484) |
| `src/EventSubscriber/MenuSubscriber.php` | Modify | Repoint `activities`; add `activities_all` (lines 188-198) |
| `translations/messages.{en,es}.xlf` | Modify | 4 new units, ids `gpActWs1`-`gpActWs4` |
| `tests/Controller/ActivityWorkspaceControllerTest.php` | Create | Route, scoping, 404, gate, persistence |
| `tests/Entity/ActivityCommentTest.php` | Create | Entity shape + cascade |

## Interfaces / Contracts

### Routes

```php
// ActivityWorkspaceController (class prefix #[Route(path: '/admin/project')])
#[Route(path: '/{id}/workspace/{activity}', defaults: ['activity' => null], requirements: ['activity' => '\d+'], name: 'project_activity_workspace', methods: ['GET'])]
#[IsGranted('view', 'project')]
public function indexAction(Project $project, ?Activity $activity, Request $request, ActivityRepository $activityRepository, ActivityCommentRepository $commentRepository): Response

#[Route(path: '/{id}/workspace/{activity}/comment_add', requirements: ['activity' => '\d+'], name: 'project_activity_workspace_comment_add', methods: ['POST'])]
#[IsGranted('comments', 'project')]
public function addCommentAction(Project $project, Activity $activity, Request $request, ActivityCommentRepository $commentRepository): Response

// ProjectController::indexAction — two additional attributes, verbatim board shape
#[Route(path: '/workspace', defaults: ['page' => 1], name: 'admin_project_activity_workspace_picker', methods: ['GET'])]
#[Route(path: '/workspace/page/{page}', requirements: ['page' => '[1-9]\d*'], name: 'admin_project_activity_workspace_picker_paginated', methods: ['GET'])]
```

### 404 guard (both actions, first statement after entry)

```php
if ($activity !== null && $activity->getProject()?->getId() !== $project->getId()) {
    throw $this->createNotFoundException('Activity does not belong to this project.');
}
```

Copied from `ActivityBoardController::updateCardAction` (line 63-65). It runs
before any repository read, so no panel-1/panel-3 query executes for a
cross-project id. `addCommentAction` uses the same guard with `Activity` non-nullable.

### Entity

```php
#[ORM\Table(name: 'gppro_activities_comments')]
#[ORM\Index(columns: ['activity_id'])]
#[ORM\Entity(repositoryClass: ActivityCommentRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[Serializer\ExclusionPolicy('all')]
class ActivityComment implements CommentInterface
{
    use CommentTableTypeTrait;   // id, message, createdBy, createdAt, pinned

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Activity $activity;

    public function __construct(Activity $activity)
    {
        $this->createdAt = new \DateTime();
        $this->activity = $activity;
    }

    public function getActivity(): Activity { return $this->activity; }
}
```

`pinned` exists via the trait (schema parity with `ProjectComment`) but is
never written or toggled by this change — no pin route, no pin UI (Rule 8);
`getComments` still orders by it, matching `ProjectRepository::getComments`.

### Migration DDL (raw `addSql`, per `Version20260812140000`)

```sql
-- up()
CREATE TABLE gppro_activities_comments (id INT AUTO_INCREMENT NOT NULL, activity_id INT NOT NULL, created_by_id INT NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, pinned TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_GPPRO_ACTIVITIES_COMMENTS_ACTIVITY (activity_id), INDEX IDX_GPPRO_ACTIVITIES_COMMENTS_CREATED_BY (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
ALTER TABLE gppro_activities_comments ADD CONSTRAINT FK_GPPRO_ACTIVITIES_COMMENTS_ACTIVITY FOREIGN KEY (activity_id) REFERENCES gppro_activities (id) ON DELETE CASCADE;
ALTER TABLE gppro_activities_comments ADD CONSTRAINT FK_GPPRO_ACTIVITIES_COMMENTS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES gppro_users (id) ON DELETE CASCADE;
-- down(): DROP both FKs, then DROP TABLE gppro_activities_comments
```

### Form

`ActivityCommentForm extends AbstractType` — identical to `ProjectCommentForm`
except `data_class: ActivityComment::class`, `csrf_token_id: 'admin_activity_comment'`,
`attr['data-form-event']: 'gppro.activityComment'`. Single `message` TextareaType,
`label => false`.

### Repository

```php
class ActivityCommentRepository extends EntityRepository   // Doctrine\ORM\EntityRepository
{
    public function getComments(Activity $activity): array  // ORDER BY pinned DESC, createdAt DESC
    public function saveComment(ActivityComment $comment): void  // persist + flush
}
```

Query body is `ProjectRepository::getComments` (lines 426-439) with
`comments.activity` in place of `comments.project`.

### Controller → template contract

```php
return $this->render('activity_workspace/index.html.twig', [
    'page_setup' => new PageSetup('activity_workspace.title'),
    'project' => $project, 'activities' => $entries, 'activity' => $activity,
    'comments' => $comments,          // null when 'comments' not granted
    'commentForm' => $commentForm,    // null when 'comments' not granted
    'now' => $this->getDateTimeFactory()->createDateTime(),
]);
```

Panel 3 markup is exactly:
`{% if comments is not null %}{{ include('embeds/comments.html.twig', {'form': commentForm, 'comments': comments}) }}{% endif %}`.

### Navigation diff (`MenuSubscriber.php`)

```php
// inside the existing view_project/teamlead/team block, after activity_board (line 192)
$activities = new MenuItemModel('activities', 'activities', 'admin_project_activity_workspace_picker', [], 'activity');
$activities->setChildRoutes(['admin_project_activity_workspace_picker_paginated', 'project_activity_workspace']);
$menu->addChild($activities);

// inside the existing view_activity block (line 194-198), replacing the old entry
$activitiesAll = new MenuItemModel('activities_all', 'activities_all', 'admin_activity', [], 'activity');
$activitiesAll->setChildRoutes(['admin_activity_create', 'activity_details', 'admin_activity_edit', 'admin_activity_delete']);
$menu->addChild($activitiesAll);
```

The picker entry **moves permission block**: it renders the project list
(`is_granted('listing', 'project')`), so keeping it under the `view_activity`
guard would hand a 403 to activity-only users. `activities_all` keeps the
original `view_activity` guard and child routes untouched (Rule 10).

### Translations

Convention is a per-feature id prefix, not a global counter (`gpExpense1-51`
is the expense domain; `gpActBrd1-23` the board domain). New prefix `gpActWs`;
`gpExpenseNN` is not the right domain here.

| id | resname | en | es |
|---|---|---|---|
| `gpActWs1` | `activity_workspace.title` | Activity workspace | Espacio de actividades |
| `gpActWs2` | `activity_workspace.detail` | Activity detail | Detalle de la actividad |
| `gpActWs3` | `activity_workspace.select_activity` | Select an activity to see its detail and comments | Selecciona una actividad para ver su detalle y comentarios |
| `gpActWs4` | `activities_all` | All activities | Todas las actividades |

Reused, no new key: `activities` (panel 1 heading + picker menu label),
`comment` / `error.no_comments_found` / `placeholder.type_message` (from
`embeds/comments.html.twig`), `error.no_entries_found`, `name`, `comment`,
`visible`, `billable`, `project`, `milestone`, `budget`, `timeBudget`.

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit | `ActivityComment` constructor stamps `createdAt`, holds activity | `tests/Entity/ActivityCommentTest.php`, mirrors `ProjectCommentTest` |
| Integration | `ActivityCommentRepository::getComments` ordering; cascade delete leaves no orphan rows | Kernel test with fixtures |
| Functional | Workspace renders with/without `{activity}`; panel 1 excludes other projects and globals; cross-project `{activity}` → 404; `comments`-less user sees no form/thread; POST persists with `createdBy` then 302; `admin_activity` still renders | `tests/Controller/ActivityWorkspaceControllerTest.php` extending `AbstractControllerBaseTestCase`, following `ActivityBoardControllerTest` |
| Regression | `project_details` activities tab and `project_board` unchanged | Existing `ProjectControllerTest` / `ActivityBoardControllerTest` must stay green untouched |

## Threat Matrix

| Boundary | Applicability | Design response |
|---|---|---|
| Documentation-like paths | N/A — no file classification or execution |
| Git repository selection | N/A — no VCS invocation |
| Commit state | N/A — no VCS invocation |
| Push state | N/A — no VCS invocation |
| PR commands | N/A — no process/subprocess integration |

No shell, subprocess, VCS, or executable-classification boundary exists. The
one adversarial boundary is HTTP authorization (IDOR on enumerable ids),
covered by the D7 gates and the 404 guard above, with the RED tests named in
the Testing Strategy row "Functional".

## Migration / Rollout

Single additive `CREATE TABLE`; no existing table altered, no data
transformed, no feature flag. `down()` drops the FKs then the table.

## Open Questions

- [x] Panel-1 page size — resolved as 25 (D4), controller const, not configurable.
- [x] Panel-1 pagination route — resolved as query string (D3).
- [x] Picker menu-entry permission guard — moves to the project guards; noted above as an item the proposal left open.
- [ ] `pinned` stays in the schema unused this change; confirm no reviewer expects it dropped from the DDL (dropping it would break `CommentTableTypeTrait` reuse).
