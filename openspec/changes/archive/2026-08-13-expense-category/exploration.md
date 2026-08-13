# Exploration: expense-category

## Current State

`App\Entity\Expense` (`src/Entity/Expense.php`) has no category field today — just `description` (free text), `amount`, `expenseDate`, `recurrence` (nullable, currently only `RECURRENCE_MONTH`), `status`, `requiredLevels`/`currentLevel`, `createdBy`, `sourceExpense`/`periodKey`, and a `OneToMany` to `ExpenseAllocation`. `ExpenseAllocation` (`src/Entity/ExpenseAllocation.php`) only carries `expense`, `project`, `percentage`, `amountClp`, `charged`, `quotationLine` — confirming allocation/distribution is orthogonal to any header-level classification.

The archived `expense-allocation` proposal (`openspec/changes/archive/2026-08-13-expense-allocation/proposal.md`) mentions "category" exactly once — in Out of Scope, about per-category **approval threshold** configuration, not about the `Expense` record carrying a category label. No deliberate exclusion of an `Expense.category` field exists in that proposal or its design doc.

`App\Entity\Tag` (`src/Entity/Tag.php`) is a free-form, user-extensible many-to-many tagging entity used by `Timesheet` — the wrong shape for a small, company-defined, closed taxonomy of 5 items (rent, equipment, electricity, phone, services).

## Two competing in-repo precedents for closed-set fields

1. **Hardcoded enum on the entity** — `Expense::STATUS_*`/`RECURRENCE_*` (this file) and `FxRate::INDICATOR_USD`/`INDICATOR_UF` (`src/Entity/FxRate.php`). Pattern: `public const X_Y = 'y'`, an array const, `#[Assert\Choice(choices: self::X_YS)]`, a `ChoiceType` in the form (`src/Form/ExpenseForm.php:33-40`), translated via `('expense.status.' ~ expense.status)|trans` in templates. `FxRate.indicator` uses this exact shape even though `FxRate` is a dynamic, API-synced entity — the closed "kind" stays hardcoded regardless of the owning entity's dynamism.

2. **Admin-editable child entity** — `ExpenseApprovalLevel` (entity + controller + form + 2 templates + repository). Chosen because approval levels are genuinely runtime-tunable (admin sets amount thresholds/roles), need referential integrity against `gppro_roles`, and need cross-row invariants. Required a dedicated new permission `manage_expense_approval_levels` (`config/packages/gppro.yaml:132`) distinct from the `EXPENSES` permission set.

## Affected Areas (enum approach)

- `src/Entity/Expense.php` — add `CATEGORY_*` consts + `CATEGORIES` array, `category` column, `Assert\Choice`, getter/setter.
- New migration (latest expense-table migration: `migrations/Version20260812140000.php`).
- `src/Form/ExpenseForm.php` — add `category` `ChoiceType`, same shape as `recurrence`.
- `templates/expense/edit.html.twig`, `view.html.twig`, `index.html.twig` — display/edit category (index.html.twig has no filter UI at all today, so purely additive).
- `translations/messages.en.xlf` / `messages.es.xlf` — new `expense.category` + `expense.category.<value>` entries (next id after `gpExpense40`).
- `src/Repository/ExpenseRepository.php` — optional category filter on `findForListing()`.
- `src/Expense/RecurringExpenseGenerator.php` — **must-fix**: `cloneForPeriod()` currently copies description/amount/expenseDate/recurrence/createdBy/allocations but has no category logic; without a one-line addition, monthly-generated copies would silently lose their category.
- `tests/Form/ExpenseFormTest.php` — needs a category case.
- No `ExpenseVoter` change — category reuses existing `EXPENSES`/`edit_expense`/`create_expense` permissions.

## Approaches

1. **Hardcoded enum on `Expense`** (mirrors `STATUS_*`/`RECURRENCES`/`FxRate::INDICATORS`)
   - Pros: matches two existing in-repo precedents; zero new entity/repository/controller/permission; same size class as the existing `recurrence` field; PO described the category set as company-defined, not end-user-extensible.
   - Cons: adding/renaming a category later needs a code change + migration + deploy.
   - Effort: Low.

2. **New admin-editable entity** (`ExpenseCategory`, mirrors `ExpenseApprovalLevel`)
   - Pros: admins could add/rename categories without a deploy; room for future per-category metadata.
   - Cons: full CRUD stack plus a new dedicated permission for what the PO framed as a fixed 5-item list; no PO signal that end users need self-service category management.
   - Effort: Medium-High (disproportionate to stated scope).

## Recommendation

Approach 1 (hardcoded enum) by default, given the PO's own framing (a short, company-defined, non-extensible list) and the direct in-repo precedent of `FxRate.indicator`. Switch to Approach 2 only if the PO explicitly confirms end users need to add/rename categories without a deploy.

## Open Questions for sdd-propose

1. Enum vs. admin entity — confirm PO does not need runtime-added categories (default: enum).
2. Exact category slugs to freeze into constants/translations (is "other services" one catch-all, or does description still carry free text alongside it?).
3. Mandatory vs. optional field — existing `gppro_expenses` rows have no category value; a NOT NULL column needs a default or backfill decision.
4. Recurrence/category default-suggestion (e.g., "Rent"/"Phone" pre-selecting monthly) — no in-code coupling exists today; recommend NOT auto-linking in v1 unless explicitly requested.
5. Whether category needs list/filter UI in this change (today `index.html.twig` has no filter UI for anything) or is deferred.
6. Independent of Q1's outcome: `RecurringExpenseGenerator::cloneForPeriod()` MUST copy `category` — a correctness requirement.

## Risks

- Silent regression in `RecurringExpenseGenerator` if category is added to the entity but the clone method isn't updated in the same change.
- Mandatory-column migration against existing rows needs an explicit backfill/default decision.
- Choosing the admin-entity approach without confirmed PO need repeats `ExpenseApprovalLevel`'s cost for a stated fixed list — over-engineering risk in the wrong direction.

## Ready for Proposal

Yes — with the 6 open questions above resolved explicitly at the top of `sdd-propose`, especially Q1 (enum vs. entity) and Q3 (mandatory vs. optional).
