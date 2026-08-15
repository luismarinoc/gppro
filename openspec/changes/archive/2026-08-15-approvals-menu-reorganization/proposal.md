# Proposal: Approvals Menu Reorganization

## Intent

Approval work is scattered across three unrelated places and the fix built
for it is invisible:

- Expense approval-level config lives under **Gastos**
  (`MenuSubscriber.php:137-140`), Invoice payment approval-level config
  lives under **Facturas** (`MenuSubscriber.php:167-170`) — same concept,
  two unrelated homes, discoverable only by knowing which domain menu to
  open.
- `ApprovalsDashboardController` (`approvals_dashboard`, `/approvals/`) —
  built to be the single place to see everything pending — has **zero menu
  entries anywhere**; it is reachable only by typing the URL.
- The Tabler notification bell is included in both layouts and
  `NotificationsSubscriber` already listens to `NotificationEvent`, but it
  never calls `addNotification()`, so the bell renders invisible. There is
  no ambient "something is waiting for you" signal.
- `messages.es.xlf` has no `approvals_dashboard.*` keys, so the dashboard is
  English-only in a Spanish-first app.

Result: approvers do not know they have work; admins cannot explain where
approval configuration lives.

## Locked Decisions (confirmed by PO)

| # | Decision |
|---|----------|
| D1 | The **action** (see what is pending, go resolve it) lives in exactly one place: the existing Approvals Dashboard. It gets a real menu entry plus the notification bell pointing at it. |
| D2 | A **new top-level menu** groups every approval-level configuration screen: Expense levels moved out of Gastos, Invoice levels moved out of Facturas. |
| D3 | Login Audit stays in the system menu — untouched. |
| D4 | The dashboard stays navigation-only; no inline approve/reject (pre-existing design decision, unchanged). |
| D5 | New top-level menu label: **"Aprobaciones" / "Approvals"** (not "Workflow"). |
| D6 | The dashboard entry lives **inside** the new menu, as its first child — not as a separate top-level entry. |
| D7 | Menu icon: `review` (`fas fa-user-check`, existing alias in `config/packages/tabler.yaml`, no config change needed). |
| D8 | Badge count accepts the naive (creator-exclusion-only) repository scope rather than paying per-row voter re-checks; the dashboard body remains the authoritative, exact list. |
| D9 | Bell shows one aggregated notification entry per domain (Expense/Invoice/Timesheet), not one row per pending item. |

## Scope

### In Scope

- New top-level `MenuItemModel` in `MenuSubscriber::onMainMenuConfigure()`
  holding: dashboard entry (`approvals_dashboard`, gated
  `IS_AUTHENTICATED_FULLY` to match the controller), Expense approval levels
  (`manage_expense_approval_levels`), Invoice payment approval levels
  (`manage_invoice_payment_approval_levels`) — same routes, same permissions,
  same `setChildRoutes()` wiring, only relocated.
- Remove both approval-level children from the `expenses` and `invoices`
  menus. Those parents keep their remaining children; the existing
  `hasChildren()` guard already handles a parent going empty.
- Feed the dormant bell: `NotificationsSubscriber` adds one aggregated entry
  per domain (or a single combined entry) linking to `/approvals/`, and the
  vendor template renders bell + total badge with no frontend work.
- New `COUNT()`-only repository methods for pending Expense / Invoice /
  Timesheet, so the badge does not hydrate full entity graphs on every page
  load (`findPending*ForUser()` must not be reused for this).
- Missing `approvals_dashboard.*` translations in `messages.es.xlf`, plus
  keys for the new menu labels.

### Out of Scope

- Approval logic itself — voters, `ExpenseApprovalPolicy`, approval-level
  entities and services are read, never modified.
- Inline approve/reject from the dashboard (D4).
- Login Audit relocation (D3).
- New permissions; no permission is created, renamed, or widened.
- Redesigning the dashboard body, its columns, or its filtering.

## Capabilities

### New Capabilities

