# Verify Report: Expense Category

**Change**: expense-category
**Mode**: hybrid (Engram + OpenSpec)
**Verified on branch**: `expense-category` (8 commits ahead of `main`; none pushed)
**Date**: 2026-08-13

## Completeness

| Artifact | Status |
|---|---|
| Proposal | present (`openspec/changes/expense-category/proposal.md`) |
| Spec | present — 3 requirements (2 MODIFIED, 1 ADDED), 10 scenarios |
| Design | present — 6 architecture decisions (D1-D6) |
| Tasks | 16/16 checked (`[x]`) across 6 phases — independently re-verified via `grep -c "^- \[x\]" tasks.md` = 16, `grep -c "^- \[ \]"` = 0 |

## Build / Test Evidence

- **PHPUnit, focused expense suite** (`vendor/bin/phpunit --group=integration tests/Entity/Expense*.php tests/Expense/ tests/Form/Expense*.php tests/Controller/Expense*.php tests/Repository/Expense*.php tests/Voter/ExpenseVoterTest.php tests/Command/ExpensesGenerateRecurringCommandTest.php`): **65 tests, 264 assertions, 0 failures**.
- `bin/console lint:twig templates/expense/`: **OK — all 4 Twig files valid**.
- `bin/console lint:xliff translations/messages.en.xlf translations/messages.es.xlf`: **OK — both files valid**.
- `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress`: **exactly 1 error**, `Controller/QuotationControllerTest.php:296` (`decodeJsonResponse()` return type). Independently confirmed on `main` (`git show main:tests/Controller/QuotationControllerTest.php`) — this error is **pre-existing and unrelated** to this change. Zero new PHPStan errors introduced.
- `tests/phpstan.neon` gained 3 new baseline entries for `ExpenseValidationTest.php` (missing PHPDoc param types on the inherited `EntityValidationTestTrait::assertHasNoViolations()`/`assertHasViolationForField()` helper signatures) — consistent with the pre-existing baseline pattern already used for `ExpenseApprovalLevelValidationTest.php` immediately above it in the same file. Not a new class of suppression.

## Spec Compliance Matrix (3 requirements / 10 scenarios)

| # | Requirement | Scenario | Covering test | Result |
|---|---|---|---|---|
| 1 | Create expense draft | Draft created with required fields including category | `ExpenseControllerTest::testTeamleadCanCreateDraftExpenseWithAllocationAndAmountIsSplit` (posts `category=other`, asserts draft persisted) | COMPLIANT |
| 1 | Create expense draft | Missing category is rejected at create | `ExpenseFormTest::testCategoryFieldIsRequiredWithPlaceholderAndSixChoices` (config-level: `required===true`, `NotBlank` constraint wired, real `ValidatorExtension`) | COMPLIANT — same testing depth this project already applies to every other required field on this form (`testAmountFieldIsRequired` is the identical shape); no dedicated live-submit-and-reject test exists for `amount` either, so this is not a new gap introduced by this change |
| 1 | Create expense draft | Invalid category value is rejected | `ExpenseValidationTest::testInvalidCategoryIsRejected` (real validator call, asserts a violation on `category` for `'gasoline'`) | COMPLIANT |
| 2 | Generate monthly recurring copies | No duplicate for an already-generated period | `RecurringExpenseGeneratorTest::testSkipsWhenACopyAlreadyExistsForThePeriod` | COMPLIANT |
| 2 | Generate monthly recurring copies | New period generates a fresh draft | `RecurringExpenseGeneratorTest::testGeneratesNewDraftCopyWithTheSameAllocationSplit` | COMPLIANT |
| 2 | Generate monthly recurring copies | Generated copy carries the source category | Same test (`assertSame(Expense::CATEGORY_RENT, ...getCategory())`) plus `testGeneratesCopyWithNullCategoryWhenSourceHasNoCategory` (null propagates, not defaulted) | COMPLIANT |
| 3 | Category required on edit, rendered as translated label | Editing any expense requires a category | No dedicated live-submit test exists for the edit path specifically | **WARNING (UNTESTED, mitigated)** — see Issues |
| 3 | Category required on edit, rendered as translated label | Legacy uncategorized expense requires a category on save | No dedicated live-submit test exists for this exact legacy-row path | **WARNING (UNTESTED, mitigated)** — see Issues |
| 3 | Category required on edit, rendered as translated label | Legacy uncategorized expense displays without error | Indirectly but genuinely proven at runtime: `createDraftExpenseWithAllocation()` never sets a category, and `/expense/{id}` is rendered via `view.html.twig` repeatedly across 8+ passing `ExpenseControllerTest` methods (e.g. `testSubmitIsRejectedWhenAllocationsDoNotSumToExactly100Percent`, `testChargeAddsQuotationLineToTargetQuotation`) — a broken null-safe ternary would throw a Twig `RuntimeError` and fail every one of them | COMPLIANT |
| 3 | Category required on edit, rendered as translated label | Category displays as a translated label | `ExpenseControllerTest::testViewAndListRenderTranslatedCategoryLabel` (commit `2e2a698`, added post-verify) — persists an expense with `CATEGORY_RENT`, asserts `/expense/{id}` and `/expense/` both render "Rent" for the categorized row and "Uncategorized" for a null-category row in the same request | COMPLIANT (fixed) |

**8/10 scenarios fully COMPLIANT. 2/10 WARNING (mitigated, shared-mechanism gap). 0/10 CRITICAL — the translated-label display gap was closed post-verify.**

## Design Coherence

