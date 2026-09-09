# Quotation Management Specification

## Purpose

Define the authenticated quotation list, create/edit, and detail workflow presentation while preserving quotation pricing, conversion, saving, and public-document behavior.

## Requirements

### Requirement: Authenticated quotation workflows use the GPPro visual vocabulary

The authenticated quotation list, create/edit, and detail surfaces MUST use the established GPPro semantic visual vocabulary for workflow headers, filters, action groups, metadata, statuses, line and total tables, editable line items, and summary presentation in both supported themes. The quotation PDF and public quotation response surfaces MUST remain outside this requirement.

#### Scenario: Authenticated quotation surfaces render consistently

- GIVEN an authorized user opens an authenticated quotation list, create/edit form, or detail surface
- WHEN the user selects the light or dark theme
- THEN the workflow presents its filters, actions, metadata, statuses, lines, totals, and summary using the GPPro visual vocabulary

#### Scenario: Public and document surfaces remain unchanged

- GIVEN a quotation is rendered as a PDF or through a public response page
- WHEN the authenticated quotation presentation is updated
- THEN the PDF and public response surface are not redesigned by this capability

### Requirement: Quotation interactions remain accessible and keyboard-safe

Quotation navigation and editing controls MUST use native anchors or buttons where available. A designated whole-row quotation navigation affordance, when retained, MUST be focusable, have an accessible link name, visibly indicate focus, and activate its existing destination on Enter. It MUST NOT activate from a nested link, button, input, label, or menu control. Existing native line-management controls and their focus behavior MUST remain keyboard operable.

#### Scenario: Keyboard user opens a designated quotation row

- GIVEN a keyboard user focuses a designated whole-row quotation navigation affordance
- WHEN the user presses Enter
- THEN the user navigates to the row's existing destination and can see the focus indicator before activation

#### Scenario: Nested quotation control retains its own interaction

- GIVEN a designated quotation row contains a nested interactive control
- WHEN a user clicks or activates that control with the keyboard
- THEN row navigation does not activate and the nested control receives its normal interaction

#### Scenario: Editor adds a quotation line by keyboard

- GIVEN an authorized user is editing a quotation
- WHEN the user activates the existing add-line control by keyboard
- THEN a line is added subject to the existing line limit and focus follows the existing line-editor behavior

### Requirement: Quotation lookup feedback uses the shared advisory state contract

Authenticated quotation exchange-rate feedback MUST use the shared finance workflow visual-state contract. It MUST visibly and accessibly distinguish loading, available, and unavailable advisory lookup states without fabricating a rate, total, or persisted calculation.

#### Scenario: Exchange rate is unavailable

- GIVEN an authorized user edits a quotation that requests an exchange-rate lookup
- WHEN the lookup fails or produces no available rate
- THEN the shared non-blocking unavailable state is visible and announced without a fabricated rate or total

### Requirement: Responsive quotation workflows preserve critical information and actions

Authenticated quotation list, create/edit, and detail surfaces MUST remain usable at 360px, 768px, and 1024px or wider in both themes, with reduced motion enabled. At 360px the page MUST NOT have horizontal overflow and primary actions MUST remain reachable. At 768px the line editor or detail and summary MUST preserve a coherent reading or editing order without an obstructive sticky summary. At 1024px or wider, list density, intentional table scrolling, totals, focus indicators, and sticky-summary behavior MUST remain correct.

#### Scenario: Quotation workflow at phone width

- GIVEN an authorized user views an authenticated quotation workflow at 360px
- WHEN filters, line items, totals, summary, and actions render
- THEN there is no page-level horizontal overflow and primary actions and essential quotation information remain reachable

#### Scenario: Quotation summary at tablet width

- GIVEN an authorized user edits or views a quotation at 768px
- WHEN the layout adapts for the viewport
- THEN lines, metadata, totals, and summary retain a coherent order and the summary does not obstruct editing or reading

#### Scenario: Quotation workflow at desktop width

- GIVEN an authorized user views an authenticated quotation workflow at 1024px or wider
- WHEN the user traverses it by keyboard in either theme with reduced motion enabled
- THEN list density, intentional table scrolling, focus indicators, totals, and sticky-summary behavior remain usable

### Requirement: Quotation presentation preserves authoritative behavior

Quotation presentation changes MUST NOT change quotation selectors, native controls, line limits, routes, HTTP methods, permissions, authorization decisions, CSRF token names or validation, client-side preview behavior, server validation, pricing or conversion rules, persisted calculations, or save outcomes.

#### Scenario: Updated quotation form preserves save behavior

- GIVEN an authorized or unauthorized user submits an updated quotation surface
- WHEN the existing endpoint processes the request
- THEN the existing route, method, permissions, CSRF protection, selector behavior, line limits, validation, pricing, conversion, calculation, and persistence outcome apply unchanged

## Acceptance Criteria

- Authenticated quotation workflows have a consistent GPPro presentation in both themes.
- Keyboard navigation preserves native and nested-control behavior.
- Exchange-rate status is accessible and advisory.
- The 360px, 768px, and 1024px-or-wider viewport behavior remains usable.
- Public response pages and quotation PDFs remain outside the change.
