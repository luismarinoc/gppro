# GPPro Visual System Specification

## Purpose

Define the shared semantic presentation contract used by authenticated GPPro workflows so existing and newly modernized surfaces render coherently in light and dark themes without changing product behavior.

## Requirements

### Requirement: Stable semantic token contract

The visual system MUST provide stable `--gp-*` semantic tokens for application background, two surface levels, primary and muted text, brand and brand-soft treatment, borders, focus indication, shadows, semantic status colors, outline buttons, and disabled controls. Consumers MUST use the semantic contract rather than depend on theme-specific hard-coded values where the contract provides a value.

#### Scenario: Shared consumer resolves semantic tokens

- GIVEN an authenticated GPPro surface uses the shared visual system
- WHEN it renders a background, surface, text, status, control, or focus treatment
- THEN the treatment resolves through the stable semantic token contract

### Requirement: Complete dark-theme semantic values

When the active document has `data-bs-theme='dark'`, the visual system MUST provide coherent dark values for every semantic token in the shared contract and for the necessary Tabler mappings consumed by GPPro workflow components. The dark values MUST preserve readable primary and muted text, distinguish both surface levels, preserve recognizable semantic statuses, and make focus, outline-button, and disabled-control states perceivable.

#### Scenario: Dark theme renders a complete workflow surface

- GIVEN an authenticated GPPro workflow is viewed with `data-bs-theme='dark'`
- WHEN backgrounds, surfaces, text, statuses, forms, outline buttons, disabled controls, and focusable controls render
- THEN each uses a coherent dark semantic value and remains visually distinguishable

#### Scenario: Existing preference mechanism remains unchanged

- GIVEN a user selects an existing light, dark, or automatic theme preference
- WHEN an authenticated GPPro surface loads
- THEN the existing preference mechanism selects the theme and only the semantic presentation values differ

### Requirement: Theme parity preserves shared consumer behavior

Existing modernized non-finance GPPro consumers and targeted finance consumers MUST retain their behavior when shared semantic tokens are completed. The visual system MUST NOT change routes, authorization, form submission, CSRF protection, persisted data, application business rules, or legally required Kimai attribution.

#### Scenario: Token consumer changes theme without behavior regression

- GIVEN a modernized GPPro surface in light and dark themes
- WHEN the user performs an existing navigation or form interaction
- THEN its route, authorization, submission semantics, CSRF protection, and domain outcome remain unchanged

#### Scenario: Required attribution remains present

- GIVEN an authenticated GPPro surface includes legally required Kimai attribution
- WHEN the visual system is applied in either theme
- THEN the attribution remains present and unchanged

## Acceptance Criteria

- The semantic token names remain stable across themes.
- Both themes provide distinguishable backgrounds, surfaces, text, statuses, controls, and focus treatment.
- Theme completion does not alter authenticated application behavior.
