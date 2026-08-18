# Archive Report: Expense Currency Normalization

**Change**: expense-currency-normalization  
**Archive Date**: 2026-08-18  
**Mode**: hybrid (Engram + OpenSpec)  
**Status**: CLOSED  
**Engram Archive Report ID**: #831  

## Change Summary

Resolved currency normalization for expense approval thresholds, allocation splits, and cross-charges. Non-CLP expenses (`currency != 'CLP'`) are now converted to CLP via `ClpConverter` at submit and allocation-recalculation time, with explicit blocking when no FX rate is available. Prevents silent wrong-number bugs from treating 500 USD as 500 CLP in approval levels and quotation billing.

## Artifact Registry (Observation IDs from Engram)

All artifacts successfully retrieved from Engram and verified:

| Artifact | Observation ID | Retrieval Date | Status |
|----------|----------------|----------------|--------|
| Proposal | #818 | 2026-08-18 00:00 | ✓ Retrieved |
| Specification | #819 | 2026-08-18 00:00 | ✓ Retrieved |
| Design | #820 | 2026-08-18 00:00 | ✓ Retrieved |
| Tasks | #821 | 2026-08-18 00:00 | ✓ Retrieved |
| Verify Report | #830 | 2026-08-18 00:56 | ✓ Retrieved |

## Final State Authority

### Completion Status

Per `sdd-verify` (verify-report #830, verdict PASS):
- **22/22 implementation tasks marked complete** in the persisted tasks artifact (`tasks.md`), all phases 1-7.
- **Verification verdict**: PASS — 0 CRITICAL, 0 WARNING, 2 SUGGESTION (both pre-acknowledged).
- **Build/test evidence**: 89/89 focused tests passing (31 unit + 58 integration), full-project PHPStan clean on all Expense files, exact diff-size match documented (1118/26 lines, 20 files).

### Native Review Authority

Per user-provided context (2026-08-18 launch facts):
- **PR #141 merged to main** via merge commit `e187f91` (2026-08-18).
- **CI checks**: 2 red checks (Frontend pnpm audit, Integration test 8.2) confirmed as pre-existing failures in main, not regressions introduced by this change (same failures in prior 3 runs of main before this merge).
- **Task 6.3 deferred**: Post-merge audit finder against real production/staging data — read-only, non-blocking. Finder `ExpenseRepository::findNonClpProcessedBeforeNormalization()` created and tested; execution postponed until deploy.

### Spec Synchronization

Delta spec merged into main spec (`openspec/specs/expense-allocation/spec.md`):

| Requirement | Action | Details |
|---|---|---|
| Allocate expense by percentage | MODIFIED | Added CLP conversion logic, new scenarios (500 USD rate example, no FX rate blocks, money filter). |
| Submit freezes required approval levels | MODIFIED | Added converted-CLP computation and no-rate block, new scenarios (3.000 USD threshold, submit blocked). |
| Cross-charge an approved allocation | MODIFIED | Added `amountClp` null guard and re-conversion prevention. |
| Identify historical expenses processed under the raw-amount assumption | ADDED | Read-only finder for audit trail, two scenarios. |

**Merge evidence**: All changes applied mechanically via Edit operations, verified by grep for key phrases in updated main spec.

## Archive Folder Contents

**Location**: `/Users/luismarinoc/Documents/Dev/tbema/gppro/openspec/changes/archive/2026-08-18-expense-currency-normalization/`

**Files**:
- `proposal.md` — Initial proposal with intent, scope, approach, and risks
- `design.md` — Technical decisions (D1-D6), architecture, file changes, testing strategy
- `tasks.md` — 22 tasks across 7 phases (all checked), test results (89/89 passing), post-merge action item (task 6.3)
- `specs/expense-allocation/spec.md` — Delta spec for this domain
- `verify-report.md` — Verification verdict and spec compliance matrix
- `archive-report.md` — This file

**Copy verification**: `diff -r` between pre-move snapshot and archived folder showed zero differences. Source folder removed from `openspec/changes/` after move.

## Implementation Summary

### Code Changes

Per design.md and tasks.md, the implementation created:
- `src/Expense/ExpenseClpAmountResolver.php` — `toClp(Expense): ?int`, `isConvertible(Expense): bool`
- `src/Expense/ExpenseAllocationAmountUpdater.php` — Converts, splits, writes `amountClp` or nulls it
- Modified `src/Controller/ExpenseController.php` — Wired updater into save flow, flash `expense.fx_rate_unavailable`
- Modified `src/Expense/ExpenseApprovalService.php` — Convert once at submit, block on `null`, freeze split + `requiredLevels`
- Modified `src/Expense/ExpenseCrossChargeService.php` — Null guard on `amountClp`, currency provenance in line description
- Modified `templates/expense/view.html.twig:39` — `{{ allocation.amountClp|money('CLP') }}`
- Added `translations/flashmessages.{en,es}.xlf` entries for `expense.fx_rate_unavailable`
- Added `ExpenseRepository::findNonClpProcessedBeforeNormalization()` — read-only historical finder

**Diff footprint**: 1118 additions, 26 deletions across 20 files (per verify-report). Forecast was 260–320; actual exceeded by ~100% due to integration test fixtures for Phases 3, 4, 6. Single PR delivery approved by Luis with `size:exception` due to atomic seam + 3 call-site changes + full test coverage.

### Spec Compliance

All MODIFIED requirements and ADDED requirement pass scenario-level verification per spec compliance matrix in verify-report #830. File:line evidence provided for each scenario:
- Non-CLP conversions with known rates ✓
- No-rate recalculation blocks ✓
- No-rate submit blocks ✓
- Cross-charge null guards ✓
- Historical expense audit finder (tested, execution deferred) ✓

### Test Coverage

- **Unit**: Resolver (CLP identity, USD/CLF rate handling, null on missing rate), Updater (share sums, null clears stale amounts), ApprovalService (block on null, freeze converted level/split), CrossChargeService (null guard, currency suffix), Repository finder (non-CLP predicate)
- **Integration**: Controller save flow (draft persists, warning flashes on no rate), approval flow end-to-end, cross-charge flow end-to-end
- **Linting**: Twig templates (4 files), XLIFF translations (602 files), PHPStan (1159 files, 1 pre-existing unrelated error)

**Result**: 89/89 tests passing (31 unit + 58 integration), independently re-verified by sdd-verify.

## Post-Archive Follow-Up

**Action item (task 6.3)**: After merge to main, run `ExpenseRepository::findNonClpProcessedBeforeNormalization()` against production/staging data to identify any pre-existing non-CLP expenses processed under the raw-amount assumption. If any are found, scope them as a separate follow-up correction work per spec's "Historical non-CLP expenses are found" scenario. This is read-only and non-blocking; defer until deploy.

## Closure Checklist

- [x] Task Completion Gate: 22/22 tasks checked (verified in persisted tasks artifact)
- [x] Native Review Receipt Gate: Not applicable (no review gate in this candidate per user facts; change merged to main under ordinary policy)
- [x] Spec Sync: Delta spec merged into main spec (4 requirements modified/added)
- [x] Archive Move: Change folder moved via `git mv`, verified by `diff -r` (empty output)
- [x] Main Spec Updated: Verified by grep for key phrases
- [x] All Artifacts Present: proposal, design, tasks, specs, verify-report, archive-report
- [x] Source Folder Removed: Confirmed absent from `openspec/changes/`
- [x] Archive Report Persisted: Engram (#831) and filesystem (this file)

## Cycle Status

**SDD CLOSED** — change fully planned, implemented, verified, and archived. Ready for the next change.
