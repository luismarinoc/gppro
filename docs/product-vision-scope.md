# Stage 2 Product Vision and Scope

## Executive intent

gppro should become an integrated internal platform for running a professional
services company. It should connect project and time work, financial control,
and internal operations without pretending that these are one undifferentiated
domain.

This is a product direction and scope proposal. The first end-to-end journey
has now been approved as the planning anchor, but this does not approve a data
model, workflow detail, permission change, migration, or implementation plan.

## Product vision

Give every person a clear view of the work they own, the money attached to it,
and the internal actions needed to keep the company moving. Give managers a
reliable cross-domain view without forcing them to reconcile separate tools.

The product should feel like one working environment with three legible areas:

1. **Work**: projects, activities, schedules, timesheets, budgets, and reports.
2. **Money**: expenses, approvals, invoices, quotations, rates, and financial
   reporting.
3. **Company**: people, teams, policies, shared tasks, internal requests, and
   operating visibility.

The domains should share identity, navigation, search, notifications, and
context where useful. They should not silently share business rules.

## Target users

| User | Primary job | Needs from an integrated platform |
|------|-------------|----------------------------------|
| Individual contributor | Record work and complete assigned internal tasks | Fast personal actions, a trustworthy personal view, and low navigation cost |
| Project or team lead | Keep delivery on track | Project health, team workload, time and budget context, and actionable exceptions |
| Finance or operations specialist | Control spend and administrative flow | Review queues, traceable financial records, and consistent status visibility |
| Company administrator | Configure access and keep the system usable | Permission-aware administration, stable configuration, and operational diagnostics |
| Leadership | Understand company health | Cross-domain summaries, trends, risks, and links to source records |

These are working personas, not a final entitlement matrix. The product must
not infer that a job title grants access.

## Core domains and boundaries

| Domain | Owns | May consume | Must not own |
|--------|------|-------------|---------------|
| Project and time management | Customers, projects, activities, schedules, timesheets, time budgets, delivery reporting | People, approved expenses, invoice status | Expense policy, payroll, or company-wide approval rules |
| Financial and expense management | Expenses, expense review, quotations, invoices, rates, financial reporting | Project context, user identity, time records where explicitly relevant | Project execution state or employee management |
| Internal company operations | Users, teams, internal tasks or requests, operating notices, company-level coordination | Project and financial summaries where permission allows | Customer billing records or timesheet ownership |
| Shared platform services | Identity, authorization, navigation, search, notifications, auditability, preferences | Domain records through explicit contracts | Domain-specific approval or calculation rules |

The boundaries describe ownership, not a proposed database decomposition. Exact
cross-domain contracts and reporting semantics are decisions still needed.

## First-slice scope proposal

The first slice should prove that the three domains can work together in one
permission-safe shell. It should be narrow enough to validate with real users,
but broad enough to test the product thesis.

### In scope for the first slice

- A shared authenticated application shell with clear Work, Money, and Company
  navigation groups.
- A role- and permission-aware home experience that links summary signals to
  the underlying domain pages.
- A project and time path covering the existing project context, personal work
  capture, and a manager's delivery view.
- A financial path covering expense capture or review and a clearly linked
  financial outcome. The first journey must use the existing expense allocation
  mechanism, supported by the current evidence in
  `src/Expense/AllocationSplitter.php`,
  `src/Expense/AllocationPercentageValidator.php`, and
  `src/Entity/Expense.php`; implementation must verify its semantics before
  exposing the flow.
- An internal operations path covering people or team coordination and one
  repeatable internal operating action. The exact action is a product decision.
- Shared patterns for search, filters, status, empty states, error states,
  timestamps, money, and duration.
- Instrumentation or lightweight feedback capture for navigation success,
  task completion, and permission-related confusion.

### Approved first end-to-end journey

The approved first journey is **project → time entry → expense association →
approval → profitability/operational view**. Its detailed requirements,
permission assumptions, source-record links, and unresolved decisions are
defined in [`docs/first-slice-journey.md`](first-slice-journey.md). Approval of
the journey selects the existing expense allocation mechanism for the expense-
to-project association; it does not propose a direct new association or create
new business rules, authorize application changes, or resolve profitability
semantics.

### Deliberately not fixed yet

The following require product decisions before detailed requirements are
written:

- The detailed rules, permissions, metrics, and migration treatment for the
  approved first end-to-end journey, including verification that the existing
  expense allocation is explicit, reversible, permission-safe, and has defined
  behavior for partial allocation.
