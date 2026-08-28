# First-Slice End-to-End Journey

This artifact turns the approved first gppro journey into a narrow planning
contract for discovery and implementation review. It does not authorize source
changes, define new business rules, grant access, or approve the visual proposal
in `docs/design-direction.md`.

## Journey objective

Prove that a permitted user can keep one project record connected to recorded
work, an associated expense, an approval checkpoint, and a trustworthy view of
the resulting delivery and financial context.

### Success outcome

For a real project and a permitted test user, the team can:

1. open or select the project;
2. record time against its project and activity context;
3. create or select an expense and associate it with the project;
4. submit the expense for the currently supported approval path;
5. approve or reject it only when the existing permission model allows that
   action; and
6. reach a profitability or operational view whose values state their scope,
   period, status, and links to the underlying project, time, and expense
   records.

The journey succeeds only if denied actions remain denied and if every summary
can be traced back to source records. A visually connected flow is not enough.

## Actors and permission assumptions

These are journey personas, not entitlements. Existing permissions remain the
authority. No job title below grants access.

| Persona | Journey responsibility | Permission assumption | Decision status |
|---|---|---|---|
| Individual contributor | Record their own time and submit an expense when permitted | Existing create/edit/submit checks decide what is available | Must be verified against the current permission matrix |
| Project or team lead | Review project delivery context and permitted team records | Existing visibility rules decide which people, time, and project data appear | Must be verified; no team-wide access is inferred |
| Finance or operations specialist | Review expense queue and apply an allowed approval decision | Existing expense and approval permissions decide queue visibility and actions | Must be verified; approval authority is not implied by the title |
| Leadership | Inspect an authorized profitability or operating summary | Existing reporting and record visibility decide the summary scope | Must be verified; no company-wide visibility is assumed |
| Company administrator | Configure existing access or approval-level settings where already permitted | Existing administration permissions remain authoritative | Explicitly out of the journey unless needed for test setup |

### Permission decisions required before implementation

- Which existing permission checks permit time creation, time editing, expense
  creation, expense association, submission, approval, rejection, and report
  access?
- Can a user see an expense linked to a project without seeing all project time?
- Timesheet approval is an explicit stopped-entry workflow: draft, pending
  approval, approved, or rejected. Existing project/team authorization remains
  authoritative; rejected entries can be edited and resubmitted.
- Which unauthorized states should be hidden, disabled, or shown as an empty
  authorized view? The treatment must be consistent and must not disclose record
  existence.

## Current reusable capabilities

The following are evidence to investigate and reuse, not promises that they
already satisfy the journey:

| Need | Existing evidence | Reuse implication |
|---|---|---|
| Project context | `src/Entity/Project.php`, `src/Controller/ProjectController.php`, `templates/project/` | Start from the existing project record and its established routes and templates |
| Time capture | `src/Entity/Timesheet.php`, `src/Controller/TimesheetController.php`, `src/Form/Type/QuickEntryTimesheetType.php`, `src/Form/Type/QuickEntryWeekType.php`, `templates/timesheet/` | Preserve existing duration, project, activity, validation, and permission behavior while reducing navigation cost |
| Project-aware calendar data | `src/Calendar/TimesheetEntry.php` | Reuse its explicit project and activity context when a calendar surface is selected |
| Expense lifecycle | `src/Entity/Expense.php`, `src/Controller/ExpenseController.php`, `templates/expense/` | Use existing expense status and record pages as the source of truth; do not duplicate lifecycle rules in a summary |
| Expense allocation | `src/Expense/AllocationSplitter.php`, `src/Expense/AllocationPercentageValidator.php`, `src/Entity/Expense.php` | Use the existing allocation mechanism for the first journey; implementation must verify its current semantics before exposing the flow |
| Approval | `src/Entity/ExpenseApproval.php`, `src/Expense/ExpenseApprovalService.php`, `src/Expense/ApprovalLevelResolver.php` | Reuse the existing approval record, level resolution, approve/reject behavior, and audit semantics |
| Approval configuration | `src/Controller/ExpenseApprovalLevelController.php` | Treat approval levels as existing administration behavior; do not change thresholds in this slice |
| Reporting and financial context | `src/Controller/Reporting/ProjectViewController.php`, `src/Controller/Reporting/ProjectDetailsController.php`, `src/Controller/Reporting/ProjectDateRangeController.php`, `src/Reporting/`, `src/Widget/Type/` | Inventory which current reports and widgets can provide source-linked delivery, duration, amount, or project context |
| Shared shell | `templates/base.html.twig`, `templates/macros/widgets.html.twig`, `src/EventSubscriber/MenuSubscriber.php`, `src/Widget/WidgetService.php` | Extend the existing shell and permission-aware menu; do not create a parallel frontend shell |
| Build boundaries | `webpack.config.js`, `assets/sass/variables.scss` | Keep any future presentation work inside existing entrypoints and token foundations |

