# Delta for Expense Allocation

## MODIFIED Requirements

### Requirement: Create expense draft

The system MUST allow creating an `Expense` in `draft` state with CLP amount,
description, date, an optional monthly recurrence flag, and a required
company-defined category chosen from a fixed set (`CATEGORY_RENT`,
`CATEGORY_EQUIPMENT`, `CATEGORY_ELECTRICITY`, `CATEGORY_PHONE`,
`CATEGORY_SERVICES`, `CATEGORY_OTHER`). Category MUST NOT affect amount,
approval levels, status transitions, or cross-charge.
(Previously: category was not part of the creation contract; only amount,
description, date, and recurrence were required.)

#### Scenario: Draft created with required fields including category

- GIVEN a user with expense-create permission
- WHEN they submit amount, description, date, and category `rent`
- THEN an `Expense` is created in `draft` state with `category = rent`

#### Scenario: Missing category is rejected at create

- GIVEN a user with expense-create permission
- WHEN they submit amount, description, and date without selecting a category
- THEN the submission is rejected by validation

#### Scenario: Invalid category value is rejected

- GIVEN a user with expense-create permission
- WHEN they submit a category value outside the fixed set
- THEN the submission is rejected by `Assert\Choice`

### Requirement: Generate monthly recurring copies

For an expense flagged monthly, the system MUST generate one new `Expense`
copy per period in `draft`, with the same allocation split and the same
category as the source expense. Generation MUST be idempotent per source
expense and period.
(Previously: the generated copy's category was unspecified; category now
MUST propagate.)

#### Scenario: No duplicate for an already-generated period

- GIVEN a monthly expense already generated for August
- WHEN generation runs again for August
- THEN no duplicate copy is created

#### Scenario: New period generates a fresh draft

- GIVEN a monthly expense with no copy for September
- WHEN generation runs for September
- THEN a new `draft` expense is created with the same split

#### Scenario: Generated copy carries the source category

- GIVEN a monthly expense with category `rent`
- WHEN generation runs for a new period
- THEN the generated `draft` copy has `category = rent`

## ADDED Requirements

### Requirement: Category required on edit and rendered as a translated label

Editing an existing `Expense` MUST require selecting a category from the
fixed set before the save succeeds, including expenses saved before this
change with no category. Category MUST render as a translated label
(`expense.category.<value>`) in the expense list, view, and edit screens in
both `en` and `es` locales, with a null-safe fallback only for legacy rows
not yet edited.

#### Scenario: Editing any expense requires a category

- GIVEN an existing `Expense` with category `equipment`
- WHEN a user edits it and clears the category field
- THEN the save is rejected until a category is selected

#### Scenario: Legacy uncategorized expense requires a category on save

- GIVEN a legacy `Expense` created before this change with no category
- WHEN a user opens it for edit and saves without picking a category
- THEN the save is rejected by validation

#### Scenario: Legacy uncategorized expense displays without error

- GIVEN a legacy `Expense` with no category
- WHEN it is rendered in the list or view screen
- THEN it displays a null-safe placeholder instead of a translation error

#### Scenario: Category displays as a translated label

- GIVEN an `Expense` with category `electricity`
- WHEN the list, view, or edit screen renders it
- THEN the label appears translated in the active locale (`en` or `es`)
