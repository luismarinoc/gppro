# Approvals Navigation Specification

## Purpose

Where approval work and approval configuration are reachable from: a
top-level "Aprobaciones"/"Approvals" menu, permission-gated visibility of its
children, and an ambient pending-count indicator (navbar bell). Navigation
and localization only — approval logic (voters, `ExpenseApprovalPolicy`,
approval-level entities/services) MUST NOT change.

## Requirements

### Requirement: Top-level Approvals menu with the dashboard as first child

`MenuSubscriber::onMainMenuConfigure()` MUST register a new top-level menu
labeled "Aprobaciones"/"Approvals" (icon `review`). Its first child MUST link
to route `approvals_dashboard`, gated only by `IS_AUTHENTICATED_FULLY`
(matching the controller's own guard), visible to any authenticated user
regardless of `manage_*` permissions.

#### Scenario: User without config permissions sees the menu and dashboard link

- GIVEN a logged-in user holding neither `manage_expense_approval_levels`
  nor `manage_invoice_payment_approval_levels`
- WHEN the main menu renders
- THEN "Aprobaciones" is visible and its first child links to `approvals_dashboard`

### Requirement: Approval-level config screens are gated children of the Approvals menu

Expense approval levels (route `admin_expense_approval_level_list`,
permission `manage_expense_approval_levels`) and Invoice payment approval
levels (route `admin_invoice_payment_approval_level_list`, permission
`manage_invoice_payment_approval_levels`) MUST appear as children of the
Approvals menu, each keeping its existing permission and `setChildRoutes()`
wiring unchanged. No route or permission MUST be created, renamed, or
widened.

#### Scenario: Permission gates each child independently

- GIVEN a user holding `manage_expense_approval_levels` only
- WHEN the main menu renders
- THEN the Approvals menu shows the Expense child but not the Invoice child

### Requirement: Approval-level entries removed from Gastos and Facturas

The `expenses` menu MUST NOT show the Expense approval-level child; the
`invoices` menu MUST NOT show the Invoice approval-level child. Both parents
MUST keep rendering their remaining children (using the existing
`hasChildren()` guard).

#### Scenario: Gastos and Facturas render without the relocated entries

- GIVEN a user holding both approval-level permissions
- WHEN the main menu renders
- THEN neither `expenses` nor `invoices` show an approval-level child
- AND both still show their other children (e.g. expense/invoice lists)

### Requirement: Per-domain COUNT-only pending repository methods

`ExpenseRepository`, `InvoiceRepository`, and `TimesheetRepository` MUST each
add a method returning only a scalar pending-item count (`COUNT()`
aggregate), using the same naive, creator-exclusion-only scope as each
domain's existing `findPending*ForUser()` method. These methods MUST NOT
call, wrap, or duplicate that entity-hydrating logic, and MUST NOT hydrate
full entity graphs.

#### Scenario: Count method returns a scalar without hydrating entities

- GIVEN a user with 3 pending Expense items under the existing naive scope
- WHEN the Expense COUNT-only method is called for that user
- THEN it returns the integer 3 and no `Expense` entities are hydrated

### Requirement: Navbar bell shows one aggregated notification per domain when count > 0

`NotificationsSubscriber::onNotificationEvent()` MUST NOT call
`setShowBadgeTotal(false)` unconditionally. For each domain whose COUNT-only
method returns > 0 for the current user, it MUST add exactly one aggregated
notification entry for that domain, linking to `approvals_dashboard` and
showing that domain's count. A domain with count 0 MUST add no entry; if all
three are 0, the bell MUST show no badge.

#### Scenario: Zero pending items shows no bell badge

- GIVEN a user with zero pending Expense, Invoice, and Timesheet items
- WHEN the navbar renders
- THEN no notification entries are added and no badge is shown

#### Scenario: Pending items in multiple domains produce one entry per domain

- GIVEN a user with pending items in Expense and Timesheet, none in Invoice
- WHEN the navbar renders
- THEN one aggregated entry is added for Expense and one for Timesheet
- AND no entry is added for Invoice

### Requirement: Approvals Dashboard is fully localized in Spanish

`messages.es.xlf` MUST include a translated `<target>` for every
`approvals_dashboard.*` key present in `messages.en.xlf` (`title`,
`section_expense`, `section_invoice`, `section_timesheet`, `none_pending`,
`none_pending_invoice`, `none_pending_timesheet`, `review`). Both files MUST
add keys for the new "Aprobaciones"/"Approvals" menu label and its relocated
children's labels.

#### Scenario: Dashboard renders fully in Spanish

- GIVEN a user with locale set to Spanish
- WHEN the user opens the Approvals Dashboard
- THEN every string resolves to a Spanish `<target>`, none falls back to a raw key

### Requirement: Login Audit and approval logic remain unaffected

Login Audit MUST remain unmoved in the system menu. No voter,
`ExpenseApprovalPolicy`, approval-level entity, or permission definition MUST
be created, modified, or removed by this capability.

#### Scenario: Login Audit unchanged

- GIVEN an admin user who previously found Login Audit in the system menu
- WHEN the main menu renders after this change
- THEN Login Audit still appears there, unmoved
