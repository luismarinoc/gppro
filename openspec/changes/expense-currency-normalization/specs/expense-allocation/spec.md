# Delta for Expense Allocation

## MODIFIED Requirements

### Requirement: Allocate expense by percentage

Each `Expense` MUST split into `ExpenseAllocation` rows by manual percentage
per project. The sum MUST NOT exceed 100% at any time and MUST equal exactly
100% to submit for approval. Before splitting, a non-CLP `Expense` amount
MUST be converted to CLP via `ClpConverter` using the expense's currency and
`expenseDate`; the split MUST operate on the converted CLP amount, never the
raw currency amount. When no FX rate is available for that currency/date,
the recalculation MUST be blocked and MUST NOT write a raw foreign amount
into `amountClp`.
(Previously: split operated directly on `Expense::getAmount()`, implicitly
assuming CLP.)

#### Scenario: Sum over 100% is rejected

- GIVEN a draft with allocations totaling 90%
- WHEN the user adds one that would total 110%
- THEN the change is rejected

#### Scenario: Submit requires exactly 100%

- GIVEN a draft with allocations totaling 90%
- WHEN the user submits for approval
- THEN submission is rejected until the sum equals 100%

#### Scenario: Non-CLP expense splits on the converted CLP amount

- GIVEN a 500 USD expense with a known USD-to-CLP rate on its `expenseDate`
- WHEN allocation amounts are recalculated
- THEN each `ExpenseAllocation::amountClp` is a share of the converted CLP
  total, not of 500

#### Scenario: No FX rate blocks the recalculation

- GIVEN a non-CLP expense whose currency has no FX rate on or before its
  `expenseDate`
- WHEN allocation amounts are recalculated
- THEN the recalculation is blocked and no allocation is written with a
  mislabeled amount

#### Scenario: Allocation amount displays with the money filter

- GIVEN an `ExpenseAllocation` with a converted `amountClp`
- WHEN the expense view screen renders it
- THEN it displays formatted through the `money` filter, not a raw number

### Requirement: Submit freezes required approval levels

On submit, the system MUST compute `requiredLevels` from the expense
amount, converted to CLP via `ClpConverter` when the expense's currency is
not CLP, against current `ExpenseApprovalLevel` rows, and store it.
`ExpenseApprovalLevel::minAmount` remains a CLP-only threshold; only the
converted CLP amount is ever compared against it. Later edits to the level
configuration MUST NOT change an already-submitted expense's value. When no
FX rate is available for the expense's currency/date, submit MUST be
blocked.
(Previously: `requiredLevels` was computed from the raw `Expense::getAmount()`
regardless of currency.)

#### Scenario: Required levels computed at submit

- GIVEN levels 0/ROLE_TEAMLEAD and 1.000.000/ROLE_ADMIN
- WHEN a 2.000.000 CLP expense is submitted
- THEN `requiredLevels` is set to 2

#### Scenario: Later config change does not affect in-flight expense

- GIVEN a submitted expense with `requiredLevels = 2`
- WHEN a new level 3 is added to the configuration
- THEN the expense's `requiredLevels` stays 2

#### Scenario: Non-CLP expense resolves levels by its converted CLP amount

- GIVEN a 3.000 USD expense whose converted value exceeds the ROLE_ADMIN
  threshold, with a known rate on its `expenseDate`
- WHEN the expense is submitted
- THEN `requiredLevels` reflects the converted CLP amount, not the raw 3.000

#### Scenario: Submit is blocked when no rate is available

- GIVEN a non-CLP expense whose currency has no FX rate on or before its
  `expenseDate`
- WHEN the user attempts to submit
- THEN submission is rejected and `requiredLevels` is not computed or frozen

### Requirement: Cross-charge an approved allocation

An `ExpenseAllocation` of a fully `approved` `Expense` MAY be manually
cross-charged: it MUST add a line to a `draft` `Quotation` of the same
project, using the allocation's already-converted `amountClp` as the
`QuotationLine` unit price. The action MUST be blocked when the quotation
is not CLP, when the allocation was already charged, or when the
allocation's `amountClp` was never successfully converted to CLP (no
mislabeled foreign amount may be written as a CLP unit price).
(Previously: unit price was written from `amountClp` without verifying it
held a converted CLP value.)

#### Scenario: Allocation charged to a draft CLP quotation

- GIVEN an approved allocation with a converted CLP `amountClp` and a draft
  CLP quotation of the same project
- WHEN the user cross-charges it
- THEN a quotation line is added with that CLP amount as unit price and the
  allocation is marked charged

#### Scenario: Non-CLP quotation is blocked

- GIVEN an approved allocation and a draft quotation in another currency
- WHEN the user attempts to cross-charge
- THEN the action is rejected

#### Scenario: Double charge is blocked

- GIVEN an allocation already marked charged
- WHEN the user attempts to cross-charge it again
- THEN the action is rejected

## ADDED Requirements

### Requirement: Identify historical expenses processed under the raw-amount assumption

The system MUST provide a way to identify existing `Expense` records whose
currency is not CLP and that already went through approval-level
resolution, allocation split, or cross-charge before this change took
effect — i.e. `currency != CLP` combined with a non-null `requiredLevels`,
a non-null `ExpenseAllocation::amountClp`, or a charged allocation. This
identification MUST be usable to decide whether a retroactive correction is
needed. It MUST NOT silently auto-correct historical rows as a side effect
of the identification itself.

#### Scenario: No historical non-CLP expenses exist

- GIVEN no `Expense` with `currency != CLP` was ever submitted, allocated,
  or cross-charged before this change
- WHEN the identification check runs
- THEN it reports zero affected records and no retroactive correction is
  required

#### Scenario: Historical non-CLP expenses are found

- GIVEN one or more `Expense` records with `currency != CLP` were
  submitted, allocated, or cross-charged before this change
- WHEN the identification check runs
- THEN it reports the affected records so a retroactive correction can be
  scoped as separate follow-up work
