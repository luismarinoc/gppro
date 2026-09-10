# Implementation Tasks: GPPro calendar visual consistency

## Review Workload Forecast

| Field | Value |
| ------- | ------- |
| Estimated changed lines | 235–320 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Allowed Edit Surfaces

| File | Permitted change |
| --- | --- |
| `templates/calendar/user.html.twig` | Add only calendar workflow/surface/containment classes and wrappers. |
| `templates/calendar/drag-drop.html.twig` | Add optional presentation hooks only when necessary. |
| `assets/sass/calendar.scss` | Add calendar-scoped GPPro workflow, state, focus, and responsive-containment rules. |
| `tests/Controller/CalendarControllerTest.php` | Add focused rendering and DOM-contract assertions using existing fixtures. |

Do not edit controllers, JavaScript, routes, permissions, translations, integrations, shared Sass tokens, dependencies, migrations, plugins, or generated assets. Preserve `#timesheet_calendar`, `#calendar-form`, the complete inline JavaScript block, FullCalendar options/event sources/URLs, and `.external-events`, `.external-event`, `.draggable`, `data-method`, `data-route`, `data-route-replacer`, and `data-entry` contracts.

## Implementation

### RED

- [x] **RED** — In `tests/Controller/CalendarControllerTest.php`, add failing populated-calendar rendering assertions for the planned `.gp-workflow--calendar` shell, additive filter/source surface hooks, local calendar containment wrapper, and the existing IDs, drag selectors/data payloads, and Google configuration; run `vendor/bin/phpunit tests/Controller/CalendarControllerTest.php` and record the expected assertion failure, command, and exit status (an infrastructure failure is not RED). <!-- sdd-owner: implementation -->

### GREEN

- [x] **GREEN** — In `templates/calendar/user.html.twig`, add the scoped `gp-workflow gp-workflow--calendar` root and minimal additive hooks/wrapper required by the RED assertions; retain all conditions, form fields, target IDs, embeds, stylesheet/script tags, and inline JavaScript byte-for-byte except surrounding presentation markup. <!-- sdd-owner: implementation -->
- [x] **GREEN** — In `templates/calendar/drag-drop.html.twig` only if the existing markup cannot be styled from the parent hooks, add minimal additive presentation classes while preserving source filtering/slicing, `.drag-and-drop-source`, selectors, attributes, tooltip behavior, and container/item relationships; otherwise leave this file unchanged. <!-- sdd-owner: implementation -->
- [x] **GREEN** — In `assets/sass/calendar.scss`, add rules scoped under `.gp-workflow--calendar` that use existing `--gp-*` tokens for shell/sidebar/filter/source surfaces, toolbar, event spacing/radii/focus, today/weekend/selection precedence, disabled/hover/focus states, and `min-width: 0` plus wrapper-local `max-width: 100%; overflow-x: auto` containment; do not recolor source events, suppress document overflow, or clip popovers. <!-- sdd-owner: implementation -->
- [x] **GREEN** — Rerun `vendor/bin/phpunit tests/Controller/CalendarControllerTest.php` and record passing focused evidence that the new presentation hooks exist and the existing IDs, selector relationships, decoded drag payloads, and Google configuration remain intact. <!-- sdd-owner: implementation -->

### TRIANGULATE

- [x] **TRIANGULATE** — Extend `tests/Controller/CalendarControllerTest.php`, using verified existing fixtures/configuration, to cover filter-present plus genuinely sidebar-absent rendering and retain existing authorization/super-admin coverage; prove the absence case from server-rendered data rather than CSS simulation, then rerun the focused controller test. <!-- sdd-owner: implementation -->

### REFACTOR

- [x] **REFACTOR** — Consolidate `assets/sass/calendar.scss` selectors so every new rule stays under `.gp-workflow--calendar`, remove redundant presentation hooks from `templates/calendar/user.html.twig` or `templates/calendar/drag-drop.html.twig`, and rerun `vendor/bin/phpunit tests/Controller/CalendarControllerTest.php` without changing the preserved DOM/JavaScript contracts. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Run `APP_DEBUG=1 composer linting`, `./phpstan.sh test`, and `./php-cs-fixer.sh core`; resolve in-scope failures and rerun the focused `tests/Controller/CalendarControllerTest.php` after any correction. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Run `pnpm build`; restore any generated `public/build/` output before delivery and verify it is excluded from the implementation diff. Run `composer tests-unit` as final broader validation when the integration environment is available, noting that it does not replace the focused controller evidence. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Recount the complete delivery diff, including OpenSpec artifacts: pause before apply under `ask-on-risk` if it exceeds the 360-line safety target or risks 400 changed lines; do not select a chain strategy or size exception without parent approval. <!-- sdd-owner: implementation -->

## Parent Lifecycle and Browser Evidence Gates

- [x] Before deployment, start or reuse bounded review of the completed diff, confirm only the allowed edit surfaces changed, verify `public/build/` is restored, and require the recorded RED/GREEN/TRIANGULATE/REFACTOR command evidence before lifecycle advancement. <!-- sdd-owner: parent -->
- [x] After deployment, collect read-only browser evidence against `https://gppro.tbema.net` at 360px, 768px, and 1024px in light and dark themes: toolbar, events, weekend/today/selection, hover/disabled/focus, sidebar-present/absent layouts, and document-versus-local horizontal overflow; use `.env.local` credentials without printing secrets and do not perform mutating interactions. <!-- sdd-owner: parent -->
- [x] If browser evidence exposes a visual or containment defect, return the slice to implementation with the affected viewport/theme/state; if scope grows beyond the forecast, stop for the `ask-on-risk` delivery decision rather than applying an unapproved chain or size exception. <!-- sdd-owner: parent -->

## Rollback Boundary

This presentation-only slice can be rolled back by reverting its changes in the allowed Twig/Sass/test surfaces and redeploying matching assets; do not alter data, migrations, or integrations.
