# Exploration: GPPro calendar visual consistency

## Context

The completed GPPro visual consistency work now covers expenses, quotations, approvals dashboard, invoices, invoice payment approval administration, and milestone invoicing. A read-only follow-up exploration compared remaining operational surfaces and ranked Calendar as the strongest next candidate.

## Candidate ranking

1. **Calendar** — recommended next target.
   - High-frequency operational surface under `templates/calendar/`.
   - Current presentation uses generic cards/rows and minimal `assets/sass/calendar.scss` styling.
   - Does not yet use the shared `--gp-*` workflow vocabulary established by recent finance work.
   - Expected scope fits one bounded slice under the 400-line review budget.
2. **Reporting** — valuable but broader, likely 2–3 slices across multiple report families.
3. **Activity workspace** — small and feasible, but lower impact than Calendar.
4. **User/team/system configuration** — useful but touches security/admin-sensitive surfaces and should require explicit scope approval.
5. **Customer/project/activity/timesheet lists** — deprioritized because many already have dedicated semantic styling; Calendar is the visible timesheet-adjacent gap.

## Evidence read

- `templates/calendar/user.html.twig`
- `templates/calendar/drag-drop.html.twig`
- `assets/sass/calendar.scss`
- `tests/Controller/CalendarControllerTest.php` exists as the main route/rendering test target.
- `tests/Calendar/CalendarSourceTest.php` and `tests/Calendar/CalendarQueryTest.php` exist for non-presentation calendar behavior.
- `openspec/specs/gppro-visual-system/spec.md` and `assets/sass/_workflow.scss` provide the shared visual reference.

## Current surface observations

`templates/calendar/user.html.twig` renders:

- optional filter form with `id="calendar-form"`
- optional drag-and-drop source lists using `.external-events`, `.external-event`, `.draggable`, `data-method`, `data-route`, and `data-route-replacer`
- the FullCalendar target `#timesheet_calendar`
- extensive inline JavaScript configuration for `GpproCalendar`, event sources, permissions, create/edit/action URLs, and toolbar class adjustments

`assets/sass/calendar.scss` is currently minimal:

- `.calendar-entry` list/text formatting
- `.draggable { cursor: grab; }`
- `#timesheet_calendar` Bootstrap variable remapping
- weekend background color for `.fc-day-sat` and `.fc-day-sun`

## Recommended change

- **Change name:** `gppro-calendar-visual-consistency`
- **Goal:** make the authenticated calendar shell visually consistent with the GPPro workflow system while preserving FullCalendar and timesheet behavior.

## Non-goal boundaries

Do not change:

- routes or query parameters
- `#timesheet_calendar`
- `#calendar-form`
- FullCalendar initialization/options/event-source semantics
- drag/drop selector or data contracts: `.external-events`, `.external-event`, `.draggable`, `data-method`, `data-route`, `data-route-replacer`, `data-entry`
- timesheet create/edit/update/delete behavior
- Google/source integrations
- permissions or persistence
- translations, backend services, APIs, or migrations
