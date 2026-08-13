# Proposal: Expense Category

## Intent

`Expense` today classifies nothing: overhead spend is identified only by the
free-text `description`. Office rent, equipment, electricity, phone, and other
service costs all land in the same undifferentiated list, so "how much of what
kind of overhead did we push into projects" cannot be answered without reading
every row by hand. Add a company-defined category label to the expense header
so overhead spend is classified at capture time, while the existing per-project
percentage allocation stays exactly as it is.

The product owner's framing, as stated:

> "Categorize company overhead expenses — office rent, equipment, electricity,
> phone/cell, other services — spend that isn't billed to a single client the
> way project work is, but which still needs to go through the exact same
> per-project percentage allocation mechanism."

## Naming

`Expense.category`, a nullable `VARCHAR(20)` column on the existing
`gppro_expenses` table, driven by `CATEGORY_*` constants and a `CATEGORIES`
array const on `App\Entity\Expense`. No new entity, no new table, no new
namespace. This mirrors the entity's own `STATUS_*`/`RECURRENCE_*` pattern and
`FxRate::INDICATOR_*`.

An `ExpenseCategory` admin entity (the `ExpenseApprovalLevel` shape) was
rejected: approval levels earned that cost because they are genuinely
runtime-tunable, need referential integrity against `gppro_roles`, and carry
cross-row invariants. A short, company-defined list of overhead kinds has none
of those properties, and would drag in a controller, a form, two templates, a
repository, and a dedicated `manage_expense_categories` permission for a
five-to-six item taxonomy.

## Business Rules

| # | Rule |
|---|------|
| 1 | Category classifies company overhead spend, not client-billable work |
| 2 | The category set is company-defined and fixed; end users do not add to it at runtime |
| 3 | Category is a header-level label on `Expense`; it never affects `ExpenseAllocation` |
| 4 | Categorized overhead is still distributed across projects by the same manual percentage mechanism |
| 5 | Category has no effect on amount, approval levels, status transitions, or cross-charge |
| 6 | A recurring expense's generated period copy carries the same category as its source |
| 7 | Category is required whenever an expense is created or edited through the form; only pre-existing legacy rows (saved before this change) may be uncategorized until next edited |

## Category Set (decision)

Six values, frozen as constants and translation keys:

| constant | value | covers |
|---|---|---|
| `CATEGORY_RENT` | `rent` | office rent and lease |
| `CATEGORY_EQUIPMENT` | `equipment` | hardware, furniture, tooling, consumables and supplies |
| `CATEGORY_ELECTRICITY` | `electricity` | electricity supply |
| `CATEGORY_PHONE` | `phone` | phone and mobile plans |
| `CATEGORY_SERVICES` | `services` | other recurring services (internet, water, cleaning) |
| `CATEGORY_OTHER` | `other` | catch-all |

`other` exists deliberately. A fixed five-item list will eventually meet an
expense it does not describe, and without a catch-all the only outcomes are
blocking expense creation or mislabelling the row. `description` stays free
text and remains the place for the specific detail.

## Working Assumptions (confirm in spec/design)

| # | Assumption |
|---|------------|
| A1 | Category is a hardcoded enum on `Expense` (six values above), not an admin-editable entity. Justified by the PO's own framing of a short company-defined list, and by the `FxRate::INDICATOR_*` precedent (a closed "kind" stays a constant even on a dynamic entity) |
| A2 | The `category` field is **required in the form** (PO decision): the `ChoiceType` is `required: true` with `Assert\NotBlank`, so any expense create or edit must submit a category. The DB column stays nullable so no backfill migration runs against existing `gppro_expenses` rows — a legacy row with no category simply shows uncategorized until the next time someone saves it, at which point the form requires picking one |
| A3 | No coupling between category and recurrence in this change. Selecting "Rent" does not auto-select or suggest monthly recurrence. The two fields stay independent and independently testable; the UX nudge is deferred, not core to the ask |
| A4 | No new filter or search UI for category. `templates/expense/index.html.twig` renders no filter UI for any field today, so category appears as a visible column only and the page stays internally consistent |
| A5 | `RecurringExpenseGenerator::cloneForPeriod()` MUST copy `category` onto the generated copy. Confirmed gap: that method explicitly copies `description`, `amount`, `expenseDate`, `recurrence`, `createdBy`, and allocations, and would silently drop the category. This is a correctness requirement, not optional polish |

Assumptions follow this project's convention: locked here so implementation is
unambiguous, superseded by an explicit PO correction if one arrives.

## Scope

### In Scope
- `CATEGORY_*` constants + `CATEGORIES` array on `App\Entity\Expense`, with a
  nullable `category` column, `Assert\Choice`, getter/setter.
- Migration adding `category VARCHAR(20) DEFAULT NULL` to `gppro_expenses`.
- `category` `ChoiceType` in `src/Form/ExpenseForm.php`, same shape as the
  existing `recurrence` field.
- Category rendered in `templates/expense/edit.html.twig`,
  `view.html.twig`, and as a column in `index.html.twig`.
- `expense.category` label + one `expense.category.<value>` key per value in
  `translations/messages.en.xlf` and `messages.es.xlf`.
