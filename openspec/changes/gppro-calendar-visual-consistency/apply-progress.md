# Apply Progress: GPPro calendar visual consistency

## Status

```json
{
  "changeName": "gppro-calendar-visual-consistency",
  "artifactStore": "openspec",
  "applyState": "implementation-complete",
  "nextRecommended": "parent-lifecycle",
  "actionContext": {
    "mode": "repo-local",
    "workspaceRoot": "/Users/luismarinoc/Documents/Dev/tbema/gppro",
    "allowedEditRoots": ["/Users/luismarinoc/Documents/Dev/tbema/gppro"],
    "warnings": []
  }
}
```

The parent supplied the valid RED evidence after repairing the disposable test database: the new shell assertion failed at `CalendarControllerTest.php:38` (4 tests, 12 assertions, 1 failure). The earlier bootstrap failure (`FK_4016EF25A76ED395`) remains historical only; later focused runs passed.

## Completed Implementation Tasks

All 10 implementation-owned task rows are marked `[x]` in `tasks.md`:

- RED DOM-contract assertions; Twig workflow shell/wrappers; Sass presentation layer; focused GREEN evidence.
- `drag-drop.html.twig` intentionally unchanged: parent hooks style its existing markup without altering source contracts.
- TRIANGULATE configuration with `calendar.dragdrop_amount = 0`, proving no source surface while retaining the controller's always-rendered filter form.
- Scoped-Sass refactor, lint/static/style/build/unit validation, generated-output restoration, and final diff recount.

A genuinely sidebar-absent controller rendering is not feasible within this slice: `CalendarController::userCalendar()` always supplies `form->createView()`. The triangulation case uses real server configuration (not CSS simulation) to prove the no-drag-source path.

## TDD Cycle Evidence

| Task | Test file | Layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Calendar presentation | `tests/Controller/CalendarControllerTest.php` | Integration | 4 tests / 13 assertions passed before RED | Parent: valid missing-shell failure, 4 tests / 12 assertions | 4 tests / 21 assertions passed after Twig hooks | 5 tests / 26 assertions passed with `dragdrop_amount = 0` | 5 tests / 26 assertions passed after Sass/refactor and final validation |

## Commands Run

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/CalendarControllerTest.php` | PASS — final 5 tests, 26 assertions |
| `APP_DEBUG=1 composer linting` | PASS |
| `./phpstan.sh test` | PASS after replacing count comparisons with `assertCount()` |
| `./php-cs-fixer.sh core` | PASS; restored its unrelated `tests/Controller/QuotationControllerTest.php` change |
| `pnpm build` | PASS with existing Sass deprecation warnings; `public/build/` restored and absent from the diff |
| `composer tests-unit` | PASS — 3,464 tests, 18,798 assertions, 4 skipped |
| `git diff --check` | PASS |

## Workload / PR Boundary

The complete delivery diff is **309 changed lines**, including OpenSpec artifacts. It is below the 360-line safety margin and 400-line budget; no chain or size exception is required.

## Files Changed

- `templates/calendar/user.html.twig`
- `assets/sass/calendar.scss`
- `tests/Controller/CalendarControllerTest.php`
- `openspec/changes/gppro-calendar-visual-consistency/tasks.md`
- `openspec/changes/gppro-calendar-visual-consistency/apply-progress.md`

## Deviations and Risks

- No JavaScript, FullCalendar options, IDs, source selectors/data attributes, routes, permissions, or generated assets were changed.
- Browser/theme/viewport evidence remains parent-owned; PHPUnit does not prove computed presentation or page-level overflow.
- Repeated focused-test bootstrap instability recurred once after PHP-CS-Fixer (`FK_50BD83889395C3F3`), but `composer tests-unit` and the final focused run subsequently passed.

## Remaining Parent Lifecycle Tasks

- [ ] Before deployment, start or reuse bounded review of the completed diff, confirm only the allowed edit surfaces changed, verify `public/build/` is restored, and require the recorded RED/GREEN/TRIANGULATE/REFACTOR command evidence before lifecycle advancement. <!-- sdd-owner: parent -->
- [ ] After deployment, collect read-only browser evidence against `https://gppro.tbema.net` at 360px, 768px, and 1024px in light and dark themes: toolbar, events, weekend/today/selection, hover/disabled/focus, sidebar-present/absent layouts, and document-versus-local horizontal overflow; use `.env.local` credentials without printing secrets and do not perform mutating interactions. <!-- sdd-owner: parent -->
- [ ] If browser evidence exposes a visual or containment defect, return the slice to implementation with the affected viewport/theme/state; if scope grows beyond the forecast, stop for the `ask-on-risk` delivery decision rather than applying an unapproved chain or size exception. <!-- sdd-owner: parent -->

## Post-Deploy Browser Evidence — 2026-09-10

After pushing the implementation to `origin/main`, the parent waited for Dokploy and ran authenticated read-only Chrome/CDP evidence against `https://gppro.tbema.net` using `.env.local` credentials without printing secrets.

| Route | Widths | Themes | Result |
| --- | --- | --- | --- |
| `/es/calendar/` | 360px, 768px, 1024px | light, forced dark | PASS — exactly one `.gp-workflow.gp-workflow--calendar` root, `#calendar-form` preserved, `#timesheet_calendar` preserved inside `.gp-calendar-scroll`, FullCalendar toolbar present, drag source container and draggable item data preserved, and no document-level horizontal overflow detected. No calendar/timesheet mutation was invoked. |

The non-trailing `/es/calendar` path can return a web-server 404; the Symfony calendar route is the canonical trailing-slash path `/es/calendar/`.