The repository evidence does not yet establish an authoritative profitability
calculation. “Profitability” is therefore a required product decision, not a
permission to add a formula during implementation.

## Proposed user flow

The flow below is a proposal for a testable sequence. Each step must preserve a
link to its owning source record and expose the current state without implying
access the user does not have.

| Step | Screen or state | User action | Source-record link | Required states |
|---|---|---|---|---|
| 1 | Project context | Select an existing permitted project | `Project` record and project detail route | Loading, no permitted projects, not found, no access |
| 2 | Project work surface | Review project identity, activity choices, current time context, and next action | Project → activities, timesheets, and permitted reports | Empty activity set, validation warning, stale data |
| 3 | Time entry form | Enter duration and required project/activity context | New or edited `Timesheet` record | Default, validation error, save success, save failure, unauthorized |
| 4 | Expense association | Create or select an `Expense`, then associate it with the project through the existing allocation mechanism | `Expense` → existing allocation record → `Project` | No eligible expense, allocation validation error, duplicate/conflict, success |
| 5 | Submission checkpoint | Review amount, currency, project context, evidence, and resulting status before submit | Expense detail and associated project | Draft, pending approval, already submitted, invalid, unauthorized |
| 6 | Approval queue/detail | An authorized reviewer inspects the expense and chooses the existing approve/reject action | Expense → `ExpenseApproval` records and approval history | Pending, approved, rejected, wrong level, conflict, unauthorized |
| 7 | Outcome view | Open a project profitability or operational view and inspect linked time, expense, and approval evidence | Project/report summary → filtered source records | No data, partial data, unavailable metric, stale/failed report |

### Navigation and source-link rules

- The active project and current domain must remain visible in the page title,
  context line, breadcrumbs, and action area.
- A summary value must identify its period, status filter, currency or duration
  unit, and source route. If a value cannot be traced, it must not be presented
  as authoritative.
- Approval destinations may be aggregated in a queue, but the owning expense
  page and its permission checks remain authoritative.
- Back, refresh, validation failure, and deep-link entry must preserve or
  reconstruct the project context without silently changing the selected record.

## Requirements and acceptance criteria

### Work

- A permitted user can select a project and see only activities and time records
  permitted by the existing visibility rules.
- Time entry preserves existing duration, begin/end, break, project, activity,
  and validation semantics.
- A saved time entry links back to its project and remains discoverable from the
  project work surface.
- The project view distinguishes personal data from team or company data.
- Acceptance: a test can create or edit one permitted time entry, refresh, and
  reach the same source record from both the project and time surfaces.

### Money

- An expense can be created or selected only through an existing permitted
  action, with amount and currency preserved as recorded.
- The existing allocation mechanism is the proposed association mechanism for
  this journey. Implementation must verify that its current semantics are
  explicit, inspectable, reversible until existing lifecycle rules say
  otherwise, permission-safe, and correct in its behavior for partial
  allocation. No direct
  new expense-to-project association or new allocation rule is proposed.
- Submission shows the expense status and the approval levels currently
  required by existing logic, without recalculating thresholds in presentation.
- Approval and rejection record the existing decision, actor, timestamp, note,
  and status transitions where the current service provides them.
- Acceptance: an authorized reviewer can follow one expense from draft through
  the available approval outcome, while an unauthorized user cannot perform
  that outcome or infer hidden approval data.

### Company and operations visibility

