# Verify Report: Expense Currency Normalization

**Change**: expense-currency-normalization
**Mode**: hybrid (Engram + OpenSpec)
**Verdict**: PASS

## Completeness

| Item | Status |
|---|---|
| Tasks checked | 22/22 (all phases 1-7) |
| Proposal/spec/design/tasks present | Yes, all four artifacts retrieved from Engram (#819 spec, #820 design, #821 tasks, #828 apply-progress) |

## Build/Test Evidence (independently re-executed by verify, not trusted from apply report)

| Command | Result |
|---|---|
| `git log --oneline c80dcce..HEAD` | 7 commits, matches apply-progress record |
| `git diff c80dcce..HEAD --stat` | **20 files changed, 1118 insertions(+), 26 deletions(-)** — exact match to reported size |
| `vendor/bin/phpunit tests/Expense tests/Controller/ExpenseControllerTest.php tests/Repository/ExpenseRepositoryTest.php` | **OK (89 tests, 306 assertions)** — re-run independently, confirmed |
| `bin/console lint:xliff translations` | OK — 602 files valid |
| `bin/console lint:twig templates/expense` | OK — 4 files valid |
| `vendor/bin/phpstan analyse -c phpstan.neon` (full project, 1159 files) | 1 pre-existing error in `Controller/UserController.php` (untouched, `argument.type`) — zero errors in any Expense file. Matches apply-progress baseline exactly. |

Full unit suite (3449 tests) and integration suite (1283 tests) were not re-run in full during verify (would duplicate apply's already-evidenced, diff-confirmed baseline comparison); the change-scoped suite (89/89) plus full-project PHPStan were re-executed directly as the independent runtime check.

## Spec Compliance Matrix

| Requirement | Scenario | Evidence | Status |
|---|---|---|---|
| Allocate expense by percentage | Sum over 100% rejected | Unchanged `AllocationPercentageValidator` path, pre-existing test coverage retained | PASS |
| Allocate expense by percentage | Submit requires exactly 100% | Unchanged, pre-existing | PASS |
| Allocate expense by percentage | Non-CLP splits on converted CLP amount | `ExpenseAllocationAmountUpdater::apply()`/`applyAmount()` convert via `ExpenseClpAmountResolver::toClp()` before calling `AllocationSplitter::split()` (src/Expense/ExpenseAllocationAmountUpdater.php:38-72); covered by `ExpenseAllocationAmountUpdaterTest` (2/2 green) | PASS |
| Allocate expense by percentage | No FX rate blocks recalculation | `apply()` returns `false` and sets every `amountClp = null` when `toClp()` is `null` (ExpenseAllocationAmountUpdater.php:42-48); `ExpenseController::form()` flashes `expense.fx_rate_unavailable` warning and still saves the draft (ExpenseController.php:249-251); covered by extended `ExpenseControllerTest` | PASS |
| Allocate expense by percentage | Allocation amount displays with money filter | `templates/expense/view.html.twig:39` — `{{ allocation.amountClp|money('CLP') }}`, consistent with the null-safe convention used by 3 other `|money` usages in the same templates directory | PASS |
| Submit freezes required approval levels | Required levels computed at submit | `ExpenseApprovalService::submit()` computes `requiredLevels` from the resolved CLP amount via `ApprovalLevelResolver::requiredLevelsFor($clpAmount)` (ExpenseApprovalService.php:48-57), inside the existing `beginTransaction()`/`commit()` block | PASS |
| Submit freezes required approval levels | Later config change does not affect in-flight expense | Unchanged freeze-on-write behavior (pre-existing entity design), regression-covered by `ExpenseApprovalServiceTest` | PASS |
| Submit freezes required approval levels | Non-CLP expense resolves levels by converted amount | Same `submit()` code path — single conversion feeds both the split and `ApprovalLevelResolver`, covered by extended `ExpenseApprovalServiceTest` (task 3.2) | PASS |
| Submit freezes required approval levels | Submit blocked when no rate available | `submit()` throws `\DomainException` before any write when `toClp()` returns `null` (ExpenseApprovalService.php:48-52), wrapped by the transaction's `catch`/`rollback()`; covered by extended `ExpenseApprovalServiceTest` (task 3.1) | PASS |
| Cross-charge an approved allocation | Allocation charged to draft CLP quotation | Unchanged happy path, regression-covered | PASS |
| Cross-charge an approved allocation | Non-CLP quotation blocked | Unchanged guard (`Quotation::CURRENCY_CLP !== ...`), pre-existing | PASS |
| Cross-charge an approved allocation | Double charge blocked | Unchanged `isCharged()` guard, pre-existing | PASS |
| Cross-charge an approved allocation | Reject when amountClp never converted | New guard `null === $amountClp` throws `\DomainException` before any write (ExpenseCrossChargeService.php:56-60), no re-conversion attempted; covered by extended `ExpenseCrossChargeServiceTest` (task 4.1) | PASS |
| Identify historical expenses (ADDED) | No historical non-CLP expenses exist | `ExpenseRepository::findNonClpProcessedBeforeNormalization()` returns empty array for CLP-only fixtures; covered by `ExpenseRepositoryTest` | PASS |
| Identify historical expenses (ADDED) | Historical non-CLP expenses found | Query matches `currency != CLP` AND (`requiredLevels IS NOT NULL` OR `amountClp IS NOT NULL` OR `charged = true`) via `leftJoin`+`orX` (ExpenseRepository.php:171-187); read-only `getResult()`, no `flush()`/`persist()` anywhere in the method — genuinely non-mutating; covered by `ExpenseRepositoryTest` | PASS |

## Design Coherence

| Decision | Check | Result |
|---|---|---|
| D1 — conversion seam location | `ClpConverter` is injected only into `ExpenseClpAmountResolver`; controller and services depend on the resolver/updater, never `ClpConverter` directly | Confirmed via `grep` — `ClpConverter` referenced only in `ExpenseClpAmountResolver.php` |
| D1 — pure services untouched | `ApprovalLevelResolver.php`, `AllocationSplitter.php` | `git diff c80dcce..HEAD` shows **zero changes** to either file; `AllocationSplitter::split(int $amountClp, array $basisPoints)` signature unchanged | Confirmed |
| D2 — draft save, no rate | Save succeeds, `amountClp` cleared to `null` on every allocation (including stale prior values), warning flash, no rejection | `ExpenseAllocationAmountUpdater::apply()` unconditionally nulls all allocations before returning `false`; `ExpenseController::form()` saves regardless (`$repository->saveExpense($expense)` runs unconditionally, not inside the `if`) | Confirmed |
| D3 — submit, no rate | `\DomainException` thrown before any write | `ExpenseApprovalService::submit()` throws before `applyAmount()`/`persist()`/`flush()` are reached, inside the try/catch that rolls back the transaction | Confirmed |
| D4 — freeze point (convert once) | Submit resolves CLP once, feeds both the split and `requiredLevels` | Single `$this->clpAmountResolver->toClp($expense)` call at line 48, its result passed to both `applyAmount()` (line 54) and `requiredLevelsFor()` (line 56) | Confirmed |
| D5 — no new FX provenance columns | No schema change | `git diff --stat` shows no migration file; entities untouched | Confirmed |
| D6 — cross-charge labelling | Null guard + currency suffix for non-CLP only, CLP byte-identical | Guard at ExpenseCrossChargeService.php:58-60; suffix conditional on `Expense::CURRENCY_CLP !== $expense->getCurrency()` at line 66-68 — CLP path builds the same `sprintf('%s (%s)', ...)` string as before with no suffix appended | Confirmed |

## Issues

**CRITICAL**: None.

**WARNING**: None.

**SUGGESTION**:
1. Task 6.3 (running the historical-expense audit finder against real production/staging data) is explicitly not executed — correctly deferred as post-merge maintainer follow-up per apply-progress; not a blocker for this PR since the finder itself is read-only and tested.
2. Actual diff (1118/26, 20 files) is well above the tasks.md original forecast (~260-320 lines) and the 400-line review-budget guideline. Luis already explicitly approved shipping this as a single PR with `size:exception` because the conversion seam, its 3 call sites, and their tests form one atomic, non-splittable unit. Documented transparently in tasks.md and apply-progress — flagging here for the record, not re-opening the decision.

## Verdict

**PASS** — 0 CRITICAL, 0 WARNING, 2 SUGGESTION (both pre-acknowledged, non-blocking). Implementation matches spec scenarios, design decisions D1-D6, and all 22 tasks. Independently re-verified: 89/89 focused tests, exact diff-size match (1118/26/20 files), full-project PHPStan clean except the one pre-existing unrelated `UserController.php` error. Ready for archive.
