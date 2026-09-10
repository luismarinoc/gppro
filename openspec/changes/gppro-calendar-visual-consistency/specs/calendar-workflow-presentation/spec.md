# Calendar Workflow Presentation Specification

## Purpose

Define a cohesive, theme-aware GPPro presentation for the authenticated Calendar workflow while preserving all calendar, timesheet, and integration behavior.

## Requirements

### Requirement: Calendar workflow uses the shared visual vocabulary

The authenticated Calendar workflow MUST present its calendar shell, optional filter surface, optional drag-and-drop source surfaces, and FullCalendar controls through the established GPPro visual vocabulary and `--gp-*` semantic tokens. The presentation MUST be scoped to Calendar and MUST NOT alter unrelated workflow consumers.

#### Scenario: Calendar renders with optional workflow surfaces

- GIVEN an authenticated user opens Calendar with the filter and one or more drag source lists available
- WHEN the page renders in either supported theme
- THEN the calendar shell, filter, source lists, toolbar, and event surfaces are visually coherent through the shared semantic vocabulary

#### Scenario: Calendar renders without a sidebar

- GIVEN an authenticated user opens Calendar without a filter or populated drag source list
- WHEN the page renders
- THEN the calendar remains a coherent standalone workflow surface

### Requirement: Calendar states have light and dark theme parity

The Calendar workflow MUST provide distinguishable and readable toolbar, event, current-day, weekend, selection, hover, disabled, and focus states in light and dark themes. Existing calendar event-source colors and text-contrast behavior MUST remain authoritative.

#### Scenario: Calendar is readable at desktop width in both themes

- GIVEN an authenticated user views Calendar at a 1024px viewport in light or dark theme
- WHEN the toolbar, calendar grid, events, current day, weekend, and selected state render
- THEN the states are distinguishable and readable without changing the active theme mechanism or event-source presentation semantics

#### Scenario: Keyboard focus is visible

- GIVEN an authenticated user navigates existing Calendar controls or drag-source entries by keyboard
- WHEN a focusable control or entry receives focus
- THEN visible focus is distinguishable from adjacent surfaces in light and dark themes

### Requirement: Calendar layout is responsive and locally contained

The Calendar workflow MUST remain readable and locally contain wide calendar content at 360px, 768px, and 1024px viewports in light and dark themes. Calendar-specific width handling MUST NOT create page-level horizontal overflow.

#### Scenario: Narrow calendar viewport remains contained

- GIVEN an authenticated user views Calendar at a 360px viewport in light or dark theme
- WHEN the calendar requires more inline space than the viewport provides
- THEN any horizontal containment is limited to the Calendar workflow and the document does not gain page-level horizontal overflow

#### Scenario: Medium viewport preserves hierarchy

- GIVEN an authenticated user views Calendar at a 768px viewport in light or dark theme with optional sidebar content available
- WHEN the page renders
- THEN the calendar and available sidebar surfaces remain readable, visually ordered, and locally contained

#### Scenario: Wide viewport preserves workflow composition

- GIVEN an authenticated user views Calendar at a 1024px viewport in light or dark theme with optional sidebar content available
- WHEN the page renders
- THEN the calendar and sidebar retain a coherent multi-surface workflow composition without page-level horizontal overflow

### Requirement: Existing Calendar contracts remain unchanged

Calendar presentation changes MUST preserve existing routes, query parameters, server behavior, persistence, APIs, permissions, translations, generated assets, and Google or other calendar-source integrations. The changes MUST preserve the `#timesheet_calendar` and `#calendar-form` identifiers; FullCalendar initialization, options, permissions, event sources, URLs, and event-source semantics; and existing timesheet create, edit, update, and delete behavior.

#### Scenario: Existing calendar interaction remains server-authoritative

- GIVEN an authenticated user performs an existing calendar navigation, filter, timesheet, or calendar-source interaction
- WHEN the presentation layer is applied
- THEN the existing route or query parameters, authorization result, request behavior, persisted outcome, and integration behavior remain unchanged

#### Scenario: FullCalendar target and configuration remain intact

- GIVEN the authenticated Calendar page renders after the presentation update
- WHEN the Calendar client initializes
- THEN `#timesheet_calendar` remains the initialization target and the existing FullCalendar options, event sources, URLs, and permissions retain their prior behavior

### Requirement: Drag-and-drop source contracts remain intact

When drag-and-drop sources are available, their presentation MUST improve readability and local containment without changing their selectors or data contracts. The `.external-events`, `.external-event`, and `.draggable` selectors and the `data-method`, `data-route`, `data-route-replacer`, and `data-entry` attributes MUST remain available with their existing meanings.

#### Scenario: Drag source remains usable

- GIVEN Calendar renders one or more populated drag-and-drop source lists
- WHEN a user focuses, selects, or drags an existing source entry
- THEN the source remains readable and locally contained and its existing selector and data-attribute contract supports the unchanged drag-and-drop behavior

## Acceptance Criteria

- Calendar presentation is coherent at 360px, 768px, and 1024px viewports in light and dark themes.
- Keyboard focus is visibly distinguishable for existing Calendar controls and drag-source entries.
- Calendar-specific width handling does not create page-level horizontal overflow.
- Calendar behavior, DOM/JavaScript contracts, integrations, persistence, APIs, permissions, translations, and generated assets remain unchanged.
