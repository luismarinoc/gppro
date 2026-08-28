# Stage 2 Design Direction

## Status

**Proposal for review.** This document sets a design direction for future gppro
work. It is not an approved implementation specification and does not authorize
changes to templates, Sass, assets, JavaScript, configuration, or generated
bundles.

## Visual thesis

gppro should feel like a calm operations desk: clear enough for a quick check,
structured enough for serious financial work, and distinctive enough to belong
to one company rather than to a generic SaaS template.

The direction is **quietly industrial editorial**. Use disciplined grids,
strong left alignment, thin rules, selective color, and typographic hierarchy.
Let data and actions carry the visual weight. Avoid decorative noise, generic
gradient dashboards, and identical card grids.

## Design principles

1. **Orientation before decoration.** Every page should answer where the user is,
   what needs attention, and what can happen next.
2. **One platform, three working modes.** Work, Money, and Company share a
   grammar, while domain signals retain distinct labels and semantic colors.
3. **Progressive density.** Overview pages breathe. Tables, queues, and detail
   views become denser only where the task demands comparison.
4. **Source records stay visible.** Summary numbers link to the records and
   filters that produced them.
5. **Permission is part of the interface.** Hidden actions, disabled actions,
   and empty authorized views need clear, consistent treatment.
6. **Reuse infrastructure, not visual accidents.** Preserve Twig macros,
   widgets, plugin hooks, permission behavior, and Encore entry boundaries while
   replacing inconsistent presentation patterns over time.
7. **Motion explains state.** Use short transitions for navigation, loading, and
   completion. Never use motion to make routine work feel theatrical.

## Information architecture

### Primary navigation

- **Home**: role-aware brief, recent work, review queue, and next actions.
- **Work**: timesheets, calendar, projects, activities, reports, and delivery
  views.
- **Money**: expenses, approvals, quotations, invoices, rates, and financial
  views.
- **Company**: people, teams, internal actions, and operating views.
- **Administration**: customers, projects, activities, users, teams, and system
  configuration, shown only when permitted.

The labels are a proposed user-facing grouping, not a route rename. Existing
routes and permission checks must remain authoritative until an explicit
migration plan exists.

### Page composition

Use a consistent page frame:

1. Context line or breadcrumb.
2. Left-aligned page title and one-sentence purpose.
3. Primary action and relevant filters.
4. Main work surface, usually a table, queue, calendar, board, or focused form.
5. Supporting context and links to source records.

The current `base.html.twig` page-title, page-action, toolbar, flash, and theme
event blocks are the natural integration points. Do not create a parallel shell.

## Color and token strategy

Use a restrained, tinted-neutral system with one brand accent and semantic
status colors. The current `#1B3A6B` GPartner Consulting blue and light blue
surface are useful evidence, but the values below are proposed tokens to be
validated for contrast and brand fit before implementation.

| Token | Proposed value | Use |
|-------|----------------|-----|
| `--gp-bg` | `oklch(97% 0.012 250)` | App background |
| `--gp-surface` | `oklch(99% 0.006 250)` | Primary work surface |
| `--gp-surface-muted` | `oklch(93% 0.018 250)` | Secondary panels and selected regions |
| `--gp-ink` | `oklch(25% 0.035 250)` | Main text |
| `--gp-ink-muted` | `oklch(48% 0.028 250)` | Supporting text |
| `--gp-brand` | `oklch(34% 0.105 255)` | Navigation, primary actions, active state |
| `--gp-brand-soft` | `oklch(91% 0.035 250)` | Brand-tinted background |
| `--gp-line` | `oklch(82% 0.025 250)` | Dividers and field borders |
| `--gp-positive` | `oklch(48% 0.12 150)` | Confirmed or healthy |
| `--gp-warning` | `oklch(58% 0.13 85)` | Attention required |
| `--gp-danger` | `oklch(48% 0.14 25)` | Blocking or rejected |

The values above are proposals, not implementation values. Validate them for
contrast and brand fit before adoption. Semantic colors must never be the only
way to communicate meaning. Preserve the existing time-off and attendance
distinctions while mapping them to a documented semantic palette.

Color should be rare and meaningful. Do not use purple gradients, neon glows,
gradient text, or a different accent for each domain.

## Typography

This is a product UI, not an editorial microsite. Use a sans-serif system with
clear numeric behavior:

- **UI and body**: `Instrument Sans`, with a local or approved fallback stack.
- **Headings**: `Satoshi`, used sparingly for page titles and major section
  labels.
- **Data and technical metadata**: `Geist Mono` or `JetBrains Mono`, with
  tabular numerals where comparison matters.

These font choices require an asset and licensing decision before implementation.
Do not add a web-font dependency as part of Stage 2 planning. Body copy should
stay within roughly 65 to 75 characters per line where the layout permits.
Use weight and scale for hierarchy, not oversized headings.

## Spacing, radius, and elevation

- Base spacing unit: 4px, with a default rhythm of 8px increments.
- Page gutters: 24px on desktop, 16px on compact screens.
- Main section gaps: 24px to 32px. Dense table rows: 8px to 12px vertical
  padding, subject to scanability testing.
- Radius: 6px for controls and fields, 8px for work surfaces, 12px only for
  prominent grouped areas. Avoid making every element a pill.
- Elevation: prefer borders, dividers, and whitespace. Use one soft tinted
  shadow only when a surface must sit above another surface, such as a menu,
  drawer, or modal.