- The operating view is permission-scoped and distinguishes delivery signals,
  financial signals, and operational signals.
- It shows exceptions that support an action, such as pending approval or
  missing association, rather than presenting an unqualified metric wall.
- Every metric states period, units, status scope, and source links. The formula
  and authority of profitability values must be documented before release.
- Acceptance: a permitted manager or leader can move from the outcome view to
  the project, time, expense, and approval source records used by the view.
- Acceptance: two users with different visibility permissions do not receive
  the same summary unless the underlying records and aggregation scope are
  authorized for both.

## Data preservation and migration questions

No migration is proposed by this artifact. Before implementation, answer:

- Must all existing projects, activities, timesheets, expenses, allocations,
  approval rows, timestamps, notes, currencies, and identifiers be preserved?
- Are historical links between current project, time, and expense records
  complete enough to display, or must the first slice label records as
  unassociated rather than infer a link?
- Must existing expense statuses and approval history remain immutable?
- Are browser preferences, dashboard layouts, saved filters, exports, and
  plugin-provided fields in scope for preservation?
- Is lossless transition required for every installation, or only for a defined
  pilot dataset?
- Can the first slice ship behind a reversible presentation and routing change
  that leaves database records, historical migrations, and identifiers intact?
- What is the rollback plan if a metric, association, or permission mapping is
  found to be wrong after release?

Recommended default for decision review: preserve existing records and
identifiers, avoid backfilling uncertain relationships, label incomplete legacy
context, and make any read-model or presentation change reversible. This is a
recommendation, not an approved migration policy.

## UX and design acceptance criteria

The visual thesis in `docs/design-direction.md` remains a **proposal for review**.
The following criteria align the journey with that proposal without treating its
tokens, fonts, or industrial/editorial character as approved implementation:

- Each screen follows the proposed page-frame order: context, title and purpose,
  action/filter area, primary work surface, then supporting source links.
- Work, Money, and Company are legible as modes, while status labels and domain
  rules remain explicit rather than being conveyed by color alone.
- The project context is visually stable across time entry, expense
  association, approval, and outcome views.
- Summary values are quiet, labeled, period-scoped, and source-linked. Avoid a
  dashboard made of identical metric cards or charts without a decision attached.
- Forms expose labels above fields, helper text where needed, inline validation,
  save state, and a recovery path after failure.
- Loading uses layout-matched skeletons; empty, error, and no-access states
  explain the next safe action without disclosing unauthorized records.
- Keyboard focus, landmarks, logical headings, accessible names, live feedback,
  reduced-motion behavior, and WCAG 2.2 AA contrast are defined for every step.
- At 320px, 768px, and 1280px, the journey remains usable without accidental
  overflow. Dense tables define which columns remain visible or become detail.
- Touch targets are at least 44 by 44 CSS pixels, and no critical action relies
  on hover.
- Motion communicates navigation, loading, save, or completion state only. It
  must not make financial review theatrical or delay routine work.

Before implementation, product and design must explicitly approve or revise the
visual thesis, proposed tokens, typography, and pilot screens.

## Incremental and reversible implementation slices

1. **Evidence and contract inventory**: map current routes, permissions,
   entities, approval transitions, report calculations, and source links. No
   behavior change.
2. **Journey fixtures and permission matrix**: define a small representative
   dataset and test personas using existing grants only. Keep the fixture and
   matrix separate from production data.
3. **Project-to-time continuity**: improve the path from project context to
   existing time entry and back. Rollback is a route/template presentation
   revert with no schema change.
4. **Expense association checkpoint**: expose the existing association mechanism
   with explicit validation and source links. Stop if the data model cannot
   represent the relationship without inference.
 5. **Approval continuity**: connect the expense detail and existing approval
 service/queue, preserving current thresholds, decisions, audit rows, and
 permission checks. A rejected expense remains editable; resubmission starts a
 new approval attempt, recalculates and freezes current CLP allocation amounts
 and required approval levels, resets current progress, and returns the expense
 to pending approval. Prior approval rows remain immutable history and prior
 approvers remain subject to the existing four-eyes rule.
