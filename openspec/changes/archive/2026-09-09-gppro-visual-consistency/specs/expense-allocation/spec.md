# Delta for Expense Allocation

## ADDED Requirements

### Requirement: Authenticated expense workflows use the GPPro visual vocabulary

The authenticated expense list, pending list, create/edit, detail, and decision surfaces MUST use the established GPPro semantic visual vocabulary for workflow headers, actions, metadata, status, tables, forms, and focus treatment in both supported themes. The presentation MUST retain the existing expense destinations, conditional edit-versus-view destination, and domain data.

#### Scenario: Expense workflow renders consistently in either theme

- GIVEN an authorized user viewing an authenticated expense list, pending list, edit form, detail, or decision surface
- WHEN the user selects the light or dark theme
- THEN the surface presents its workflow structure, statuses, metadata, actions, tables, and form controls using the GPPro visual vocabulary

#### Scenario: Conditional row destination remains unchanged

- GIVEN an expense row whose existing state and permissions select either its edit or view destination
- WHEN the row is rendered after the visual update
- THEN it retains that same destination

### Requirement: Expense currency-preview feedback is advisory and accessible

When an expense currency preview is refreshed, the expense form MUST show a non-blocking loading state and MUST show an understandable unavailable state when the preview cannot be obtained. Dynamic informational feedback MUST be announced through appropriately scoped status semantics. The preview MUST NOT substitute for server validation, persisted amounts, allocation recalculation, or form and flash errors.

#### Scenario: Currency preview announces loading and availability

- GIVEN an expense editor changes a value that refreshes the currency preview
- WHEN the preview request is pending and then succeeds
- THEN the form exposes an announced loading state followed by the available advisory preview

#### Scenario: Currency preview is unavailable

- GIVEN an expense editor changes a value that refreshes the currency preview
- WHEN the preview request fails or has no available result
- THEN the form exposes an announced non-blocking unavailable state and does not invent a replacement amount

#### Scenario: Server validation remains authoritative

- GIVEN an expense form has a server-side validation error or an unavailable currency preview
- WHEN the user submits the form
- THEN the existing Symfony form error or flash feedback remains the authoritative blocking result

### Requirement: Expense row navigation and decision controls are keyboard-safe

A designated whole-row expense navigation affordance MUST be focusable, have an accessible link name, visibly indicate focus, and activate its existing destination on Enter. It MUST NOT activate when the originating interaction is a nested link, button, input, label, or menu control. Expense approval and rejection controls, including decision notes, MUST have visible labels and remain reachable and operable by keyboard.

#### Scenario: Keyboard user opens a designated expense row

- GIVEN a keyboard user focuses a designated whole-row expense navigation affordance
- WHEN the user presses Enter
- THEN the user navigates to the row's existing accessible destination and can see the focus indicator before activation

#### Scenario: Nested control retains its own interaction

- GIVEN a designated expense row contains a nested interactive control
- WHEN a user clicks or activates that nested control with the keyboard
- THEN the row navigation does not activate and the nested control receives its normal interaction

#### Scenario: Approver enters a decision note by keyboard

- GIVEN an authorized approver opens an expense decision surface
- WHEN the approver traverses the decision controls using only the keyboard
- THEN the note field has a visible label and the existing approve and reject controls are reachable and operable

### Requirement: Expense workflow remains usable at target viewport widths

Authenticated expense list, pending, create/edit, detail, and decision surfaces MUST remain usable at 360px, 768px, and 1024px or wider in both themes, with reduced motion enabled. At 360px the page MUST NOT have horizontal overflow and primary actions and controls MUST remain reachable; intentional scrolling inside an irreducibly dense table MAY remain available.

#### Scenario: Expense workflow at phone width

- GIVEN an authorized user views any authenticated expense workflow at 360px
- WHEN headers, actions, allocations, forms, and tables render
- THEN there is no page-level horizontal overflow and primary actions and controls remain visible and operable

#### Scenario: Expense workflow at tablet and desktop widths

- GIVEN an authorized user views an authenticated expense workflow at 768px and at 1024px or wider
- WHEN the user traverses it by keyboard with reduced motion enabled in either theme
- THEN layout order, focus indicators, readable data, and any intentional table scrolling remain usable

### Requirement: Expense presentation changes preserve protected behavior

Expense presentation changes MUST NOT change existing routes, HTTP methods, permission or authorization decisions, CSRF token names or validation, business rules, persisted calculations, or approval and rejection semantics.

#### Scenario: Protected expense submission and decision behavior is unchanged

- GIVEN a user submits, approves, rejects, or attempts an unauthorized expense action through an updated surface
- WHEN the existing endpoint processes the request
- THEN the existing route, method, authorization, CSRF protection, validation, calculation, and domain outcome apply unchanged
