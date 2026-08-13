# Tasks: Expense Category

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~230–300 (13 files: 1 entity, 1 migration, 1 form, 3 templates, 2 translation files, 1 generator, 4 test files) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

Single additive nullable column, no backfill, no index, no allocation/approval/voter/cross-charge change (proposal's own "Low" framing, confirmed by file count and design's own ~120–150-line production estimate plus tests/translations).

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Full `expense-category` change: entity+migration, form, templates, translations, generator, tests | PR 1 (single) | `phpunit tests/Entity/ExpenseValidationTest.php tests/Form/ExpenseFormTest.php tests/Expense/RecurringExpenseGeneratorTest.php tests/Controller/ExpenseControllerTest.php` | `doctrine:migrations:migrate` on test DB, then manual create/edit expense in staging | revert migration `down()` + revert entity/form/template/translation/generator files; column drop is the only DB-level rollback |

## Phase 1: Entity + Migration

- [x] 1.1 RED: create `tests/Entity/ExpenseValidationTest.php` (`KernelTestCase` + `EntityValidationTestTrait`, `#[Group('integration')]`) — invalid `'gasoline'` category produces a violation; `null` category produces no violation; each of the six `CATEGORY_*` values validates clean. Traces: "Invalid category value is rejected".
- [x] 1.2 GREEN: `src/Entity/Expense.php` — add `CATEGORY_RENT|EQUIPMENT|ELECTRICITY|PHONE|SERVICES|OTHER` consts + `CATEGORIES` array after `RECURRENCES` (line 45); add nullable `$category` property with `#[ORM\Column(length: 20, nullable: true)]` + `#[Assert\Choice(choices: self::CATEGORIES)]` after `$recurrence`; add `getCategory()`/`setCategory(?string)` after `setRecurrence()`. Run 1.1 green.
- [x] 1.3 Create `migrations/Version20260813150000.php` — `final class`, description `'Add an overhead category to expenses'`; `up()` adds `category VARCHAR(20) DEFAULT NULL` to `gppro_expenses`; `down()` drops it.
- [x] 1.4 Verify: run the migration against the test DB, confirm `gppro_expenses.category` exists and is nullable.

## Phase 2: Form Required-Field Validation

- [x] 2.1 RED: extend `tests/Form/ExpenseFormTest.php` — assert `category` field present, `required === true`, `placeholder === 'expense.category_placeholder'`, choices count `=== \count(Expense::CATEGORIES)` and contains `Expense::CATEGORY_RENT`. Traces: "Missing category is rejected at create".
- [x] 2.2 GREEN: `src/Form/ExpenseForm.php` — add `use Symfony\Component\Validator\Constraints\NotBlank;`; insert `category` `ChoiceType` between `expenseDate` and `recurrence` (`required: true`, `placeholder: 'expense.category_placeholder'`, `constraints: [new NotBlank()]`, six choices mapping `expense.category.<value>` to `Expense::CATEGORY_*`). Run 2.1 green. Traces: "Draft created with required fields including category", "Editing any expense requires a category", "Legacy uncategorized expense requires a category on save".
- [x] 2.3 Fix regression: add a `category` value to every existing create/edit POST payload in `tests/Controller/ExpenseControllerTest.php` (design Risk #1) — these payloads fail `NotBlank` once 2.2 lands.

## Phase 3: Templates

- [x] 3.1 `templates/expense/edit.html.twig` — add `{{ form_row(form.category) }}` between `expenseDate` and `recurrence`.
- [x] 3.2 `templates/expense/view.html.twig` — add category `<p>` between date and status: `expense.category ? ('expense.category.' ~ expense.category)|trans : 'expense.category_none'|trans`. Traces: "Legacy uncategorized expense displays without error", "Category displays as a translated label".
- [x] 3.3 `templates/expense/index.html.twig` — add `<th>`/`<td>` category column between date and status using the same null-safe expression; change `colspan="5"` to `colspan="6"` on the empty-state row. Note: `templates/expense/pending.html.twig` is deliberately NOT touched (separate approval-queue table) — no task there.

## Phase 4: Translations

- [x] 4.1 `translations/messages.en.xlf` — add trans-units `gpExpense41`–`gpExpense49` after `gpExpense40` (line 2388): `expense.category`, `expense.category_placeholder`, `expense.category_none`, one `expense.category.<value>` per category, exact English strings from design (`"Other services"` for `services`).
- [x] 4.2 `translations/messages.es.xlf` — same 9 ids after `gpExpense40` (line 2368), `xml:space="preserve"`, `state="translated"`, Spanish strings from design (`"Otros servicios"` for `services`).

## Phase 5: Recurring Category Propagation

- [x] 5.1 RED: extend `tests/Expense/RecurringExpenseGeneratorTest.php` — add `->setCategory(Expense::CATEGORY_RENT)` to `createRecurringSource()`, assert the generated copy's category equals `CATEGORY_RENT` in `testGeneratesNewDraftCopyWithTheSameAllocationSplit`; add a new test asserting a `null`-category source generates a `null`-category copy. Traces: "Generated copy carries the source category".
- [x] 5.2 GREEN: `src/Expense/RecurringExpenseGenerator.php` — add `$copy->setCategory($source->getCategory());` after line 76 in `cloneForPeriod()`. Run 5.1 green.

## Phase 6: Verification

- [x] 6.1 Run `phpunit tests/Entity/ExpenseValidationTest.php tests/Form/ExpenseFormTest.php tests/Expense/RecurringExpenseGeneratorTest.php tests/Controller/ExpenseControllerTest.php` — all green (33/33).
- [x] 6.2 Manual/staging: no staging environment available in this sandbox. Automated equivalent verified instead: `lint:twig` passes on all 3 changed templates; `ExpenseControllerTest` requests to `/expense/{id}` render `view.html.twig` for expenses with no category set (the fixture never sets one), exercising the null-safe `expense.category_none` fallback without a rendering error across 14 passing controller tests; `lint:xliff` confirms both `en`/`es` translation files are well-formed with the new `gpExpense41`-`gpExpense49` units. True visual/staging confirmation of translated labels in both locales is deferred to a human reviewer with browser access.
