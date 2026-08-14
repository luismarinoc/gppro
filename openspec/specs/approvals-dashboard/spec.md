# Approvals Dashboard Specification

## Purpose

Read-only, cross-domain aggregation of pending approvals (Expense + Invoice
+ Timesheet) for the current user. Navigation-only — no inline approve/
reject in this slice.

## Requirements

### Requirement: Aggregate pending approvals across all three domains

The system MUST show, in one view, the current user's pending approvals
from Expense, Invoice payment approval, and Timesheet approval combined.

#### Scenario: User with pending items in all three domains
- GIVEN user U has pending approvals in Expense, Invoice, and Timesheet
- WHEN U opens the dashboard
- THEN all three domains' pending items for U appear in one aggregated list

### Requirement: Single-domain result is correctly scoped

When a user has pending approvals in only one domain, the dashboard MUST
show only that domain's items and MUST NOT include items from domains with
nothing pending for that user.

#### Scenario: User with pending items in only Timesheet
- GIVEN user U has pending Timesheet approvals only
- WHEN U opens the dashboard
- THEN only Timesheet items appear; no Expense or Invoice rows are shown

### Requirement: Empty state when nothing is pending

The system MUST render an appropriate empty state when the current user has
zero pending approvals across all three domains.

#### Scenario: User with no pending approvals anywhere
- GIVEN user U has no pending approvals in Expense, Invoice, or Timesheet
- WHEN U opens the dashboard
- THEN an empty state is shown, with no error

### Requirement: Dashboard rows navigate to the domain's own screen

Selecting a dashboard row MUST navigate to that item's own domain-specific
approve/reject screen. The dashboard MUST NOT expose inline approve/reject
controls.

#### Scenario: Clicking an Invoice row opens Invoice's approval screen
- GIVEN the dashboard lists a pending Invoice approval item
- WHEN the user selects that row
- THEN they are navigated to Invoice's own payment-approval screen, not an
  inline action on the dashboard

### Requirement: Visibility is permission-consistent with each domain

The dashboard MUST only include items the current user is eligible to
approve per that domain's own approval-eligibility rule (Expense policy,
Invoice level eligibility, Timesheet team-lead voter). It MUST NOT surface
items beyond what each domain's own authorization would already allow.

#### Scenario: Dashboard does not leak items outside a user's eligibility
- GIVEN user U is not the team lead of project P and P has a pending
  Timesheet entry
- WHEN U opens the dashboard
- THEN P's pending Timesheet entry does not appear for U