- `approvals-navigation`: where approval work and approval configuration are
  reachable from — menu placement, permission-gated visibility, and the
  ambient pending-count indicator.

### Modified Capabilities

None. `approvals-dashboard` behavior is unchanged; only its reachability and
localization change, which the new capability covers.

## Approach

Pure navigation/wiring work in `MenuSubscriber` reusing the existing
dropdown-parent/permission-gated-child pattern already used by `quotations`,
`expenses`, and `invoices`. The dashboard child is intentionally the first
child and visible to any authenticated user, so the new parent renders for
plain approvers who hold neither `manage_*` permission — a config-only menu
would be invisible to exactly the users who need the dashboard most.

For the bell, `NotificationsSubscriber::onNotificationEvent()` stops calling
`setShowBadgeTotal(false)` unconditionally and instead adds notifications
sourced from the new COUNT methods.

**Count accuracy tradeoff**: the existing repository pending scopes are
deliberately naive (creator-exclusion only); the dashboard body re-filters
every row through the real voter. A COUNT fed by the raw scope may
over-count relative to the dashboard. Recommendation: accept the
approximation (consistent with already-documented behavior, keeps the
per-request cost flat) and let the dashboard remain the authoritative
number. To be confirmed — see Open Questions.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/EventSubscriber/MenuSubscriber.php` | Modified | New top-level menu; two children relocated |
| `src/EventSubscriber/NotificationsSubscriber.php` | Modified | Emits real pending-approval notifications |
| `src/Repository/ExpenseRepository.php` | Modified | New `COUNT()`-only pending method |
| `src/Repository/InvoiceRepository.php` | Modified | New `COUNT()`-only pending method |
| `src/Repository/TimesheetRepository.php` | Modified | New `COUNT()`-only pending method |
| `translations/messages.es.xlf` | Modified | `approvals_dashboard.*` + new menu keys |
| `translations/messages.en.xlf` | Modified | New menu keys |
| `config/packages/tabler.yaml` | Modified (conditional) | Only if a new icon alias is needed |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Users' muscle memory breaks — approval levels no longer under Gastos/Facturas | Med | Intentional per D2; both screens keep their exact routes, so bookmarks and deep links still work |
| Bell count queries run on every page load and slow the app | Med | Dedicated `COUNT()`-only methods; no entity hydration; no per-row voter re-check |
| Badge count differs from the dashboard's voter-filtered list, users report it as a bug | Med | Explicit tradeoff above; dashboard is the authoritative number. Confirm with PO before implementing |
| A domain menu becomes empty after removing its child | Low | `hasChildren()` guard already exists; both parents retain other children |
| Notification `maxDisplay` (default 10) truncates a long list | Low | Aggregate per domain rather than one entry per pending item |

## Rollback Plan

All changes are additive or relocations in two subscribers, three read-only
repository methods, and translation files. No migration, no entity change,
no permission change. Reverting the commits restores the previous menu
placement and the dark bell with zero data impact.

## Dependencies

- `ApprovalsDashboardController` / route `approvals_dashboard` (exists).
- `MenuItemModel::setBadge()`/`setBadgeColor()` (exists, unused).
- `KevinPapst\TablerBundle\Event\NotificationEvent` and
  `navbar_notifications.html.twig` (exist, already in both layouts).

## Success Criteria

- [ ] The Approvals Dashboard is reachable from the main menu by any authenticated user, with no URL typing.
- [ ] Both approval-level config screens appear only under the new top-level menu, under their unchanged permissions.
- [ ] Gastos and Facturas menus no longer show approval-level entries and still render their remaining children correctly.
- [ ] A user with pending approvals sees the navbar bell with a count; a user with none sees no bell.
- [ ] The dashboard renders fully in Spanish — no untranslated `approvals_dashboard.*` keys.
- [ ] Login Audit is unchanged in the system menu.
- [ ] No voter, approval policy, or permission is modified.

## Open Questions

None — all resolved by the PO, see D5–D9 in Locked Decisions. Ready for `sdd-spec` / `sdd-design`.
