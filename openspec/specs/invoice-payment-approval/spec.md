# Invoice Payment Approval Specification

## Purpose

Tiered, amount-based approval gating ONLY the `Invoice` PAID transition,
mirroring Expense's approval shape via a new, independent
`InvoicePaymentApprovalLevel` ladder. PENDING/CANCELED remain ungated.

## Requirements

### Requirement: PAID transition requires submission and cleared levels

The system MUST prevent an `Invoice` from transitioning to `STATUS_PAID`
unless it has been submitted for payment approval and all levels required
for its frozen amount are cleared.

#### Scenario: Unsubmitted invoice cannot be marked paid
- GIVEN an invoice never submitted for payment approval
- WHEN a user attempts to transition it to PAID
- THEN the transition is denied

#### Scenario: Submitted invoice with uncleared levels cannot be marked paid
- GIVEN an invoice submitted for approval with 1 of 2 required levels cleared
- WHEN a user attempts to transition it to PAID
- THEN the transition is denied

### Requirement: Submission freezes required levels at current amount

Submitting an invoice for payment approval MUST resolve and freeze the
required approval levels based on the invoice's total amount at that
moment, analogous to `Expense::submitForApproval()`.

#### Scenario: Submission fixes required levels
- GIVEN an invoice with a total that maps to 2 required levels
- WHEN it is submitted for payment approval
- THEN exactly 2 levels are fixed as required for this invoice

### Requirement: Post-submission amount changes do not reopen cleared levels

A change to the invoice's amount after submission MUST NOT re-evaluate or
reopen levels already cleared, and MUST NOT alter the frozen required-level
count.

#### Scenario: Amount increase after partial clearance does not reopen
- GIVEN a submitted invoice with level 1 of 2 cleared
- WHEN the invoice amount is increased to a value that would map to 3 levels
- THEN the required count stays 2 and the cleared level remains cleared

### Requirement: Only eligible approvers can clear a level

A pending level MUST be clearable only by a user holding that level's
required role or named as that level's approver, per
`InvoicePaymentApprovalLevel`. Ineligible users MUST be denied.

#### Scenario: Eligible approver clears a level
- GIVEN a level requiring ROLE_TEAMLEAD, pending on a submitted invoice
- WHEN a ROLE_TEAMLEAD user approves it
- THEN the level clears and is audited with approver and timestamp

#### Scenario: Ineligible user is denied
- GIVEN the same pending level
- WHEN a user without the required role or named-approver status attempts to
  clear it
- THEN the action is denied

### Requirement: All levels cleared unlocks PAID with audit trail

Clearing the final required level MUST make the invoice eligible for the
PAID transition, and the audit trail MUST record who cleared each level and
when.

#### Scenario: Final level clears and invoice can be paid
- GIVEN 1 of 2 required levels remaining
- WHEN the eligible approver clears it
- THEN the invoice becomes eligible for PAID and both clearances are audited

### Requirement: PENDING and CANCELED remain ungated (regression guard)

This change MUST NOT gate the PENDING or CANCELED status transitions with
approval requirements.

#### Scenario: PENDING and CANCELED transitions are unaffected
- GIVEN an invoice in any state prior to this change's behavior
- WHEN it transitions to PENDING, or separately to CANCELED
- THEN neither transition requires submission or cleared levels

### Requirement: Historical PAID invoices are grandfathered

Invoices already marked PAID before this change ships MUST NOT be required
to have retroactive approval records and MUST NOT be flagged as
"unapproved" in any new UI (including the dashboard).

#### Scenario: Pre-existing PAID invoice shows no unapproved flag
- GIVEN an invoice marked PAID before this change shipped, with no approval
  records
- WHEN it is viewed in any new approval-related UI
- THEN it is not flagged as unapproved and no retroactive submission is
  required
