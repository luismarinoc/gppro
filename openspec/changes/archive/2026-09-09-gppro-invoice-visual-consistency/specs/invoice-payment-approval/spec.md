# Delta for Invoice Payment Approval

## ADDED Requirements

### Requirement: Payment action grouping preserves approval semantics

The invoice edit surface MUST present the currently available submit, approve, and reject payment actions as a coherent accessible action group in standard and modal rendering contexts. The presentation MUST NOT add payment-approval progress, summaries, labels, or status language, and MUST NOT display retroactive approval messaging or controls for historical PAID invoices.

#### Scenario: Available payment action is grouped without new approval state

- GIVEN an authorized user views an invoice with an existing payment action available
- WHEN the invoice edit surface renders in a standard or modal context
- THEN the existing action remains native, descriptively named, keyboard operable, and visibly focused within the action group without a new approval-progress or status treatment

#### Scenario: Historical PAID invoice remains action-free

- GIVEN an invoice was already PAID without retroactive approval records
- WHEN an authorized user views its edit surface
- THEN no payment-approval message or payment action control is introduced

### Requirement: Approval-level administration has consistent workflow presentation

Invoice payment approval-level list and edit surfaces MUST present their existing table, actions, form hierarchy, and contextual empty state using the shared workflow visual vocabulary. Dense lists MUST preserve local responsive containment and existing action controls MUST remain reachable at supported viewport widths.

#### Scenario: Administrator views an empty or populated approval-level list

- GIVEN an authorized administrator opens the approval-level list with zero or more configured levels
- WHEN the list renders at a supported viewport width
- THEN its contextual empty state or level rows, actions, and local table containment remain understandable and reachable

#### Scenario: Administrator edits an approval level

- GIVEN an authorized administrator opens the approval-level create or edit form
- WHEN the form renders in either supported theme
- THEN its existing fields, validation feedback, save action, and cancel action remain visually distinguishable and keyboard operable

### Requirement: Payment-approval presentation retains protected contracts

Invoice payment action and approval-level administration presentation MUST NOT change existing authorization, approval eligibility, frozen-level rules, routes, HTTP methods, CSRF token names or validation, field names, validation, deletion rules, audit behavior, or payment-transition outcomes.

#### Scenario: Existing protected payment and administration actions retain their outcome

- GIVEN a user invokes an existing payment or approval-level administration action through an updated surface
- WHEN the server evaluates the request
- THEN the existing authorization, method, CSRF, validation, and domain-rule outcome applies unchanged
