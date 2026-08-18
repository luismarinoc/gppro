# Proposal: Expense Currency Normalization

## Intent

`Expense::currency` (CLP/USD/CLF) is captured and displayed, but every downstream money rule still treats `Expense::getAmount()` as CLP. A 500 USD expense is compared against CLP approval thresholds, split into `ExpenseAllocation::amountClp`, and cross-charged into a CLP quotation as if it were 500 pesos. Result: wrong approval levels on non-CLP expenses and understated client billing. This proposal decides the business rule; it writes no conversion logic yet.

## Scope

### In Scope
- Approval-level resolution for non-CLP expenses (`ApprovalLevelResolver`, `ExpenseApprovalService::submit`).
- Allocation split semantics and the meaning of `ExpenseAllocation::amountClp` (`ExpenseController::recalculateAllocationAmounts`, `AllocationSplitter`).
- Cross-charge of a non-CLP expense into a CLP quotation (`ExpenseCrossChargeService`).
- Minor: `templates/expense/view.html.twig:39` renders `allocation.amountClp` without the `money` filter.

### Out of Scope
- New FX rate source. `App\FxRate\ClpConverter` + `FxRate` (Mindicador sync) already exist and are the precedent used by Milestone/Quotation.
- Multi-currency quotations, invoices, or reporting.
- Changing `Expense::currency` capture or display (done in `1ad11c0`).

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `expense-allocation`: amount semantics stop being implicitly CLP for approval thresholds, allocation shares, and cross-charge.

## Approach

Reuse `ClpConverter` (currency + date -> CLP, `null` when no rate exists) instead of inventing conversion. Three candidate directions, one per open question below:

| Direction | Effect | Precedent |
|---|---|---|
| Convert to CLP at submit/charge | Thresholds and cross-charge stay CLP-only | `MilestoneTotalCalculator` |
| Block non-CLP in these flows | No silent wrong numbers, unblocks later | `InvoiceableMilestoneFinder` excludes non-convertible |
| Operate natively per currency | Thresholds and quotations become multi-currency | none in repo |

Design phase picks one after Luis answers. **Resolved (see design.md): convert at submit, block when no rate.**

## Proposal question round

1. **Rate timing**: should the CLP value freeze at submit (audit-stable, like `requiredLevels`) or re-resolve at each read?
2. **Behavior**: convert, block, or go native for each of the three flows — or convert for approval and block cross-charge?
3. **No rate available**: `ClpConverter` returns `null` when Mindicador has no rate on/before the date. Block the submit, or fall back to the latest rate?
4. **Historical data**: do non-CLP expenses already exist that were approved or cross-charged with raw amounts? Is a backfill script needed, or is correction manual?
5. **Threshold meaning**: is `ExpenseApprovalLevel::minAmount` a CLP-only policy, or should it become per-currency?

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `src/Expense/ApprovalLevelResolver.php` | Modified | `resolve(int $amountClp)` receives unconverted amounts |
| `src/Expense/ExpenseApprovalService.php:42` | Modified | Passes raw `getAmount()` |
| `src/Controller/ExpenseController.php:313` | Modified | Feeds raw amount to `AllocationSplitter::split()` |
| `src/Expense/ExpenseCrossChargeService.php:55` | Modified | Writes `amountClp` as CLP unit price unchecked |
| `templates/expense/view.html.twig:39` | Modified | Missing `money` filter |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Wrong direction chosen without Luis | High | Blocked on the 5 questions; design must not pick alone |
| Existing approvals silently change level | Med | Freeze at submit; never recompute in-flight expenses |
| Rate unavailable at submit time | Med | Explicit null-rate rule (question 3) |
| Historical allocations already wrong | Unknown | Question 4 decides backfill |

## Rollback Plan

Change is additive at the call sites. Revert the commits and CLP-assumed behavior returns; no schema change is required unless a converted-amount column is chosen in design, in which case the migration must be `DOWN`-reversible and non-destructive to `amountClp`.

## Dependencies

- `App\FxRate\ClpConverter` and populated `gppro_fx_rates` (Mindicador sync) for any convert-based direction.

## Success Criteria

- [x] A non-CLP expense resolves approval levels by a documented, intentional rule.
- [x] Cross-charge never writes a non-CLP amount into a CLP quotation line unlabeled.
- [x] `ExpenseAllocation::amountClp` holds CLP or the flow is blocked — never a mislabeled foreign amount.
- [x] Existing CLP-only expenses behave exactly as before (no regression) — verified by regression tests in Phases 2-4.
- [ ] Historical non-CLP data is either proven absent or covered by a correction plan — read-only finder shipped (Phase 6); the actual production/staging run is a post-merge follow-up (task 6.3).

## Verified code facts (grounding)

- `AllocationSplitter::split(int $amountClp, array $basisPoints)` — `src/Expense/AllocationSplitter.php:27`, integer CLP contract documented in the class docblock.
- `ExpenseController::recalculateAllocationAmounts()` line 313 passes `$expense->getAmount()` unconverted, then `setAmountClp($shares[$index])` at line 315.
- `ExpenseCrossChargeService::charge()` line 51 validates only the DESTINATION quotation is `Quotation::CURRENCY_CLP`; line 55 reads `$allocation->getAmountClp()` and line 60 writes it as `QuotationLine::setUnitPrice()`.
- `ExpenseApprovalService::submit()` line 42 calls `requiredLevelsFor($expense->getAmount() ?? 0)`; `ApprovalLevelResolver::resolve(int $amountClp)` line 29/33 filters `minAmount <= $amountClp`.
- `Expense::CURRENCY_UF = 'CLF'` (`src/Entity/Expense.php:67`) matches `Quotation::CURRENCY_UF = 'CLF'` (line 48) AND `ClpConverter::INDICATORS['CLF']` — no constant mismatch, conversion is wired-compatible today.
- Existing FX stack: `src/FxRate/ClpConverter.php`, `ClpConversion`, `FxRateSynchronizer`, `MindicadorClient`, `Entity/FxRate`, `Repository/FxRateRepository::findLatestOnOrBefore()`.
- Consumers precedent: `MilestoneTotalCalculator`, `MilestoneInvoiceItemRepository`, `QuotationController::view/fxRate`, `QuotationPdfRenderer`, `InvoiceableMilestoneFinder::isConvertible()` (excludes non-convertible instead of guessing).
