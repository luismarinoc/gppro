# Approvals Dashboard Specification

## Purpose

Read-only, cross-domain aggregation of pending approvals (Expense, Invoice, and Timesheet) for the current user. Navigation-only — no inline approve/
reject in this slice.

## Requirements

### Requirement: Aggregate pending approvals across all three domains

The system MUST show, in one view, the current user's pending approvals
from Expense, Invoice payment approval, and Timesheet approval combined.

#### Scenario: User with pending items in all three domains

GIVEN user U has pending approvals in Expense, Invoice, and Timesheet
WHEN U opens the dashboard
THEN all three domains' pending items for U appear in one aggregated list

### Requirement: Single-domain result is correctly scoped

When a user has pending approvals in only one domain, the dashboard MUST
show only that domain's items and MUST NOT include items from domains with
nothing pending for that user.

#### Scenario: User with pending items in only Timesheet

GIVEN user U has pending Timesheet approvals only
WHEN U opens the dashboard
THEN only Timesheet items appear; no Expense or Invoice rows are shown

### Requirement: Empty state when nothing is pending

The system MUST render a contextual, consistently structured GPPro empty state when the current user has zero pending approvals across all three domains. The empty state MUST remain understandable and MUST NOT be represented only by an otherwise empty table row.

#### Scenario: User with no pending approvals anywhere

GIVEN user U has no pending approvals in Expense, Invoice, or Timesheet
WHEN U opens the dashboard
THEN a contextual GPPro empty state is shown, with no error and no table-only empty message

### Requirement: Dashboard rows navigate to the domain's own screen

Selecting an Expense or Invoice dashboard row MUST navigate to that item's own domain-specific approve/reject screen. The dashboard MUST NOT add inline approve/reject controls for Expense or Invoice. Existing Timesheet inline approve/reject POST controls MUST remain available only with their current protected submission semantics.

#### Scenario: Clicking an Invoice row opens Invoice's approval screen

GIVEN the dashboard lists a pending Invoice approval item
WHEN the user selects its row
THEN they are navigated to Invoice's own payment-approval screen, not an inline action on the dashboard

#### Scenario: Expense item remains navigation-only

GIVEN the dashboard lists a pending Expense approval item
WHEN the user selects its review control
THEN the user is navigated to the expense's own decision screen and no inline expense decision is exposed

#### Scenario: Timesheet inline action remains protected

GIVEN the dashboard lists a pending Timesheet approval item for an eligible user
WHEN the user invokes its existing approve or reject control
THEN the existing POST action and its CSRF protection are submitted without a change to approval eligibility or outcome

### Requirement: Visibility is permission-consistent with each domain

The dashboard MUST only include items the current user is eligible to
approve per that domain's own approval-eligibility rule (Expense policy,
Invoice level eligibility, Timesheet team-lead voter). It MUST NOT surface
items beyond what each domain's own authorization would already allow.

#### Scenario: Dashboard does not leak items outside a user's eligibility

GIVEN user U is not the team lead of project P and P has a pending
  Timesheet entry
WHEN U opens the dashboard
THEN P's pending Timesheet entry does not appear for U

### Requirement: Dashboard uses consistent accessible GPPro workflow presentation

The approvals dashboard MUST retain its domain-by-domain organization while using the GPPro visual vocabulary for sections, statuses, metadata, tables, actions, and visible keyboard focus in both supported themes. Review links and existing action controls MUST remain native, descriptively named, and keyboard operable.

#### Scenario: Pending items are presented by domain

GIVEN an eligible user has pending expense, invoice, or timesheet items
WHEN the user opens the approvals dashboard in either theme
THEN each populated domain is presented as a consistent GPPro workflow section with understandable item status and review or action controls

#### Scenario: Keyboard user reaches a review control

GIVEN an eligible user traverses a populated dashboard with only the keyboard
WHEN focus reaches an expense or invoice review link or a timesheet action control
THEN the control has an understandable name, a visible focus indicator, and remains operable

### Requirement: Dashboard remains usable at target viewport widths

The approvals dashboard MUST remain usable at 360px, 768px, and 1024px or wider in both themes, with reduced motion enabled. At 360px the page MUST NOT have horizontal overflow and review or approval controls MUST remain reachable; intentional scrolling inside a dense table MAY remain available.

#### Scenario: Dashboard adapts to the viewport matrix

GIVEN an eligible user views empty and populated dashboard domains at 360px, 768px, and 1024px or wider
WHEN action groups and domain tables render in either theme with reduced motion enabled
THEN page-level overflow is absent, domain context remains understandable, and all existing review or approval controls remain reachable

### Requirement: Dashboard presentation preserves authorization and protected submissions

Dashboard presentation changes MUST NOT change pending-item eligibility, domain authorization, routes, HTTP methods, existing timesheet form submission behavior, CSRF token names or validation, or approval business rules.

#### Scenario: Updated dashboard enforces existing domain protections

GIVEN a user opens or acts through the updated approvals dashboard
WHEN visibility and any existing dashboard action are evaluated
THEN only currently eligible items are shown and existing authorization, method, CSRF, and business-rule outcomes apply unchanged