- Whether the current allocation semantics evidenced in
  `src/Expense/AllocationSplitter.php`,
  `src/Expense/AllocationPercentageValidator.php`, and
  `src/Entity/Expense.php` are sufficient for the first journey. No direct new
  expense-to-project association is proposed.
- Whether the home experience is a configurable dashboard, a role-based brief,
  or both.
- Which cross-domain metrics are authoritative and how they are calculated.
- Which approval states, delegations, escalation paths, and audit events are
  required.
- Whether internal operations includes HR-sensitive information, and how that
  information is segmented.
- Which existing entities and routes are source records for the first slice.

## Non-goals

- Rebuilding every Kimai screen or preserving Kimai's product information
  architecture. **Kimai compatibility is not a product goal.**
- Inventing new business rules during the visual or navigation work.
- Replacing the existing permission model with role-name assumptions.
- Building payroll, full HRIS, accounting-ledger, procurement, CRM, or general
  ERP capabilities in the first slice.
- Creating a separate frontend framework or discarding the existing Twig,
  Bootstrap, Tabler, Sass, and Encore foundation as a prerequisite.
- Treating a dashboard as the product. The dashboard must lead to useful work.
- Renaming historical identifiers or rewriting migration history as part of
  product discovery.

## Navigation implications

The current application already has a permission-aware `MenuSubscriber` and
separate menu areas for application, administration, and system functions. The
future information architecture should build on that behavior:

- Keep visibility and route access governed by permissions, not by the visual
  grouping alone.
- Use Work, Money, and Company as user-facing information groups, while keeping
  administration and system diagnostics distinct.
- Keep a stable personal shortcut area for the user's most frequent actions.
- Make the current domain and record context visible in page titles, breadcrumbs,
  and actions.
- Avoid duplicating approval destinations. A shared review area may aggregate
  work, expense, and invoice queues, but each item must link to its owning
  domain and retain its permission checks.
- Treat responsive navigation as a first-class path. Collapsing the sidebar
  must not remove access to search, identity, or urgent actions.

`templates/base.html.twig` already provides the application shell, page title,
actions, toolbar extension points, flash handling, and responsive sidebar
entrypoint. These are infrastructure to preserve, not a reason to bypass the
existing shell.

## Existing evidence to preserve

- `README.md` establishes time tracking, invoicing, expenses, reporting,
  multi-user operation, permissions, and responsive design as existing product
  territory.
- `templates/base.html.twig` provides the shared Twig shell and Encore entry
  loading.
- `templates/dashboard/index.html.twig` and `templates/dashboard/grid.html.twig`
  provide widget-based dashboards, including configurable grid behavior.
- `templates/macros/widgets.html.twig` centralizes actions, labels, avatars,
  alerts, formatting, and empty states.
- `src/Widget/WidgetService.php` and the GpproLoader plugin system provide
  reusable extension points.
- `src/EventSubscriber/MenuSubscriber.php` applies permission-aware menu
  visibility and groups existing work, money, approval, administration, and
  system routes.
- `assets/sass/variables.scss` and the Sass entrypoints provide the current
  visual token and build foundation.
- `webpack.config.js` defines Encore bundles for the app, charts, dashboard,
  calendar, board, and other focused surfaces.

## Open product decisions

| Decision | Why it matters | Owner / timing |
|----------|----------------|----------------|
| How should the approved first end-to-end journey be verified? | Prevents three disconnected mini-products and unverified cross-domain assumptions | Product, finance, and engineering before implementation |
| What does “internal operations” include? | Sets privacy, data, and navigation boundaries | Leadership and operations |
| What is the source of truth for money and time metrics? | Prevents conflicting dashboard numbers | Product and finance |
| Does the existing expense allocation support the first journey? | Verifies explicitness, reversibility, permissions, and behavior for partial allocation without introducing a direct new association | Product, finance, and engineering before implementation |
| How much dashboard personalization is needed? | Determines configuration and support cost | Product, after first user interviews |
| What data must be preserved from current gppro installations? | Defines migration and rollout risk | Engineering and operations |
| Is data preservation required for all existing records, preferences, and identifiers? | Compatibility is not the goal, but lossless transition may be | Explicit decision required |
| Which existing permissions map to the new domain groups? | Prevents accidental access expansion | Security and product |
| What audit trail is required for financial and internal actions? | Determines trust and compliance expectations | Finance, operations, and security |
| Which integrations are needed in the first slice? | Constrains contracts and delivery scope | Product, after journey selection |

## Stage 2 acceptance gate

Stage 2 planning is ready for detailed discovery when the team can answer the
open decisions above, name one primary journey for each target user group, and
show that the proposed navigation preserves existing permission behavior. No
application source change is implied by this document.