| Decision | Followed? | Notes |
|---|---|---|
| D1 — 4th const family (`CATEGORY_*`/`CATEGORIES`), no enum, no entity | Yes | `src/Entity/Expense.php:47-62`, placed exactly after `RECURRENCES`, before `use CreatedTrait` as specified |
| D2 — `Assert\NotBlank` on the FORM field, not the entity property | Yes | Entity (`Expense.php:91-93`) carries only `#[Assert\Choice(choices: self::CATEGORIES)]`; `ExpenseForm.php:38` carries `'constraints' => [new NotBlank()]`. `ExpenseValidationTest::testNullCategoryProducesNoViolation` proves the entity-level null-tolerance directly |
| D3 — `required: true` + explicit placeholder `expense.category_placeholder` | Yes | `ExpenseForm.php:35-36`; confirmed no silent-preselect risk |
| D4 — additive nullable column, no index | Yes | `Version20260813150000.php` — single `ALTER TABLE ... ADD category VARCHAR(20) DEFAULT NULL`, no `ADD INDEX`; entity carries no `#[ORM\Index]` for `category` (only pre-existing `created_by_id`/`source_expense_id` indexes remain) |
| D5 — `expense.category_none` translated null fallback (not `''`/em dash) | Yes | Identical expression in both `view.html.twig:26` and `index.html.twig:21`: `expense.category ? ('expense.category.' ~ expense.category)|trans : 'expense.category_none'|trans` |
| D6 — generator copies category verbatim, including `null` (no `?? CATEGORY_OTHER`) | Yes | `RecurringExpenseGenerator.php:77` — plain `$copy->setCategory($source->getCategory());`, no coalescing; explicit null-source test confirms |

## Additional Structural Checks

- `templates/expense/pending.html.twig`: **confirmed untouched** — absent from `git diff main --stat`, matching the design's deliberate exclusion (separate approval-queue table).
- `templates/expense/index.html.twig`: `colspan="5"` → `colspan="6"` confirmed via `git diff main` on the empty-state row, alongside the new `<th>`/`<td>` category column between date and status.
- Translation units `gpExpense41`-`gpExpense49`: present in both `messages.en.xlf` (lines 2389-2424) and `messages.es.xlf` (lines 2369-2404, all with `xml:space="preserve"` + `state="translated"`); English/Spanish strings match the design table exactly, including the deliberate "Other services"/"Otros servicios" disambiguation against bare "Other"/"Otro".
- `src/Entity/ExpenseAllocation.php`, `src/Voter/ExpenseVoter.php`, `config/packages/gppro.yaml`: confirmed absent from `git diff main --stat` — no allocation, voter, or permission change, as scoped.
- Authored diff (excluding SDD planning docs under `openspec/`): 14 files, 287 insertions / 2 deletions — well under the 400-line review budget (matches design's own "Low" risk forecast).

## Issues

**CRITICAL**: None (resolved — see below).

**Resolved post-verify**:
1. ~~"Category displays as a translated label" had no covering test~~ — fixed by `ExpenseControllerTest::testViewAndListRenderTranslatedCategoryLabel` (commit `2e2a698`). Independently re-run: 66/66 focused tests pass (up from 65), `php-cs-fixer --dry-run` clean. The test persists a `CATEGORY_RENT` expense and a null-category expense in the same fixture set, requests both `/expense/{id}` and `/expense/`, and asserts the rendered HTML contains "Rent" for the categorized row and "Uncategorized" for the null one in every response — proving the Twig ternary's non-null branch actually executes and resolves to the correct translation key.

**WARNING**:
1. "Editing any expense requires a category" and "Legacy uncategorized expense requires a category on save" (ADDED requirement, scenarios 1-2) have no dedicated live-submit-and-reject test. Both scenarios are mechanically backed by the exact same `ExpenseForm::category` field (`required: true` + `NotBlank`) used for create, which `ExpenseFormTest::testCategoryFieldIsRequiredWithPlaceholderAndSixChoices` proves is correctly wired against a real `ValidatorExtension`; `ExpenseController::edit()` and `::create()` both build the identical `ExpenseForm::class` (`ExpenseController.php:207`), so there is no edit-specific branching that could diverge from the create-path behavior. This matches the project's own established testing depth for every other required field on this form (`amount`, `description`, `expenseDate` also have no dedicated live-submit-rejection test). Not a regression or a gap unique to this change, but still short of the spec's own scenario-level literal wording — a dedicated `ExpenseControllerTest` case (edit an existing categorized expense, submit with an empty `category`, assert non-redirect + unchanged persisted value) would close this cleanly and cheaply.

**SUGGESTION**: None.

## Verdict

**PASS WITH WARNINGS**

16/16 tasks independently confirmed complete. 8/10 spec scenarios have direct passing runtime evidence after the post-verify fix; the remaining 2 (edit/legacy-save rejection paths) are WARNING-tier — mechanically proven via a shared, already-tested `ExpenseForm::category` constraint (`required: true` + `NotBlank`), and `ExpenseController::edit()`/`::create()` build the identical form class with no edit-specific branching that could diverge. This matches this project's own pre-existing testing depth for every other required field on this form. Build/test/lint evidence is clean: 66/66 focused tests green (up from 65 after adding the translated-label regression test), `lint:twig`/`lint:xliff` clean, code style clean, PHPStan shows only the one confirmed pre-existing unrelated `QuotationControllerTest` error. All 6 design decisions (D1-D6) are faithfully implemented, `pending.html.twig` correctly left untouched, and the `index.html.twig` colspan fix (5→6) is confirmed. Ready for archive.
