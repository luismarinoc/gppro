# Tasks: Approvals Menu Reorganization

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~260-320 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Menu relocation (single PR, all phases) | PR 1 | `bin/phpunit tests/EventSubscriber/MenuSubscriberTest.php tests/EventSubscriber/NotificationsSubscriberTest.php` | `bin/console lint:xliff translations` + manual login as approver-only user to see bell/menu | Revert the two subscriber commits + translation commit; no migration, no entity/permission change |

### Actual apply-time result (post-implementation)

Actual diff vs `main`: **683 insertions + 19 deletions = 702 changed lines**, 13 files
(`git diff --stat main...approvals-menu-reorganization`) — over both the ~260-320
estimate and the 400-line single-PR budget. Overrun is concentrated in test code
(`NotificationsSubscriberTest.php` alone is 252 new lines: 6 unit tests with the
full mock/assert scaffolding strict TDD requires for `NotificationsSubscriber`'s
7-dependency constructor) and `MenuSubscriberTest.php` (82 changed lines: 3 new
scenarios + assertions strengthened on 2 existing tests). Production code itself
is a modest 207 lines across 5 files. No unrelated file was touched (5.3). Flagged
to the user/orchestrator per `ask-on-risk`; not unwound since all 52 tests pass
and the diff is a single cohesive, easily-reviewable navigation/wiring change.

## Phase 1: Menu Relocation (RED -> GREEN)

- [x] 1.1 RED: update `tests/EventSubscriber/MenuSubscriberTest.php::testExpenseApprovalLevelMenuIsVisibleForLevelManagers` to assert `$expenses->findChild('expense_approval_level_list')` is `null` and add assertion that the item now lives under a new `approvals` top-level menu child instead.
- [x] 1.2 RED: update `testInvoicePaymentApprovalLevelMenuIsVisibleForLevelManagers` the same way for `invoices`/`invoice_payment_approval_level_list`.
- [x] 1.3 RED: add `testApprovalsMenuShowsDashboardFirstForAnyAuthenticatedUser` — user granted neither `manage_*` permission still sees `approvals` menu, first child route `approvals_dashboard`, gate `IS_AUTHENTICATED_FULLY`.
- [x] 1.4 RED: add `testApprovalsMenuGatesEachLevelChildIndependently` — user with only `manage_expense_approval_levels` sees the Expense child, not the Invoice child.
- [x] 1.5 RED: add `testApprovalsMenuAbsentForRememberMeOnlySession` — outer `IS_AUTHENTICATED_REMEMBERED` guard denies dashboard child (mirrors A2).
- [x] 1.6 RED: extend the two existing expenses/invoices tests to assert remaining children (`expense_list`/`invoice_listing`) still render after removal.
- [x] 1.7 GREEN: in `src/EventSubscriber/MenuSubscriber.php`, delete the `manage_expense_approval_levels` block (current lines ~137-141) from the expense menu and the `manage_invoice_payment_approval_levels` block (~167-171) from the invoice menu.
- [x] 1.8 GREEN: add new `approvals` parent after the invoice block (~after line 178): `new MenuItemModel('approvals', 'menu.approvals', null, [], 'review')`; child 1 dashboard (`approvals_dashboard`, route `approvals_dashboard`, gate `IS_AUTHENTICATED_FULLY`, icon `clock`); child 2 Expense levels (moved verbatim, same identifier/route/icon/`setChildRoutes()`); child 3 Invoice levels (moved verbatim); wrap `$menu->addChild($approvals)` in `if ($approvals->hasChildren())`.
- [x] 1.9 Run `bin/phpunit tests/EventSubscriber/MenuSubscriberTest.php` — all RED tests from 1.1-1.6 now GREEN.

## Phase 2: COUNT-only Repository Methods (RED -> GREEN)

- [x] 2.1 RED: create `tests/Repository/ExpenseRepositoryTest.php` (or extend if present) with `testCountPendingForUserMatchesNaiveScope` — 3 pending items excluding creator's own → returns int `3`, no entity hydration assertion (query builder select shape).
- [x] 2.2 GREEN: add `ExpenseRepository::countPendingForUser(User $user): int` below `findPendingForUser()` (line ~109) — same `andWhere` clauses (`status = PENDING_APPROVAL`, `createdBy != :user OR createdBy IS NULL`), `select('COUNT(e.id)')`, no `orderBy`, `getSingleScalarResult()` cast to `int`.
- [x] 2.3 RED: extend `tests/Repository/InvoiceRepositoryTest.php` with `testCountPendingPaymentApprovalForUserMatchesNaiveScope` mirroring 2.1 for `paymentApprovalStatus = PAYMENT_APPROVAL_PENDING`.
- [x] 2.4 GREEN: add `InvoiceRepository::countPendingPaymentApprovalForUser(User $user): int` below `findPendingPaymentApprovalForUser()` (line ~68) — same WHERE clauses, `select('COUNT(i.id)')`.
- [x] 2.5 RED: extend `tests/Repository/TimesheetRepositoryTest.php` with `testCountPendingApprovalForUserDeduplicatesMultiTeamLead` — a teamlead of 2 teams on the same project with 1 pending timesheet must count `1`, not `2` (DISTINCT case).
- [x] 2.6 GREEN: add `TimesheetRepository::countPendingApprovalForUser(User $user): int` below `findPendingApprovalForUser()` (line ~1024) — same joins (`t.project`→`p.teams`→`team.members`), `select('COUNT(DISTINCT t.id)')`, `andWhere('tm.teamlead = true')`, `andWhere('t.approvedAt IS NULL')`, no `orderBy`.
- [x] 2.7 Run `bin/phpunit tests/Repository/ExpenseRepositoryTest.php tests/Repository/InvoiceRepositoryTest.php tests/Repository/TimesheetRepositoryTest.php` — all GREEN.

