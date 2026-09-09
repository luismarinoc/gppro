# Invoice Workflow Presentation Specification

## Purpose

Provide a coherent, responsive, accessible GPPro presentation for authenticated invoice archive, history, creation, and preview workflows without changing invoice behavior or outputs.

## Requirements

### Requirement: Invoice archive and history use coherent workflow hierarchy

The invoice archive/listing and history summary MUST present their existing filters, tables, lifecycle badges, actions, and summary data with a consistent invoice workflow hierarchy. Existing lifecycle badge meaning and status treatment MUST remain authoritative, and the archive MUST NOT add a payment-pending indicator.

#### Scenario: User views a populated invoice archive

- GIVEN an authorized user opens an invoice archive with filtered invoice rows and history summary data
- WHEN the page renders
- THEN filters, archive rows, existing lifecycle badges, actions, and the history summary are visually distinguishable without a new payment-pending indicator

#### Scenario: Archive table is dense at a narrow viewport

- GIVEN an authorized user views the populated archive at 360px, 768px, or 1024px or wider
- WHEN archive or history tables require more horizontal space than the viewport
- THEN intentional local table scrolling MAY be available, page-level horizontal overflow is absent, and each existing action remains reachable

### Requirement: Invoice creation and preview form one coherent workspace

The invoice creation and preview workspace MUST group the existing filter, customer previews, nested entry tables, totals, empty states, and preview/save actions as one coherent invoice workflow while retaining dense operational information.

#### Scenario: User previews invoices for available customer data

- GIVEN an authorized user provides filter criteria that produce customer invoice previews
- WHEN the workspace renders
- THEN each customer preview, nested entry data, existing totals, and existing preview/save actions are understandable and actions remain reachable

#### Scenario: User has no previewable invoice content

- GIVEN an authorized user opens or filters the creation workspace without previewable invoice content
- WHEN the relevant preview section renders
- THEN a contextual empty state identifies the absent content without replacing server feedback

### Requirement: Invoice workspace presentation preserves interaction contracts

Invoice archive and creation/preview presentation MUST NOT change routes, query parameters, filters, pagination, export flow, data-table configuration, status URLs, action-menu or modal-edit behavior, form IDs, field names, token nodes, CSRF contracts, data attributes, JavaScript hooks, calculations, server errors, flash messages, persistence, or rendered output.

#### Scenario: Existing archive and preview interaction completes unchanged

- GIVEN a user performs an existing archive, modal-edit, preview, or save interaction through an updated invoice workspace
- WHEN the interaction is submitted or navigated
- THEN the existing client hook and server-authoritative route, protection, calculation, validation, and outcome apply unchanged

### Requirement: Invoice workspace remains accessible across supported presentation conditions

Invoice archive and creation/preview workflows MUST remain understandable at 360px, 768px, and 1024px or wider in light and dark themes with reduced motion enabled. Existing controls MUST retain understandable names, visible keyboard focus, and operability.

#### Scenario: Keyboard user traverses an invoice workspace

- GIVEN an authorized user traverses an empty or populated included invoice workspace with only the keyboard
- WHEN focus reaches an existing filter, action, preview/save control, or table action
- THEN the control is reachable, visibly focused, and operable without page-level horizontal overflow
