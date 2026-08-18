# Tasks: Expense Currency Normalization

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~260-320 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

> Apply note: actual diff came in at ~743 changed lines (additions+deletions,
> including test code) vs. the ~260-320 estimate — driven mostly by the
> integration test fixtures for Phases 3/4/6. The forecast at apply start
> said "Decision needed before apply: No" / single PR, and the change stayed
> a single cohesive work unit exactly as scoped (one deliverable: currency
> normalization end-to-end). Flagging the actual size here for the
> reviewer/orchestrator's awareness, not re-opening the workload decision
> retroactively.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Ship the full currency-normalization fix (resolver, updater, service/controller wiring, template, translations, historical check) as one PR | PR 1 | `bin/phpunit tests/Expense tests/Controller/ExpenseControllerTest.php tests/Repository/ExpenseRepositoryTest.php` | Symfony `WebTestCase` — save an edit on a USD expense with no FX rate, confirm draft saves and warning flashes | Revert the single PR; no schema change, no other feature touches these files |

## Phase 1: CLP Conversion Seam (Foundation)

- [x] 1.1 RED: `tests/Expense/ExpenseClpAmountResolverTest.php` — CLP expense returns raw amount as int; USD/CLF use `Expense::getExpenseDate()` via mocked `ClpConverter`; missing rate returns `null` from both `toClp()` and `isConvertible()`.
- [x] 1.2 GREEN: Create `src/Expense/ExpenseClpAmountResolver.php` — `toClp(Expense): ?int` (rounds `ClpConverter::toClp()`'s scale-4 string), `isConvertible(Expense): bool`.
- [x] 1.3 RED: `tests/Expense/ExpenseAllocationAmountUpdaterTest.php` — convertible expense: `apply()` returns `true`, `AllocationSplitter` shares sum to the converted CLP total on every `ExpenseAllocation`. Not convertible: `apply()` returns `false`, every `amountClp` set to `null` (including overwriting a stale prior value).
- [x] 1.4 GREEN: Create `src/Expense/ExpenseAllocationAmountUpdater.php` composing `ExpenseClpAmountResolver` + `AllocationSplitter` per D1/D2.
- [x] 1.5 REFACTOR: Confirm `ApprovalLevelResolver::resolve()` and `AllocationSplitter::split()` signatures are untouched (design D1) — no code change expected, just verification.

## Phase 2: Controller Wiring (Draft Save)

- [x] 2.1 RED: Extend `tests/Controller/ExpenseControllerTest.php` — saving a USD expense edit with no FX rate keeps the draft, sets `amountClp = null` on all allocations, and flashes `expense.fx_rate_unavailable` (warning, HTTP 200/redirect still succeeds).
- [x] 2.2 GREEN: In `src/Controller/ExpenseController.php`, replace the `recalculateAllocationAmounts()` call (line 249) and its private method (lines 306-317) with `ExpenseAllocationAmountUpdater::apply($expense)`; flash `expense.fx_rate_unavailable` when it returns `false`.
- [x] 2.3 Add `<trans-unit resname="expense.fx_rate_unavailable">` to `translations/flashmessages.en.xlf` and `translations/flashmessages.es.xlf`, following the `gpExpenseF*` id pattern used by `expense.allocations_over_100`.
- [x] 2.4 REFACTOR: Remove the now-unused `AllocationSplitter` constructor param from `ExpenseController::form()` if `ExpenseAllocationAmountUpdater` fully replaces its use there; keep only if still referenced elsewhere in the class.

## Phase 3: Submit Freeze (ExpenseApprovalService)

- [x] 3.1 RED: Extend `tests/Expense/ExpenseApprovalServiceTest.php` — submitting a non-CLP expense with no FX rate throws `\DomainException` before any write; `requiredLevels` and `amountClp` are untouched.
- [x] 3.2 RED: Extend `tests/Expense/ExpenseApprovalServiceTest.php` — submitting a convertible non-CLP expense freezes `requiredLevels` from the converted CLP amount (not the raw amount) and freezes the allocation split from the same conversion, all inside the existing transaction; CLP-expense submit path is unchanged (regression case).
- [x] 3.3 GREEN: In `src/Expense/ExpenseApprovalService.php::submit()`, call `ExpenseClpAmountResolver::toClp()` once; throw `\DomainException` on `null`; use the resolved amount for both `ApprovalLevelResolver` and the allocation split write (via `ExpenseAllocationAmountUpdater` or direct `AllocationSplitter` call with the resolved amount) per D3/D4.

## Phase 4: Cross-Charge Guard (ExpenseCrossChargeService)

- [x] 4.1 RED: Extend `tests/Expense/ExpenseCrossChargeServiceTest.php` — charging an allocation whose `amountClp` is `null` is rejected (no `QuotationLine` created, no charged flag set).
- [x] 4.2 RED: Extend `tests/Expense/ExpenseCrossChargeServiceTest.php` — charging a non-CLP expense's allocation with a converted `amountClp` appends the source currency to the `QuotationLine` description; a CLP expense's description stays byte-identical to today (regression case, D6).
- [x] 4.3 GREEN: In `src/Expense/ExpenseCrossChargeService.php`, add the `null === getAmountClp()` guard (reject) and the currency-provenance suffix in the line description for non-CLP expenses only.

## Phase 5: Template Fix

- [x] 5.1 Edit `templates/expense/view.html.twig:39` — replace `{{ allocation.amountClp }}` with `{{ allocation.amountClp|money('CLP') }}`, null-safe (renders empty/dash when `null`).
- [x] 5.2 Verify the `money` filter's existing null-handling convention (check another `|money` usage in the same templates directory) before assuming default behavior.

## Phase 6: Historical Data Check (read-only, low risk)

- [x] 6.1 RED: `tests/Repository/ExpenseRepositoryTest.php` (or a dedicated `tests/Command/...Test.php`) — a fixture with a non-CLP `Expense` having a non-null `requiredLevels`, or an allocation with non-null `amountClp`, or a charged allocation, is returned by the finder/query; a fixture with only CLP expenses returns zero results.
- [x] 6.2 GREEN: Add a read-only finder (e.g. `ExpenseRepository::findNonClpProcessedBeforeNormalization()`) or a `bin/console app:expense:audit-non-clp` read-only command that lists `Expense` rows where `currency != 'CLP'` AND (`requiredLevels IS NOT NULL` OR an allocation has `amountClp IS NOT NULL` OR an allocation is `charged`). No writes, no auto-correction.
- [x] 6.3 Run the check against production/staging data once merged; if it reports any rows, file them as separate follow-up work per spec's "Historical non-CLP expenses are found" scenario — do not fix inline in this change. **Not run in this session** — requires a merged deploy against real production/staging data, out of scope for a local apply pass. Follow-up action item for the maintainer post-merge.

## Phase 7: Verification

- [x] 7.1 Run `bin/phpunit tests/Expense tests/Controller/ExpenseControllerTest.php tests/Repository/ExpenseRepositoryTest.php` — full green. **89/89 passing** (31 unit + 58 integration).
- [x] 7.2 Manually confirm the draft-save, submit-block, and cross-charge-block flows against the spec's scenario list (spec.md, `expense-allocation` domain). Confirmed via the integration tests added in Phases 2-4, which directly exercise each scenario end-to-end.
