# Design: Approvals Menu Reorganization

## Technical Approach

Three independent, additive edits with no new classes: (1) `MenuSubscriber`
gains one dropdown parent built with the exact `quotations`/`expenses`
pattern (parent with `null` route + permission-gated children +
`hasChildren()` guard), and the two approval-level children are moved into
it verbatim; (2) each of the three repositories gains a `COUNT()`-only
sibling next to its existing `findPending*ForUser()`; (3)
`NotificationsSubscriber` stops muting the bell and emits one aggregated
`NotificationModel` per domain, all pointing at `approvals_dashboard`.
No route, entity, permission, migration, or frontend template changes.

## Architecture Decisions

| # | Decision | Choice | Rejected | Rationale |
|---|---|---|---|---|
| A1 | Menu position | New parent `MenuItemModel('approvals', 'menu.approvals', null, [], 'review')` added **after** the `invoices` block, before the admin menu | Placing it first, or as a top-level sibling of the dashboard | A cross-domain aggregator reads naturally after the domains it aggregates; the first seven entries keep their current order, so existing muscle memory is untouched |
| A2 | Child order + gates | 1. `approvals_dashboard` → route `approvals_dashboard`, icon `clock`, gate `IS_AUTHENTICATED_FULLY`; 2. `expense_approval_level_list` (gate `manage_expense_approval_levels`); 3. `invoice_payment_approval_level_list` (gate `manage_invoice_payment_approval_levels`). Both level items keep identifier, route, icon `settings`, and `setChildRoutes()` unchanged. Wrapped in `if ($approvals->hasChildren())` | Gating the dashboard on a `manage_*` permission | D6 puts the dashboard first; `IS_AUTHENTICATED_FULLY` mirrors the controller's own `#[IsGranted]` exactly, so a plain approver (no `manage_*`) still sees the parent. The outer subscriber guard is `IS_AUTHENTICATED_REMEMBERED`, so a remember-me-only session correctly sees no dashboard child — same denial the controller would issue |
| A3 | Dashboard child routes | `setChildRoutes()` not called | Registering domain deep-links (`expense_view`, `admin_invoice_edit`) as child routes | Those routes already belong to the Gastos/Facturas items; claiming them here would highlight two menu entries at once |
| A4 | Menu badge | None. The bell is the only count surface | `MenuItemModel::setBadge()` on the parent or on the dashboard child | The vendor `menu.html.twig` macro renders a top-level `item_badge` **only** when `layout_type is 'horizontal'` — invisible in the default vertical sidebar. A child badge would render, but needs a second count source per request, doubling the queries for a redundant signal |
| A5 | Counting orchestration | Inline in `NotificationsSubscriber` (constructor: `Security`, `TranslatorInterface`, `UrlGeneratorInterface`, the 3 repositories, `LoggerInterface`) | A new `PendingApprovalCounter` service | Single consumer, given A4. Extract only if a second consumer appears |
| A6 | Query order / short-circuit | Bail when `!$user instanceof User`; then Expense → Invoice → Timesheet, and run the Timesheet count **only if** `$user->isTeamlead()` | Always running all three | Expense/Invoice are single-table indexed counts; Timesheet is the only 3-level join. `isTeamlead()` is in-memory over loaded memberships and `tm.teamlead = true` can never match otherwise — zero behavior change, one join query saved for most users. Emission order matches the dashboard's section order |
| A7 | Error isolation | Each count in its own `try/catch (\Throwable)` → log, treat as `0` | Letting the exception bubble | The bell renders in the global layout of **every** page; an uncaught failure would 500 the whole app, not just approvals. A missing badge is the safe degradation |
| A8 | Caching | Per-request memoization only (`private ?array $counts = null`) | PSR-6/Redis cache with a TTL | Counts flip on every approve/reject and there is no invalidation hook; a stale "3 pending" after approving is a worse bug than 2–3 indexed COUNTs. Memoization makes a repeated `NotificationEvent` dispatch free |
| A9 | Badge semantics | Keep `showBadgeTotal` at its default `true`; per-domain counts live in each entry's message, the aggregate in `setTitle()` | (a) one notification per item — breaks D9 and trips `maxDisplay`; (b) overriding `navbar_notifications.html.twig` — frontend work is out of scope; (c) `setShowBadgeTotal(false)` (dot only) | `NotificationEvent::getTotal()` is hardcoded to `count($notifications)`, so with D9 the numeric badge counts **domains with work (1–3)**, not items. Accepted, documented in Risks |
| A10 | Translation site | The subscriber translates via `TranslatorInterface` | Passing raw keys | `navbar_notifications.html.twig` prints `{{ notification.message }}` and `notificationEvent.title` **untranslated** — the vendor template applies no `trans` filter |

## Data Flow

    ConfigureMainMenuEvent ──> MenuSubscriber
        approvals (parent, no route, icon: review)
          ├── approvals_dashboard        [IS_AUTHENTICATED_FULLY]
          ├── expense_approval_level_list        [manage_expense_approval_levels]
          └── invoice_payment_approval_level_list [manage_invoice_payment_approval_levels]

    every page render ──> NotificationEvent ──> NotificationsSubscriber
        user? ──no──> return (bell hidden: showIfEmpty=false)
        countPendingForUser ──────────────┐
        countPendingPaymentApprovalForUser├──> n>0 ? addNotification(url=/approvals/)
        isTeamlead? countPendingApproval… ┘
        (each wrapped in try/catch → 0 on failure)

