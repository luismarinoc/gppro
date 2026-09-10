# Milestone Invoicing Specification

## Purpose

Provide a coherent, responsive, accessible GPPro presentation for the authenticated milestone-invoicing customer chooser and customer-scoped selection workflow without changing milestone invoicing behavior.

## Requirements

### Requirement: Customer chooser and milestone selection use an invoice workflow hierarchy

Milestone invoicing MUST present the existing customer chooser and customer-scoped invoiceable-milestone selection workflow with consistent workflow surfaces, table containment, batch-action hierarchy, and contextual empty states.

#### Scenario: User selects a customer with invoiceable milestones

- GIVEN an authorized user opens milestone invoicing and selects a customer with invoiceable milestones
- WHEN the customer-scoped selection screen renders
- THEN customer context, milestone rows, selection controls, and the existing batch action are understandable and reachable

#### Scenario: User has no eligible customers or milestones

- GIVEN an authorized user has no eligible customers or the selected customer has no invoiceable milestones
- WHEN the corresponding milestone-invoicing screen renders
- THEN a contextual empty state identifies the absent content without an error or a table-only empty message

### Requirement: Milestone warning and disabled controls remain distinguishable

Milestone invoicing MUST preserve the existing no-billable-hours warning semantics and disabled selection controls while making warning content and disabled controls distinguishable in both supported themes.

#### Scenario: Milestone lacks billable hours

- GIVEN a displayed invoiceable milestone has no billable hours under the existing eligibility rules
- WHEN the selection screen renders in either supported theme
- THEN the existing warning remains understandable and its existing selection control remains disabled and visually distinguishable

### Requirement: Milestone invoicing remains responsive and accessible

Milestone invoicing MUST remain understandable at 360px, 768px, and 1024px or wider in light and dark themes with reduced motion enabled. Dense tables MAY use intentional local horizontal scrolling, but the page MUST NOT have horizontal overflow and existing controls MUST remain keyboard operable with visible focus.

#### Scenario: User operates a dense milestone table at a narrow viewport

- GIVEN an authorized user views a populated milestone selection table at 360px
- WHEN the table and batch action render
- THEN page-level horizontal overflow is absent, local table containment preserves access to selection and batch controls, and keyboard focus is visible

### Requirement: Milestone presentation preserves selection and generation contracts

Milestone-invoicing presentation MUST NOT change customer access scope, routes, forms, field names, CSRF contracts, data-table configuration, reload events, warning or checkbox selectors, server revalidation, invoice generation, calculations, persistence, permissions, or authorization.

#### Scenario: Existing milestone batch submission retains protection and outcome

- GIVEN a user submits an existing milestone selection through the updated presentation
- WHEN the client and server process the submission
- THEN existing reload hooks, validation, access scoping, CSRF protection, server revalidation, and invoice-generation outcomes apply unchanged