- `RecurringExpenseGenerator::cloneForPeriod()` copies the category (A5).
- `category` is a required field (`required: true` + `Assert\NotBlank`) on
  `ExpenseForm`, so creating or editing an expense forces a category choice
  (A2/Rule 7).
- Test coverage for the form choice, the required-field validation, and for
  category propagation through recurring generation.

### Out of Scope
- Runtime-addable or renameable categories without a deploy — explicitly
  excluded by A1; changing the set is a code change plus migration.
- Per-category approval thresholds or approval-level configuration — already
  excluded by the archived `expense-allocation` proposal and still excluded here.
- Per-project, per-customer approval level configuration.
- Category-based filtering, search, grouping, or list UI (A4).
- Category-driven recurrence defaults or any other cross-field UX coupling (A3).
- Backfill or migration of existing (legacy) expenses to a category — they
  stay uncategorized until next edited, per A2.
- Spend-by-category reporting, dashboards, or export.
- Blocking submit/approve on a missing category for *already-saved* legacy
  rows that are not being edited — the requirement applies to the save
  action, not retroactively to rows already in the database.
- Any change to `ExpenseAllocation`, `AllocationSplitter`, approval flow,
  cross-charge, or `ExpenseVoter`.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `expense-allocation`: an expense header may carry a category from a fixed
  company-defined set, and a generated recurring period copy preserves it.

## Approach

Follow the entity's own closed-set precedent end to end: constants + array
const + `#[Assert\Choice(choices: self::CATEGORIES)]` on the entity, a
`ChoiceType` in `ExpenseForm` mapping translation keys to constants exactly as
`recurrence` does, and `('expense.category.' ~ expense.category)|trans` in the
templates with a null-safe fallback for uncategorized rows. The migration is a
single additive nullable column, so no existing row changes and no data
transformation runs. `RecurringExpenseGenerator` gains one setter call inside
`cloneForPeriod()`. Nothing in `src/Expense/` other than that generator is
touched, and no allocation, approval, or cross-charge logic changes.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/Entity/Expense.php` | Modified | `CATEGORY_*` consts, `CATEGORIES`, nullable `category` column, `Assert\Choice`, accessors |
| `migrations/Version*.php` | New | `ALTER TABLE gppro_expenses ADD category VARCHAR(20) DEFAULT NULL` |
| `src/Form/ExpenseForm.php` | Modified | `category` `ChoiceType`, `required: true` + `Assert\NotBlank` |
| `templates/expense/edit.html.twig` | Modified | `form_row(form.category)` next to recurrence |
| `templates/expense/view.html.twig` | Modified | Translated category display, null-safe |
| `templates/expense/index.html.twig` | Modified | Category column only; no filter UI |
| `translations/messages.en.xlf`, `messages.es.xlf` | Modified | `expense.category` + six value keys, ids continuing after `gpExpense40` |
| `src/Expense/RecurringExpenseGenerator.php` | Modified | `cloneForPeriod()` copies category (A5) |
| `tests/Form/ExpenseFormTest.php` | Modified | Category choice case |
| `src/Entity/ExpenseAllocation.php`, `src/Voter/ExpenseVoter.php`, `config/packages/gppro.yaml` | Unchanged | No allocation, permission, or voter change |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Recurring copies silently lose their category | Med | A5 is a locked requirement with an explicit test asserting the generated copy's category equals the source's |
| The frozen six-value set does not fit a real expense | Med | `CATEGORY_OTHER` catch-all plus free-text `description`; set changes are a normal migration, not an emergency |
| PO later wants runtime-editable categories | Low | A1 is a reversible decision: constants can be replaced by an `ExpenseCategory` entity with a data migration mapping slug to row |
| `services` vs `other` reads ambiguously to users | Med | Translation strings must disambiguate ("other services" vs "other"); confirm final wording in spec |
| Uncategorized legacy rows render badly | Low | Null-safe template fallback (em dash or "—"), asserted in the view test |
| Scope creep into spend-by-category reporting | Med | Explicitly out of scope; the column is the data source for a later report |

## Rollback Plan

Revert the migration (drops the `category` column) and revert the entity, form,
template, translation, and generator changes. The column is nullable and
additive, so no existing expense data is transformed and nothing else depends on
it. Allocation, approval, recurrence, and cross-charge behavior are untouched
either way.

## Dependencies

- Existing `Expense`, `ExpenseForm`, `RecurringExpenseGenerator`, and the
  `EXPENSES` permission set (reused, not extended).

## Success Criteria

- [ ] A new expense can be saved with any of the six categories, and the stored value matches the constant.
- [ ] Submitting the create or edit form with no category selected is rejected by validation.
- [ ] An invalid category value is rejected by `Assert\Choice`.
- [ ] Existing (legacy) expenses created before this change load and display with no category, but saving any edit to them requires picking one.
- [ ] A monthly recurring expense with category `rent` generates a period copy whose category is also `rent`.
- [ ] Category appears as a translated label in the expense list, view, and edit screens, in both `en` and `es`.
- [ ] Allocation percentages, CLP splitting, approval levels, and cross-charge behave identically to before.
