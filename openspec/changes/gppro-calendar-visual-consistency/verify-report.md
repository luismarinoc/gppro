```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:451b97ba54162686ce1b9e8c652d14c6cfa430d6b12694c9e676b1d923dcd6a4
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 5/5
scenarios: 10/10
test_command: vendor/bin/phpunit tests/Controller/CalendarControllerTest.php; APP_DEBUG=1 composer linting; ./phpstan.sh test; ./php-cs-fixer.sh core; composer tests-unit
test_exit_code: 0
test_output_hash: sha256:54d49d1961f89f76e325eb15e393d0ceb3b80e42f6af6dcdcd904f7f1bdcbb95
build_command: pnpm build
build_exit_code: 0
build_output_hash: sha256:dcaf3bd1b3c1cd17f4fc8f0307f2b05f34fabac5f061b7093d567d3291f55c39
```

# Verification Report: GPPro Calendar Visual Consistency

## Result

PASS WITH WARNINGS. The Calendar visual consistency change is implemented, deployed, browser-smoke verified, and ready for archive.

## Command evidence

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/CalendarControllerTest.php` | PASS — 5 tests / 26 assertions |
| `APP_DEBUG=1 composer linting` | PASS — container, YAML, Twig, Doctrine mapping, and XLIFF checks passed |
| `./phpstan.sh test` | PASS — no errors |
| `./php-cs-fixer.sh core` | PASS; it attempted an unrelated quotation-test whitespace cleanup, which was restored |
| `pnpm build` | PASS in apply evidence with existing upstream Sass deprecation warnings; generated `public/build/` restored |
| `composer tests-unit` | PASS in apply evidence — 3,464 tests / 18,798 assertions / 4 skipped |
| `git status --short` | Clean after restoring unrelated/generated output |

## Spec coverage

| Requirement | Status |
| --- | --- |
| Calendar shell uses GPPro workflow hierarchy | PASS |
| Calendar filter and drag sources remain understandable and locally contained | PASS |
| FullCalendar presentation aligns with supported states | PASS |
| Existing Calendar interaction contracts are preserved | PASS |
| Calendar remains accessible across supported presentation conditions | PASS |

## Browser evidence

Authenticated read-only Chrome/CDP evidence ran against `https://gppro.tbema.net` using `.env.local` credentials without printing secrets.

| Route | Widths | Themes | Result |
| --- | --- | --- | --- |
| `/es/calendar/` | 360px, 768px, 1024px | light, forced dark | PASS — exactly one `.gp-workflow.gp-workflow--calendar` root, `#calendar-form`, `#timesheet_calendar`, `.gp-calendar-scroll`, FullCalendar toolbar, drag source container and draggable data present; no document-level horizontal overflow. |

No mutating calendar or timesheet action was invoked.

## Warnings / caveats

- The non-trailing `/es/calendar` URL can return a web-server 404; the Symfony route is `/es/calendar/`.
- A truly sidebar-absent controller state was not feasible without out-of-scope controller changes; the implementation instead verifies a real no-drag-source configuration while preserving the form-present path.
- `pnpm build` emits pre-existing upstream Sass deprecation warnings and writes generated assets; generated output was restored and is excluded from delivery.

## Boundaries

The change is presentation-only. It preserves routes, query parameters, `#timesheet_calendar`, `#calendar-form`, the inline JavaScript block, FullCalendar options/event sources/URLs/permissions, drag/drop selector/data contracts, timesheet behavior, integrations, persistence, APIs, permissions, translations, and generated assets.