## File Changes

| File | Action | Description |
|---|---|---|
| `src/EventSubscriber/MenuSubscriber.php` | Modify | New `approvals` parent (A1/A2); delete lines 137-141 (expense levels) and 167-171 (invoice levels) |
| `src/EventSubscriber/NotificationsSubscriber.php` | Modify | Drop `setShowBadgeTotal(false)`; add constructor deps and per-domain notifications (A5–A10) |
| `src/Repository/ExpenseRepository.php` | Modify | `countPendingForUser(User): int` below `findPendingForUser()` |
| `src/Repository/InvoiceRepository.php` | Modify | `countPendingPaymentApprovalForUser(User): int` |
| `src/Repository/TimesheetRepository.php` | Modify | `countPendingApprovalForUser(User): int` |
| `translations/messages.es.xlf` | Modify | 8 `approvals_dashboard.*` keys (`title`, `none_pending`, `none_pending_invoice`, `none_pending_timesheet`, `review`, `section_expense`, `section_invoice`, `section_timesheet`) + `menu.approvals` + 3 `approvals.notification.*` + `approvals.notification.title` |
| `translations/messages.en.xlf` | Modify | Same new keys, English targets |
| `tests/EventSubscriber/MenuSubscriberTest.php` | Modify | Relocation assertions (two existing tests now assert absence under expenses/invoices) |
| `tests/EventSubscriber/NotificationsSubscriberTest.php` | Create | Unit coverage per Testing Strategy |
| `tests/Repository/*RepositoryTest.php` | Create/Modify | Integration coverage of the 3 count methods |

## Interfaces / Contracts

Counts mirror their `findPending*` sibling's WHERE clause **exactly** (D8
naive scope: creator exclusion only), drop `orderBy`, and return `int`:

```php
// TimesheetRepository - DISTINCT is required: project->teams->members
// multiplies rows when the user leads several teams on one project.
// The existing findPendingApprovalForUser() inherits that duplication;
// the counter must not.
public function countPendingApprovalForUser(User $user): int
{
    return (int) $this->createQueryBuilder('t')
        ->select('COUNT(DISTINCT t.id)')
        ->join('t.project', 'p')->join('p.teams', 'team')->join('team.members', 'tm')
        ->andWhere('tm.user = :user')->andWhere('tm.teamlead = true')
        ->andWhere('t.approvedAt IS NULL')
        ->setParameter('user', $user)
        ->getQuery()->getSingleScalarResult();
}
```

Expense/Invoice have no joins → plain `COUNT(e.id)` / `COUNT(i.id)`.

Notification per domain: `new NotificationModel('approvals_expense',
$translator->trans('approvals.notification.expense', ['%count%' => $n]),
'yellow')` with `setUrl($urlGenerator->generate('approvals_dashboard'))`.

## Testing Strategy

Strict TDD: every row is written RED first.

| Layer | What to Test | Approach |
|---|---|---|
| Unit | Menu: `approvals` parent exists for an authenticated user with no `manage_*`; dashboard child is **first**; both level items live under `approvals` with unchanged route + child routes; `expenses`/`invoices` no longer contain them; parent absent for remember-me-only | Extend `MenuSubscriberTest` with the existing mocked-`Security` callback pattern |
| Unit | Bell: no user → no notification; all counts 0 → `getTotal() === 0` (bell hidden); counts > 0 → one entry per domain, correct order, url `/approvals/`, translated message; non-teamlead → timesheet repo `expects($this->never())`; one repo throwing → no exception and the other domains still emitted; `setShowBadgeTotal` never called with `false` | New `NotificationsSubscriberTest` (`TestCase`, mocked repos/translator/url generator/security/logger) |
| Integration | The 3 count methods: status/`approvedAt` filter, creator exclusion, and the multi-team `DISTINCT` case counting 1 not N | `KernelTestCase` with Team/Project/Timesheet/Expense/Invoice fixtures, mirroring `ExpenseRepositoryTest` |
| Functional | `/approvals/` renders in Spanish with no raw `approvals_dashboard.` key in the HTML | Extend `ApprovalsDashboardControllerTest` |
| Static | XLIFF validity of both translation files | `bin/console lint:xliff translations` in the existing lint step |

## Threat Matrix

N/A — no shell, subprocess, VCS/PR automation, executable-file
classification, or process-integration boundary. No route is created,
renamed, or un-gated: menu visibility is presentation only, and
`ApprovalsDashboardController` keeps its own `#[IsGranted]` plus per-row
voter re-check as the enforcing layer (defense in depth). The count queries
are read-only and parameter-bound.

## Migration / Rollout

No migration required. Pure subscriber + read-only query + translation
change; reverting the commits restores the previous menu and the dark bell.

## Open Questions

- [ ] A9 (non-blocking): the numeric bell badge counts domains with pending
      work (1–3), not pending items, because `NotificationEvent::getTotal()`
      is vendor-hardcoded. Exact per-domain counts appear in the dropdown.
      Confirm with the PO, or accept a dot-only badge instead.