## Phase 3: NotificationsSubscriber Aggregated Bell (RED -> GREEN)

- [x] 3.1 RED: create `tests/EventSubscriber/NotificationsSubscriberTest.php` — `testNoUserAddsNoNotification` (mocked `Security::getUser()` returns non-`User`, no repo calls, `setShowBadgeTotal` never called with `false`).
- [x] 3.2 RED: add `testAllCountsZeroAddsNoNotification` — all 3 mocked repos return `0`, no `addNotification()` call.
- [x] 3.3 RED: add `testEachDomainWithPendingCountAddsOneAggregatedEntry` — Expense/Invoice > 0, Timesheet skipped (non-teamlead) → exactly 2 `addNotification()` calls, correct order (Expense, Invoice), url resolves via `UrlGeneratorInterface::generate('approvals_dashboard')`, message via `TranslatorInterface::trans()`.
- [x] 3.4 RED: add `testNonTeamleadUserNeverCallsTimesheetCountMethod` — `TimesheetRepository::countPendingApprovalForUser` mock `expects($this->never())` when `$user->isTeamlead()` is `false`.
- [x] 3.5 RED: add `testOneRepositoryThrowingStillEmitsOtherDomains` — Expense repo throws `\Throwable`, Invoice/Timesheet still evaluated and emitted, `LoggerInterface::error()` called once, no exception propagates.
- [x] 3.6 GREEN: rewrite `src/EventSubscriber/NotificationsSubscriber.php` constructor to accept `Security`, `TranslatorInterface`, `UrlGeneratorInterface`, `ExpenseRepository`, `InvoiceRepository`, `TimesheetRepository`, `LoggerInterface`.
- [x] 3.7 GREEN: implement `onNotificationEvent()` — bail if `!$user instanceof User`; per-request memoize counts (`private ?array $counts = null`, A8); short-circuit `isTeamlead()` before the Timesheet count (A6); wrap each domain's count call in its own `try { } catch (\Throwable $e) { $this->logger->error(...); $count = 0; }` (A7); for each domain with count `> 0`, `addNotification(new NotificationModel('approvals_{domain}', $translator->trans('approvals.notification.{domain}', ['%count%' => $n]), 'yellow'))` with `->setUrl($urlGenerator->generate('approvals_dashboard'))`; remove the unconditional `setShowBadgeTotal(false)` call (A9). Also added `$event->setTitle($translator->trans('approvals.notification.title'))` when any domain has pending work, per A9's "aggregate in setTitle()" — not spelled out as a separate task but required by the design's own File Changes table (the `approvals.notification.title` key).
- [x] 3.8 Run `bin/phpunit tests/EventSubscriber/NotificationsSubscriberTest.php` — all RED tests from 3.1-3.5 now GREEN.

## Phase 4: Translations

- [x] 4.1 In `translations/messages.es.xlf`, add Spanish `<target>` trans-units mirroring the 8 `approvals_dashboard.*` ids already in `messages.en.xlf` (`gpApprovalsDash1`-`gpApprovalsDash8`: title, section_expense, section_invoice, section_timesheet, none_pending, none_pending_invoice, none_pending_timesheet, review).
- [x] 4.2 In both `messages.es.xlf` and `messages.en.xlf`, add `menu.approvals` (label "Aprobaciones"/"Approvals") and `approvals.notification.expense`/`.invoice`/`.timesheet` (with `%count%` placeholder) + `approvals.notification.title` keys.
- [x] 4.3 RED: extend `tests/Controller/ApprovalsDashboardControllerTest.php` with `testDashboardRendersFullyInSpanish` — via the app's actual `{_locale}` route prefix (`config/routes.yaml`, `requestPure($client, '/es/approvals/')`) rather than an `Accept-Language` header (the app does not branch on that header for this route); asserts the real Spanish target text is present (not just the absence of a raw key, which the `en` translator fallback would mask).
- [x] 4.4 GREEN: confirm 4.1/4.2 make 4.3 pass; run `bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php`.
- [x] 4.5 Run `bin/console lint:xliff translations` — both files valid XLIFF.

## Phase 5: Full Verification

- [x] 5.1 Run the full test suite touching this change: `bin/phpunit tests/EventSubscriber/MenuSubscriberTest.php tests/EventSubscriber/NotificationsSubscriberTest.php tests/Repository/ExpenseRepositoryTest.php tests/Repository/InvoiceRepositoryTest.php tests/Repository/TimesheetRepositoryTest.php tests/Controller/ApprovalsDashboardControllerTest.php`. Result: 52/52 passing, 208 assertions.
- [x] 5.2 Verified via the existing automated regression coverage (no interactive browser available in this environment): `testLoginAuditMenuIsVisibleForSuperAdmins` and `testLoginAuditMenuIsHiddenForNonSuperAdmins` both still pass unmodified — Login Audit is unmoved in the system menu.
- [x] 5.3 Confirmed via `git diff --stat main...approvals-menu-reorganization`: only `MenuSubscriber.php`, `NotificationsSubscriber.php`, the 3 repositories, their tests, and 2 translation files changed — no voter, `ExpenseApprovalPolicy`, approval-level entity, or permission definition file was touched.