6. **Operational outcome read surface**: first present existing authoritative
   report data with links. Add a profitability metric only after its formula,
   currency, period, and owner are approved.
7. **Shell and visual pilot**: pilot the approved design direction on the journey
   page frame and one representative surface per domain. Keep the existing Twig,
   Bootstrap, Tabler, Sass, Encore, widget, and menu boundaries.
8. **Hardening and rollout**: run permission, migration-preservation, UX,
   accessibility, observability, and rollback checks before expanding beyond the
   pilot.

Each slice must be independently reviewable, behind an explicit rollout
decision where appropriate, and reversible without rewriting migrations or
changing historical records.

## Observability, testing, security, and documentation

### Observability

- Record journey step completion, abandonment, validation failure, report
  failure, and permission-denied events with request correlation and coarse
  identifiers only.
- Do not log amounts, notes, credentials, tokens, personal data, or full record
  payloads unless an approved diagnostic policy explicitly permits it.
- Measure time to first project context, time to saved time entry, association
  success, approval queue resolution, and source-link failure.
- Track metric freshness and calculation version when an outcome view is
  introduced.

### Testing

- Add unit and integration coverage for every new domain service or rule, with
  boundary cases for duration, currency, allocation, approval levels, and
  status transitions.
- Add authorization tests for each persona and each source-record link,
  including direct URL access and hidden-record cases.
- Add end-to-end journey tests for happy path, rejection, validation failure,
  refresh/back navigation, stale records, empty data, and partial visibility.
- Add responsive, keyboard, screen-reader-state, reduced-motion, and contrast
  checks for the pilot surfaces.
- Verify report values against their source records and document known rounding,
  currency, timezone, and period boundaries.

### Security

- Preserve existing authentication, authorization voters, route checks, CSRF
  protection, and permission-aware menu behavior.
- Never use persona names or visual groups as authorization decisions.
- Prevent cross-project data leakage through filters, exports, deep links,
  cached summaries, and aggregate calculations.
- Treat expense notes, descriptions, uploads, and user-controlled labels as
  untrusted input and preserve existing output escaping and validation.
- Apply the Stage 1 deployment discipline from `docs/security-stage-1.md`:
  secrets must not enter logs, trusted proxies must remain narrow, and runtime
  diagnostics must not expose sensitive record data.

### Documentation

- Maintain a source-record map, permission matrix, event vocabulary, metric
  definitions, migration decision, and rollback procedure beside the approved
  implementation plan.
- Document any route, template, API contract, report, or audit-event change in
  the relevant technical artifact.
- Keep user-facing help focused on the actual journey and status meanings, not
  on unapproved ERP capabilities.

If an API or asynchronous integration is introduced later, design it around
resource-oriented nouns, explicit authorization, stable versioning, pagination
for collections, structured errors, and source links. Do not create an API
surface merely to move presentation concerns between screens.

## Unresolved product decisions

1. Does the existing expense allocation mechanism support the first journey as
   the expense-to-project association, with its current semantics remaining
   explicit, inspectable, reversible, permission-safe, and correct for partial
   allocation? The implementation must verify the evidence in
   `src/Expense/AllocationSplitter.php`,
   `src/Expense/AllocationPercentageValidator.php`, and
   `src/Entity/Expense.php`. No direct new expense-to-project association is
   proposed.
2. Does the approval checkpoint cover expenses only, or also time records?
3. What is the authoritative profitability definition, including revenue/rate
   source, cost source, currency conversion, period, rounding, and treatment of
   unapproved expenses?
4. Which existing reports or widgets are authoritative for operational status?
5. What is the minimum permission set for each journey step, and how should
   partial visibility be represented?
6. What data and identifiers must be preserved for the pilot and for later
   installations?
7. Is the role-aware brief required for this journey, or is a project-centered
   outcome view sufficient for the first pilot?
8. Which visual elements from the design proposal are approved for the pilot?

### Recommended next decision

Approve the **source-of-truth and permission contract** before selecting screens
for implementation: verify the existing allocation semantics and permission
checks for one pilot dataset, confirm whether approval is expense-only, and
confirm the authoritative profitability/report definition. Without that
verification, a polished flow could connect records or display numbers that the
business does not actually trust.
