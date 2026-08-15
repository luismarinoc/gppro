# Verify Report: Approvals Menu Reorganization

**Change**: `approvals-menu-reorganization`
**Branch**: `approvals-menu-reorganization` (tip `3292de5`, 6 commits ahead of `main`@`7525808`)
**Mode**: Full artifact verification (spec + tasks present, no separate design.md scenario needed — design decisions folded into tasks.md rationale)
**Verdict**: **PASS WITH WARNINGS**

## Completeness Table

| Phase | Status | Evidence |
|---|---|---|
| 1 (Menu relocation, 1.1-1.9) | [x] all 9 | `MenuSubscriberTest.php` — inspected `MenuSubscriber.php` lines 168-197 directly, matches spec exactly |
| 2 (COUNT-only repos, 2.1-2.7) | [x] all 7 | `ExpenseRepository::countPendingForUser`, `InvoiceRepository::countPendingPaymentApprovalForUser`, `TimesheetRepository::countPendingApprovalForUser` inspected directly, each mirrors its sibling `findPending*()` WHERE/JOIN scope with scalar `COUNT()`/`COUNT(DISTINCT)`, no wrapping/hydration |
| 3 (Notifications bell, 3.1-3.8) | [x] all 8 | `NotificationsSubscriber.php` inspected directly — no unconditional `setShowBadgeTotal(false)`, per-domain aggregated `NotificationModel`, per-request memoized counts, per-domain try/catch isolation, Timesheet gated on `isTeamlead()` |
| 4 (Translations, 4.1-4.5) | [x] all 5 | `messages.es.xlf`/`messages.en.xlf` inspected directly, `lint:xliff` re-run |
| 5 (Full verification, 5.1-5.3) | [x] all 3 | Focused suite re-run independently below; `git diff --stat` re-run independently below |

32/32 tasks genuinely complete — spot-checked against source, not just checkbox trust.

## Build/Test Evidence (independently re-run, not trusted from apply report)

**Command** (tasks.md 5.1 focused set):
```
BOOTSTRAP_RESET_DATABASE=0 vendor/bin/phpunit \
  tests/EventSubscriber/MenuSubscriberTest.php tests/EventSubscriber/NotificationsSubscriberTest.php \
  tests/Repository/ExpenseRepositoryTest.php tests/Repository/InvoiceRepositoryTest.php \
  tests/Repository/TimesheetRepositoryTest.php tests/Controller/ApprovalsDashboardControllerTest.php
```
Result: **OK (52 tests, 208 assertions)** — matches apply-progress's "52/52 green, 208 assertions" claim exactly.

**Command** (translation lint):
```
php bin/console lint:xliff translations
```
Result: **All 602 XLIFF files contain valid syntax.**

## Spec Compliance Matrix

| Requirement | Scenario | Evidence | Status |
|---|---|---|---|
| Top-level Approvals menu, dashboard first child | User without config perms sees menu + dashboard link | `MenuSubscriber.php:177-181` — dashboard child gated only by `IS_AUTHENTICATED_FULLY`, added before the two permission-gated children; `testApprovalsMenuShowsDashboardFirstForAnyAuthenticatedUser` green | PASS |
| Approval-level config screens gated as children | Permission gates each child independently | `MenuSubscriber.php:183-193` — each level child kept its own `isGranted()` check, route, `setChildRoutes()` unchanged; `testApprovalsMenuGatesEachLevelChildIndependently` green | PASS |
| Approval-level entries removed from Gastos/Facturas | Gastos/Facturas render without relocated entries | `MenuSubscriber.php:126-166` — no `manage_expense_approval_levels`/`manage_invoice_payment_approval_levels` block remains in the expense/invoice sections; `expense_list`/`invoice_listing` children still present; both strengthened tests green | PASS |
| Per-domain COUNT-only pending repository methods | Count method returns scalar without hydrating entities | `ExpenseRepository.php:116-126`, `InvoiceRepository.php:75-85`, `TimesheetRepository.php:1033-1046` — each is a standalone `select('COUNT(...)')` + `getSingleScalarResult()`, does not call the hydrating sibling; repo integration tests green | PASS |
| Navbar bell aggregated per domain, no unconditional badge suppression | Zero pending → no badge; multi-domain → one entry each | `NotificationsSubscriber.php` — `setShowBadgeTotal(false)` call removed entirely (not present anywhere in file); `onNotificationEvent()` adds one `NotificationModel` per domain with count > 0 only; `testAllCountsZeroAddsNoNotification`, `testEachDomainWithPendingCountAddsOneAggregatedEntry` green | PASS |
| Approvals Dashboard fully localized in Spanish | Dashboard renders fully in Spanish | `messages.es.xlf:2683-2695` — all 8 `approvals_dashboard.*` ids (`gpApprovalsDash1`-`8`) have `state="translated"` Spanish `<target>`; `menu.approvals` and 4 `approvals.notification.*` keys also translated in both files; `testDashboardRendersFullyInSpanish` green | PASS |
| Login Audit and approval logic unaffected | Login Audit unchanged | `MenuSubscriber.php:272` — `login_audit` entry unmoved in admin/system menu; `git diff main...approvals-menu-reorganization -- src/Security src/Entity` returns empty (no voter/policy/entity/permission file touched); pre-existing Login Audit tests still pass unmodified | PASS |

7/7 spec requirements verified via direct source inspection plus a passing covering test for each scenario.

## Out-of-Scope Check

```
git diff main...approvals-menu-reorganization --stat
```
14 files changed (683 insertions, 19 deletions across source+tests+translations; 702 changed lines total, +tasks.md). Files touched: `MenuSubscriber.php`, `NotificationsSubscriber.php`, 3 repositories (`ExpenseRepository.php`, `InvoiceRepository.php`, `TimesheetRepository.php`), 2 translation files, and their corresponding test files/tasks.md. No voter, `ExpenseApprovalPolicy`, approval-level entity, or permission-definition file appears in the diff — confirms task 5.3's claim independently.

## Issues

### CRITICAL
None.

### WARNING
1. **Review workload budget exceeded**: actual diff is 702 changed lines across 13 code/test/translation files, above both the tasks.md forecast (~260-320, "Low" risk) and the 400-line single-PR guard threshold. The overrun is concentrated in test scaffolding (`NotificationsSubscriberTest.php` alone is 252 lines for the mock/assert scaffolding a 7-dependency constructor's strict-TDD coverage requires) and does not reflect scope creep — production code is 207 lines across 5 files, and the out-of-scope check above confirms no unrelated file was touched. Already flagged per `ask-on-risk` in apply-progress; requires an explicit accept-as-is or split decision from the user/orchestrator before merge, per the delivery-strategy contract — this is a process gate, not a spec-compliance defect, so it does not block the PASS verdict but should be resolved before archive.

### SUGGESTION
None.

## Final Verdict

**PASS WITH WARNINGS** — all 7 spec requirements verified true by direct code inspection with a passing covering test per scenario; all 32 tasks genuinely complete; no out-of-scope files touched (voters/permissions/entities untouched, Login Audit unmoved); 52/52 focused tests green (208 assertions), independently re-run and matching the reported result exactly. One process WARNING (400-line budget overrun) needs an explicit accept/split decision before archive but does not indicate incorrect or incomplete implementation.
