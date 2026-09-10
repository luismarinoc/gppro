# Proposal: GPPro calendar visual consistency

## Problem statement

The authenticated Calendar is a high-frequency GPPro operational screen, but its presentation has not been brought into the shared visual system used by the recently completed finance workflows. It still relies mostly on generic Bootstrap/Tabler card composition and a minimal calendar stylesheet, so filters, drag-and-drop source lists, FullCalendar controls, events, current/weekend states, and narrow-width behavior do not feel as cohesive as the upgraded expense, quotation, approval, and invoice workflows.

## Proposed change

Introduce a presentation-only calendar workflow layer for the authenticated user calendar:

- wrap the calendar page in a scoped GPPro workflow root for calendar-specific styling
- visually group the existing filter/source sidebar as workflow surfaces
- improve local containment and readability for drag-and-drop source lists
- align FullCalendar toolbar, event, today/weekend, selection, and focus states with `--gp-*` tokens
- preserve readable behavior at 360px, 768px, and 1024px+ in light/dark themes
- add route/rendering regression assertions that protect the DOM and JavaScript contracts

## Scope

Included surfaces:

- `templates/calendar/user.html.twig`
- `templates/calendar/drag-drop.html.twig` only if needed for additive semantic hooks
- `assets/sass/calendar.scss`
- `tests/Controller/CalendarControllerTest.php`
- OpenSpec artifacts for this change

Included states:

- calendar shell with and without the sidebar
- filter form surface
- drag-and-drop source lists when available
- FullCalendar toolbar and event states
- weekend/current/selected/focus states
- responsive local containment

## Non-goals

This change MUST NOT alter:

- routes, query parameters, or controller behavior
- `#timesheet_calendar`
- `#calendar-form`
- FullCalendar initialization, options, permissions, event sources, or URLs
- drag/drop selector/data contracts: `.external-events`, `.external-event`, `.draggable`, `data-method`, `data-route`, `data-route-replacer`, `data-entry`
- timesheet create/edit/update/delete behavior
- Google Calendar or other calendar source integrations
- persistence, migrations, APIs, permissions, or translations
- generated assets under `public/build/`

## Review workload

Expected changed lines: 160–320 in one slice.

The work should fit below the 400-line review budget with a 360-line safety margin. If exploration during design reveals broader JS behavior changes or more than one route family, split before implementation rather than expanding the slice.

## Validation plan

- Focused RED/GREEN controller evidence in `tests/Controller/CalendarControllerTest.php`
- `APP_DEBUG=1 composer linting`
- `./phpstan.sh test`
- `./php-cs-fixer.sh core`
- `pnpm build`, followed by restoring generated `public/build/` output
- read-only browser evidence against `https://gppro.tbema.net` after deployment, using `.env.local` credentials without printing secrets, at 360px, 768px, and 1024px in light/dark themes

## Acceptance summary

The Calendar is accepted when its shell, filter/sidebar, drag sources, and FullCalendar states use the shared visual vocabulary, remain responsive without page-level horizontal overflow, preserve all DOM/JS/server contracts, and keep existing timesheet interactions server-authoritative and unchanged.
