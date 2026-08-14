# Proposal: Approval Workflows Expansion

## Intent

Only Expense has an approval system today. Timesheet hours and Invoice
payment ship with zero approval gate: hours self-report with no team-lead
sign-off, and payment is a single-click status flip (`InvoiceController::
changeStatusAction` + `edit_invoice` permission, no audit trail). This
change adds approval to both, reusing what is genuinely reusable from
Expense's just-shipped tiered system, and gives the PO one aggregated
"pending my approval" view instead of three disconnected places to check.

## Locked Decisions

1. **Timesheet approval**: single-step. The project's team lead (existing
   `TeamMember::isTeamlead()` + `RolePermissionManager::checkTeamLeadAccess()`
   — NOT a global `ROLE_TEAMLEAD` role, contrary to earlier framing; no new
   admin screen needed to define eligibility) approves hours logged by their
   team. New mechanism on `Timesheet`; does not touch `WorkingTime` (separate,
   unrelated monthly HR-compliance domain).
2. **Invoice payment approval**: tiered by amount, mirroring Expense's shape.
   New `InvoicePaymentApprovalLevel` entity/table/admin CRUD, independent of
   `ExpenseApprovalLevel`. **Correction to prior assumption**: `ApprovalLevelResolver`
   (src/Expense/ApprovalLevelResolver.php) is entity-agnostic in its `resolve(int)`
   algorithm but its constructor is hard-typed to `ExpenseApprovalLevelRepository`
   — it cannot be reused as-is against a different repository. Design phase must
   either extract an interface both repositories implement, or duplicate the
   ~15-line algorithm. Flagged, not blocking.
3. **Unified management**: (a) admin CRUD pattern for `InvoicePaymentApprovalLevel`
   mirroring `ExpenseApprovalLevelController`; Timesheet needs no equivalent
   screen (decision 1). (b) one read-only "pending my approval" dashboard
   aggregating Expense + Invoice + Timesheet via existing/new
   `findPendingForUser()`-style repository queries, UI-layer merge only — no
   shared table.

## Scope

### In Scope
- `Timesheet` approval: entity fields, team-lead approve/reject action, voter rule.
- `InvoicePaymentApprovalLevel` entity + migration + admin CRUD + policy/service
  gating the PAID transition only (not PENDING/CANCELED).
- Unified pending-approvals dashboard (read-only aggregation, 3 domains).

### Out of Scope
- Any change to shipped `Expense`/`ExpenseApprovalLevel`/`ExpenseApprovalPolicy`/
  `ExpenseApprovalService`/`ExpenseApproval` code (read-only reuse of the resolver
  algorithm only).
- `WorkingTime` module — untouched, unrelated domain.
- Polymorphic/generalized `ApprovalLevel`/`ApprovalWorkflow` abstraction across
  domains (PO's explicit instinct + prior explore Approach 3 recommendation).
- Redesigning Expense's existing approval UI.

## Capabilities

### New Capabilities
- `timesheet-approval`: team-lead single-step approve/reject on Timesheet entries.
- `invoice-payment-approval`: tiered amount-based approval gating invoice PAID transition.
- `approvals-dashboard`: read-only cross-domain pending-approvals aggregation view.

### Modified Capabilities
None — no existing `openspec/specs/` covers Timesheet or Invoice; both are additive.

## Approach

Approach 3 from prior exploration: keep state machines, policies, and audit
entities domain-owned and separate; share only proven-generic plumbing
(threshold-ladder algorithm pattern, admin-CRUD-with-validation pattern,
aggregation-query pattern for the dashboard). Timesheet gets a flat
`approvedBy`/`approvedAt`-style pair plus voter gate (WorkingTime-shaped, but
its own code). Invoice gets an Expense-shaped tiered ladder (own table/policy/
service/audit entity).

## Affected Areas

| Area | Impact | Description |
|------|--------|--------------|
| `src/Entity/Timesheet.php` | Modified | approval fields + state |
| `src/Voter/TimesheetVoter.php` | Modified | new `approve` attribute, team-lead check |
| `src/Entity/Invoice.php`, `InvoiceService.php`, `InvoiceController.php` | Modified | gate PAID transition |
| `src/Entity/InvoicePaymentApprovalLevel.php` (new) | New | mirrors `ExpenseApprovalLevel` shape |
| `src/Invoice/InvoicePaymentApprovalPolicy.php`/`Service.php` (new) | New | mirrors Expense policy/service |
| Admin CRUD controller/form/templates (new) | New | mirrors `ExpenseApprovalLevelController` |
| Dashboard controller/template (new) | New | 3-domain read-only aggregation |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Resolver constructor coupling forces extra design work | Med | interface extraction, scoped additive |
| Three work areas may exceed 400-line PR budget | High | chained-PR strategy, decided at sdd-tasks |
| Concurrent branches touching Timesheet/Invoice voters | Low | rebase-check at apply time |

## Rollback Plan
Each domain's approval gate is additive (new columns/tables, new voter
attributes) — disable via feature migration rollback or permission removal;
existing status-flip/timesheet-edit paths remain functionally intact if reverted.

## Dependencies
None blocking.

## Success Criteria
- [ ] Team lead can approve/reject team timesheet entries; non-leads cannot.
- [ ] Invoice PAID transition is gated by amount-tiered approval; audit trail recorded.
- [ ] One dashboard view lists pending items across Expense, Invoice, Timesheet for current user.

## Locked Decisions — Round 2 (PO-confirmed, resolves prior Open Questions)

4. **Timesheet self-approval**: ALLOWED. A team lead may approve their own
   logged hours on a project they lead — this is a single-step check, not a
   four-eyes compliance ladder like Expense. No creator-exclusion rule needed
   on `TimesheetVoter`'s new `approve` attribute.
5. **Invoice amount changes after partial approval**: FROZEN at submit time,
   mirroring Expense's `submitForApproval(int $requiredLevels)` exactly. A
   later amount change on the invoice does NOT reopen or re-evaluate already-
   cleared levels. `InvoicePaymentApprovalPolicy`/`Service` must implement an
   explicit "submit for payment approval" step (not a stateless recompute-at-
   click-time model) — this changes the affected-areas shape: Invoice needs a
   `submittedForApprovalAt`-style state transition before the tiered levels are
   fixed, analogous to `Expense::submitForApproval()`.
6. **Approved Timesheet entries become READ-ONLY**, mirroring Expense's
   `isEditable()` gate (just shipped in `row-click-edit-consistency`) and its
   effect on row-click-to-edit. Once approved, the owner can no longer edit
   that entry; only revoking the approval (if the design supports a re-open
   path) restores edit access.
7. **Dashboard is navigation-only** in this first slice — no inline
   approve/reject controls. Each row links into its domain's own
   approve/reject screen. Confirms Affected Areas: dashboard needs no new
   write endpoints, read-only aggregation controller only.
8. **Historical PAID invoices**: grandfathered — no retroactive approval
   record required for invoices already marked PAID before this change ships.
   The dashboard/audit view should not flag them as "unapproved."

## Open Questions (remaining, non-blocking — design-phase judgment calls)
1. Permission attribute names: `approve_timesheet`? `manage_invoice_payment_approval_levels`?
   (exact naming left to sdd-design, following this codebase's existing
   attribute-naming convention)
2. Exact route naming for the new admin CRUD and dashboard (left to sdd-design).