- Avoid nested cards. A table inside a card inside a dashboard card is a visual
  tax with no product value.

## Dashboard direction

The dashboard should be a role-aware operating brief, not a wall of metrics.
The existing widget system and `WidgetService` are valuable reusable
infrastructure, and the configurable GridStack dashboard should remain an
available path where personalization is proven useful.

Proposed composition:

- A compact “now” band for urgent approvals, blocked work, or missing action.
- One primary domain view selected by the user's role or current context.
- A secondary split view for the relationship between time, money, and company
  operations.
- Recent activity and source links, shown only when they help the next action.
- Empty states that explain how to populate the view, rather than blank boxes.

Avoid the hero-metric template, identical three-column tiles, and charts without
a decision attached. Every visual summary needs a label, time range, source
link, and an explanation of what action it supports.

## Reusable component priorities

Prioritize shared primitives before domain-specific decoration:

1. Page frame, context line, title, and action cluster.
2. Permission-aware navigation group and active-state treatment.
3. Status, severity, and review-queue patterns.
4. Metric summary with source link and time range.
5. Filter bar with compact and mobile layouts.
6. Data table, responsive table fallback, sortable heading, and pagination.
7. Empty, loading, error, and no-access states.
8. Form field, helper text, validation, and save-state patterns.
9. Timeline, activity feed, avatar, tag, and entity-link patterns.
10. Dashboard widget frame and configuration affordance.

Extend `templates/macros/widgets.html.twig` and existing Tabler components only
after their responsibilities are clear. Keep domain rules outside presentation
macros.

## Responsive behavior

- Desktop uses the existing vertical navigation model when appropriate, with a
  stable content column and predictable page gutters.
- Tablet collapses secondary controls before collapsing primary actions.
- Mobile uses a single-column work surface, horizontal scrolling only for data
  that cannot be meaningfully reflowed, and a persistent route to navigation,
  search, profile, and urgent actions.
- Tables must define which columns remain visible, which become row detail, and
  which can scroll horizontally. Do not simply shrink unreadable tables.
- Dashboard widgets should reflow from four columns to two and then one using
  the existing responsive behavior as a baseline, with an explicit review of
  widget minimum widths.
- Touch targets should be at least 44 by 44 CSS pixels. Avoid hover-only actions.

## Accessibility

- Meet WCAG 2.2 AA contrast for body text, controls, focus indicators, and
  semantic status colors.
- Preserve visible keyboard focus and provide a skip link or equivalent route
  to the main content.
- Use headings in a logical order and landmarks for navigation, main content,
  and supporting regions.
- Every icon-only action needs an accessible name. Do not rely on tooltips alone.
- Announce dynamic flash, loading, and save states through appropriate live
  regions without interrupting unrelated work.
- Pair status color with text, icon shape, or explicit label.
- Respect reduced-motion preferences and keep all workflows usable without
  animation.

## Migration approach from Tabler

Migration should be incremental and reversible:

1. Inventory existing Tabler components, Sass variables, macros, widget frames,
   and page-level exceptions.
2. Define gppro tokens as a compatibility layer over the current Sass and Tabler
   variables. Validate contrast before changing values.
3. Pilot the page frame and one representative surface from each domain. Use
   screenshots and task tests to compare orientation, density, and permission
   behavior.
4. Replace repeated visual patterns through shared macros or components, not
   page-by-page overrides.
5. Keep `GpproLoader`, plugin registration events, `WidgetService`, Encore entry
   points, and permission-aware `MenuSubscriber` behavior intact.
6. Migrate high-value screens in slices. Leave specialized calendar, board,
   invoice, export, and print surfaces on their own bundles until their
   constraints are understood.
7. Remove compatibility styles only after no supported screen depends on them.

The goal is not Tabler compatibility as a product promise. Tabler is the
current implementation foundation; the migration should preserve working
infrastructure while giving gppro its own visual language.

## Measurable acceptance criteria

The proposal is ready to move into implementation when the team agrees that:

- At least 90% of sampled authenticated pages expose the same page-frame
  hierarchy: context, title, purpose, action, and work surface.
- A user can reach each permitted primary domain from Home in no more than two
  navigation decisions on desktop and three on mobile.
- No proposed navigation or styling change causes a previously permitted action
  to become visible to an unauthorized user in review scenarios.
- Keyboard users can reach every primary navigation item, page action, filter,
  and table action with a visible focus indicator.
- All sampled body text and interactive controls meet WCAG 2.2 AA contrast, and
  status meaning remains clear without color.
- At 320px, 768px, and 1280px viewport widths, no sampled page has accidental
  horizontal overflow outside an explicitly scrollable data region.
- A dashboard summary links to its source record and states its time range in
  100% of sampled widgets.
- Empty, loading, error, and no-access states are defined for every first-slice
  work surface.
- A first-slice user test can identify the current domain and next action within
  10 seconds on at least 4 of 5 tested tasks.
- The pilot can be rolled back without changing database records, historical
  migrations, or permission semantics.

## Decisions needed before implementation

- Approve the visual thesis and the degree of industrial/editorial character.
- Validate brand ownership, font licensing, and the proposed color values.
- Select the first pilot screens and representative user roles.
- Confirm whether configurable dashboards remain a first-slice requirement.
- Decide the data-preservation policy for existing records, browser preferences,
  identifiers, and migration history. Kimai compatibility is not a product goal,
  but data preservation remains an explicit open decision.
