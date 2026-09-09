# Delta for GPPro Visual System

## ADDED Requirements

### Requirement: Invoice workflow presentation is semantically scoped

Invoice operational workflows MUST use the established GPPro workflow vocabulary and `--gp-*` semantic tokens through invoice-specific presentation scoping. Invoice-specific presentation MUST NOT create a second token system or alter the rendering of unrelated workflow consumers.

#### Scenario: Invoice surface resolves through the shared visual system

- GIVEN an authenticated user views an included invoice operational surface
- WHEN its background, surfaces, text, controls, borders, statuses, disabled controls, or focus treatment render
- THEN the treatment uses the established semantic visual contract within invoice-specific scope

#### Scenario: Unrelated workflow remains isolated

- GIVEN a non-invoice GPPro workflow consumer renders
- WHEN invoice-specific presentation is available
- THEN the consumer retains its existing presentation and behavior

### Requirement: Invoice workflows have theme and keyboard-focus parity

Included invoice operational workflows MUST provide distinguishable surfaces, text, borders, existing lifecycle statuses, controls, disabled controls, and visible keyboard focus in both supported themes. Existing protected actions MUST remain native, descriptively named, keyboard operable, and visibly focused.

#### Scenario: Invoice workflow is usable in either theme

- GIVEN an authenticated user views an included invoice operational surface in light or dark theme
- WHEN populated or empty content and existing controls render
- THEN surfaces and controls are distinguishable and keyboard focus is visible without changing the selected theme mechanism
