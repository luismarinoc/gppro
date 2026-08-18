# Design: Expense Currency Normalization

## Technical Approach

Direction chosen by Luis: convert to CLP with `App\FxRate\ClpConverter`, and block the operation when no rate exists (`convert()` returns `null`) instead of guessing — the exact pair of precedents already in the repo (`MilestoneTotalCalculator` converts, `InvoiceableMilestoneFinder::isConvertible()` excludes). No new FX source, no new column, no multi-currency thresholds.

`ApprovalLevelResolver::resolve(int $amountClp)` and `AllocationSplitter::split(int $amountClp, ...)` keep their signatures: their CLP contract was always correct — the callers were feeding them a foreign amount. The fix belongs in a conversion seam between the callers and those pure services.

Two small single-purpose services in `src/Expense/` (repo style: `AllocationSplitter`, `AllocationPercentageValidator`, `ExpenseApprovalPolicy`):
- `ExpenseClpAmountResolver` — wraps `ClpConverter`, exposes `toClp(Expense): ?int` and `isConvertible(Expense): bool`. Date = `Expense::getExpenseDate()` (`null` → converter's latest-rate path). Rounds the scale-4 decimal string to the integer CLP that `amount_clp` stores.
- `ExpenseAllocationAmountUpdater` — composes the resolver + `AllocationSplitter`; `apply(Expense): bool` writes every `ExpenseAllocation::amountClp`, or sets them all to `null` and returns `false` when not convertible. Replaces the private `ExpenseController::recalculateAllocationAmounts()`.

## Architecture Decisions

| # | Decision | Choice | Alternatives rejected | Rationale |
|---|---|---|---|---|
| D1 | Where conversion is injected | New `ExpenseClpAmountResolver` + `ExpenseAllocationAmountUpdater`; `ClpConverter` never reaches the controller | (a) `ClpConverter` injected into `ExpenseController`; (b) change `AllocationSplitter::split()` to take `Expense` | (a) puts money policy in HTTP layer; (b) destroys a pure, unit-tested integer splitter reused by the submit freeze |
| D2 | Draft save, no rate | Save the draft, set every `amountClp = null`, flash `expense.fx_rate_unavailable` (warning, save still succeeds) | Reject the save | `amount_clp` is already nullable; losing typed data because Mindicador lagged is worse than a null preview. A stale value from an earlier save MUST be overwritten with `null` |
| D3 | Submit, no rate | `ExpenseApprovalService::submit()` throws `\DomainException` before the freeze; controller already flashes it | Freeze the strictest level "for safety" | A guessed `requiredLevels` is frozen forever and unauditable; blocking is self-healing (rate syncs → resubmit) |
| D4 | Freeze point | Submit converts once and freezes BOTH `requiredLevels` and the allocation split, inside the existing transaction. Cross-charge reads the frozen `amountClp` and never re-converts | Re-resolve the rate at charge time | Extends the existing spec rule "Submit freezes required approval levels" to the amount it is derived from; re-converting at charge would bill a different number than the one approved |
| D5 | Rate provenance | No `fx_rate_value` / `fx_rate_date` columns in this change | Persist an FX snapshot on `Expense` | The frozen `amount_clp` + `requiredLevels` are the audit record; schema stays revert-clean per the proposal's rollback plan. Follow-up if auditors need the exact rate |
| D6 | Cross-charge labelling | Guard `null === getAmountClp()`; append the original currency to the line description for non-CLP expenses only | Reject all non-CLP expenses at charge | D4 already guarantees the amount is real CLP; CLP expenses keep a byte-identical description (no regression) |

## Data Flow

    edit/save ──→ ExpenseAllocationAmountUpdater ──→ ExpenseClpAmountResolver ──→ ClpConverter
                          │ (preview)                          │
                          └─ null → all amountClp = null + flash warning
    submit  ──→ ExpenseApprovalService (tx)
                  ├─ resolver->toClp()  ── null ─→ DomainException (blocked)
                  ├─ updater->apply()          ─→ amountClp frozen
                  └─ ApprovalLevelResolver::requiredLevelsFor(clpAmount) ─→ requiredLevels frozen
    charge  ──→ ExpenseCrossChargeService ──→ reads frozen amountClp (no conversion)

## File Changes

| File | Action | Description |
|---|---|---|
| `src/Expense/ExpenseClpAmountResolver.php` | Create | `toClp(Expense): ?int`, `isConvertible(Expense): bool` over `ClpConverter` |
| `src/Expense/ExpenseAllocationAmountUpdater.php` | Create | Converts + splits + writes `amountClp`; `false` when not convertible |
| `src/Controller/ExpenseController.php` | Modify | `recalculateAllocationAmounts()` → `$updater->apply()`; warning flash on `false` |
| `src/Expense/ExpenseApprovalService.php` | Modify | Convert once in `submit()`; block on `null`; freeze split + `requiredLevels` |
| `src/Expense/ExpenseCrossChargeService.php` | Modify | Null-`amountClp` guard; currency provenance in the line description |
| `templates/expense/view.html.twig` | Modify | Line 39 → `allocation.amountClp\|money('CLP')`, null-safe |
| `translations/flashmessages.{en,es}.xlf` | Modify | `expense.fx_rate_unavailable` |
| `src/Expense/ApprovalLevelResolver.php`, `AllocationSplitter.php` | Unchanged | Contracts were already correct |

## Interfaces / Contracts

```php
final class ExpenseClpAmountResolver
{
    public function __construct(private readonly ClpConverter $converter) {}
    public function toClp(Expense $expense): ?int;      // null = no rate / unmapped currency
    public function isConvertible(Expense $expense): bool;
}

final class ExpenseAllocationAmountUpdater
{
    public function apply(Expense $expense): bool;      // false = amounts cleared to null
}
```

## Testing Strategy

| Layer | What to test | Approach |
|---|---|---|
| Unit | Resolver: CLP identity returns the raw amount; USD/CLF use `expenseDate`; `null` on missing rate | PHPUnit + mocked `ClpConverter` (mirrors `InvoiceableMilestoneFinderTest`) |
| Unit | Updater: shares sum to the converted total; not-convertible clears stale amounts to `null` | PHPUnit, real `AllocationSplitter` |
| Unit | `ExpenseApprovalService::submit()` blocks on `null`; freezes converted level + split; CLP path unchanged | Extend `ExpenseApprovalServiceTest` |
| Unit | Cross-charge rejects `null` `amountClp`; description carries the source currency | Extend `ExpenseCrossChargeServiceTest` |
| Integration | Edit/save with no rate saves the draft and flashes the warning | Symfony `WebTestCase` |

RED tests first (strict TDD is enabled for this project).

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary. All changes are in-process domain services and a Twig template.

## Migration / Rollout

No migration. `gppro_expenses.currency` was added on 2026-08-17 (`Version20260817140000`) as `NOT NULL DEFAULT 'CLP'`, so every pre-existing row is CLP and its behavior is bit-for-bit unchanged — no backfill and no historical correction script (proposal Q4 resolved).

## Open Questions

- [ ] None blocking. Optional follow-up: persist the FX rate/date on `Expense` if the approval audit must show which rate produced the frozen amount (D5).
