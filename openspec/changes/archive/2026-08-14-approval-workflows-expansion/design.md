# Design: Approval Workflows Expansion

## Technical Approach

Approach 3 (per proposal): three domain-owned approval mechanisms, no shared
abstraction. Invoice mirrors Expense's tiered state machine byte-for-byte
(own fields, own entity, own policy/service). Timesheet gets a flat
single-step approval. Dashboard is a read-only, permission-scoped, in-PHP
merge of three independent repository queries.

## Architecture Decisions

### D1: Resolver reuse — duplicate, do not extract an interface

| Option | Tradeoff |
|---|---|
| Extract `ApprovalLevelRepositoryInterface` implemented by both repos | Forces `ExpenseApprovalLevelRepository` (shipped, out-of-scope-to-touch) to implement a new interface — violates "no changes to Expense code" |
| Duplicate the ~15-line algorithm into `InvoicePaymentApprovalLevelResolver` | Small, self-contained, zero risk to Expense |

**Choice**: duplicate. `App\Invoice\InvoicePaymentApprovalLevelResolver` is a
near-identical class constructed with `InvoicePaymentApprovalLevelRepository`
instead of `ExpenseApprovalLevelRepository`; same `resolve()`/`requiredLevelsFor()`
shape. **Rationale**: touching `ExpenseApprovalLevelRepository` to satisfy an
interface is explicitly out of scope; the codebase already tolerates small
per-domain duplication over premature abstraction (Approach 3 is the proposal's
own explicit precedent). 15 lines duplicated is cheaper than a cross-domain
interface that only ever has two implementers.

### D2: Team-lead check — `User::isTeamleadOf(Team $team)`, not `RolePermissionManager::checkTeamLeadAccess()`

**Correction to the proposal**: `checkTeamLeadAccess(Collection|array $teams, User $user): bool`
(`src/Security/RolePermissionManager.php:143`) is **private**. It cannot be
called from a voter or a new policy class. The public primitive that already
expresses "is this user team lead of team X" is `User::isTeamleadOf(Team $team): bool`
(`src/Entity/User.php:809`).

**Choice**: `TimesheetVoter` resolves team-lead status directly:
`foreach ($timesheet->getProject()?->getTeams() ?? [] as $team) { if ($user->isTeamleadOf($team)) return true; }`.
**Alternatives considered**: (a) make `checkTeamLeadAccess` public — rejected,
touches shared security code for a single caller and the method's signature
(`Collection|array $teams`) is already awkward to reuse; (b) reuse
`checkTeamAccessTimesheet()` — rejected, it short-circuits `true` for the
timesheet's own owner (line 217-219) *before* checking team-lead status at
all, which would let any team member (not just leads) pass an "approve"
check on their own entries — breaks decision 4's "team lead, including
self-approval" requirement, since ordinary members must NOT self-approve.
**Rationale**: smallest correct primitive, no changes to shared security code.

### D3: Timesheet reject — no distinct state, "approve" is the only transition

**Choice**: no `TIMESHEET_STATUS_REJECTED`. "Reject" is not a persisted
state — a team lead who disapproves simply does not approve; the entry
stays in its normal (unapproved) editable state so the owner can revise and
resubmit hours implicitly (there is no submit step for Timesheet — it's
always approvable while unapproved, mirroring how `WorkingTime`-adjacent
review works today). **Alternatives considered**: mirror Expense's reject
with a `rejectedAt`/`rejectedBy` pair — rejected because Timesheet approval
is single-step, no ladder, no audit requirement was locked (proposal scope
list has no Timesheet audit trail item, unlike Invoice's explicit "audit
trail recorded" success criterion). **Rationale**: adding reject state adds
a third entry-visibility state (approved/rejected/pending) with no locked
requirement driving it; YAGNI per proposal's explicit "own code, don't
over-build" framing (decision 1).

### D4: Invoice payment-approval state lives on `Invoice` itself

**Choice**: new nullable fields directly on `Invoice` (mirrors Expense's
self-contained state machine), not a separate tracking entity. **Alternatives
considered**: separate `InvoicePaymentApproval` state-only entity — rejected,
Expense's own precedent keeps `status`/`requiredLevels`/`currentLevel` on the
entity itself; a split tracking entity would be inconsistent with the
established pattern for no benefit. **Rationale**: consistency with Expense;
`Invoice::status` (business lifecycle: new/pending/paid/canceled) and the new
payment-approval fields are orthogonal concerns, so they get a distinct
`paymentApproval*` prefix to avoid any collision with existing `status`/`STATUS_*`.

### D5: `minAmount` type is `float`, not `int`

**Choice**: `InvoicePaymentApprovalLevel::minAmount` is `float` (Doctrine
`Types::FLOAT`), matching `Invoice::getTotal(): float`. **Alternatives
considered**: mirror Expense's `int` (whole CLP) — rejected, `Invoice` has a
real multi-currency `currency` field and a float `total`; casting to int
would silently truncate. **Rationale**: match the actual compared field's type.

### D6: PAID gate — two insertion points, not one

Reading `InvoiceController` in full revealed **two independent paths that
persist `STATUS_PAID`**, not one:

1. `changeStatusAction()` (line ~245): when `$status === Invoice::STATUS_PAID`,
   it mutates the entity in memory (`setPaymentDate()`, `setIsPaid()`) and
   renders `invoice_edit.html.twig` — it does **not** call
   `InvoiceService::changeInvoiceStatus()` for PAID at all (that switch-case
   is effectively unreached for PAID today; the only caller passes through
   this branch first). Persistence for this path happens later, when the
   rendered edit form is submitted.
2. `editAction()` → `InvoiceEditForm` includes a `status` `ChoiceType` — a
   user can select "paid" directly from the edit form's dropdown and submit;
   this calls `InvoiceService::saveInvoice()` directly, with no special-case
   branch at all.

**Choice**: gate both, at the point where the transition is first detected,
before persistence:
- In `changeStatusAction()`: immediately inside `if ($status === Invoice::STATUS_PAID)`,
  before any setter call, check `!$invoice->isPaid() && !$invoice->isPaymentApproved()`
  → flash error, redirect, skip the mutation entirely.
- In `editAction()`: capture `$wasPaid = $invoice->isPaid();` **before**
  `$form->handleRequest($request)`. After a valid submit, if
  `$invoice->isPaid() && !$wasPaid && !$invoice->isPaymentApproved()` →
  flash error, re-render the form (skip `saveInvoice()`).

Both checks are transition-guards (`was not paid → now paid`), so an
already-PAID invoice being re-saved (comment edits, grandfathered historical
PAID invoices per decision 8) never re-triggers the gate — `paymentApprovalStatus`
stays `null` for them and is never read. PENDING/NEW/CANCELED transitions
are untouched in both call sites. **Rationale**: gating only `changeInvoiceStatus()`'s
switch-case would miss the actual production PAID path entirely (it's dead
code for PAID); the edit-form dropdown is a real second bypass that must be
closed for the gate to be meaningful.

### D7: Timesheet read-only enforcement lives in the voter, entity holds the flag

**Choice**: `Timesheet::isApproved(): bool { return null !== $this->approvedAt; }`
(entity-level readable primitive, mirrors `Expense::isApproved()`).
`TimesheetVoter::canEdit()`/`canDelete()` add a new private check
`isAllowedApproved()` (same shape as the existing `isAllowedExported()`/
`isAllowedInLockdown()` pair) that returns `false` when `isApproved()` is
true. **No template changes needed**: `templates/timesheet/index.html.twig:88`
already gates the row-click-to-edit affordance with
`is_granted('edit', entry)`, and edit/delete actions already route through
`TimesheetVoter::EDIT`/`DELETE`. Note: the "row-click-edit-consistency"
Expense pattern referenced in the task brief is **not present** in the
current working tree (`templates/expense/index.html.twig` only has a static
"view" link, no `open-edit`/`data-href` row pattern) — the actual precedent
for read-only-gated row click is `templates/timesheet/index.html.twig`
itself, already wired to the voter. **Rationale**: reuses an existing,
already-correct enforcement seam; zero template risk.

## Data Flow — Invoice payment approval

    InvoiceController::submitPaymentApprovalAction (new)
        → InvoicePaymentApprovalService::submit(Invoice)
            → InvoicePaymentApprovalLevelResolver::requiredLevelsFor(invoice.total)
            → Invoice::submitForPaymentApproval(int $requiredLevels)  [freeze, own state machine]
    InvoiceController::approvePaymentAction / rejectPaymentAction (new)
        → InvoicePaymentApprovalService::approve|reject(Invoice, User)
            → InvoicePaymentApprovalPolicy::canApprove|canReject   [four-eyes, mirrors ExpenseApprovalPolicy]
            → Invoice::clearPaymentLevel(int) | rejectPaymentApproval()
            → persist InvoicePaymentApproval audit row
    InvoiceController::changeStatusAction / editAction (existing, modified)
        → gate: !invoice.isPaid() && !invoice.isPaymentApproved() → block transition to PAID

## Data Flow — Timesheet approval

    TimesheetTeamController::approveAction / rejectAction (new)
        → IsGranted('approve_timesheet', 'entry')
            → TimesheetVoter: user isTeamleadOf() any of entry.project.teams
        → approve: Timesheet::approve(User $approver) sets approvedBy/approvedAt
        → reject: no-op / flash only — entry stays unapproved (D3)

## Data Flow — Dashboard

    ApprovalsDashboardController::indexAction
        → ExpenseRepository::findPendingForUser($user)              [existing]
        → TimesheetRepository::findPendingApprovalForUser($user)    [new]
        → InvoiceRepository::findPendingPaymentApprovalForUser($user) [new]
        → filter each result set in PHP with is_granted() per item (approve_expense / approve_timesheet / approve_invoice_payment)
        → merge, sort by date, render single read-only template

## File Changes

| File | Action | Description |
|---|---|---|
| `src/Entity/Timesheet.php` | Modify | add `approvedBy` (?User, nullable), `approvedAt` (?\DateTimeImmutable, nullable), `isApproved()`, `approve(User)` |
| `src/Voter/TimesheetVoter.php` | Modify | add `approve` attribute + team-lead vote logic (D2); add `isAllowedApproved()` guard to `canEdit`/`canDelete` (D7) |
| `src/Controller/TimesheetTeamController.php` | Modify | add `approveAction`/`rejectAction`, routes `admin_timesheet_approve`/`admin_timesheet_reject` |
| `src/Entity/InvoicePaymentApprovalLevel.php` | Create | mirrors `ExpenseApprovalLevel` (D5: `minAmount` float) |
| `src/Repository/InvoicePaymentApprovalLevelRepository.php` | Create | mirrors `ExpenseApprovalLevelRepository` |
| `src/Invoice/InvoicePaymentApprovalLevelResolver.php` | Create | duplicated algorithm (D1) |
| `src/Entity/InvoicePaymentApproval.php` | Create | audit entity, mirrors `ExpenseApproval` |
| `src/Repository/InvoicePaymentApprovalRepository.php` | Create | mirrors `ExpenseApprovalRepository` |
| `src/Invoice/InvoicePaymentApprovalPolicy.php` | Create | mirrors `ExpenseApprovalPolicy` |
| `src/Invoice/InvoicePaymentApprovalService.php` | Create | mirrors `ExpenseApprovalService`, adds `submit()` |
| `src/Entity/Invoice.php` | Modify | add `paymentApprovalStatus`, `paymentRequiredLevels`, `paymentCurrentLevel` + state methods (D4) |
| `src/Invoice/InvoiceService.php` | Modify | no logic change to `changeInvoiceStatus()` PAID case (kept for API completeness) |
| `src/Controller/InvoiceController.php` | Modify | gate in `changeStatusAction`/`editAction` (D6); add `submitPaymentApprovalAction`/`approvePaymentAction`/`rejectPaymentAction` |
| `src/Controller/InvoicePaymentApprovalLevelController.php` | Create | mirrors `ExpenseApprovalLevelController` exactly |
| `src/Form/InvoicePaymentApprovalLevelForm.php` | Create | mirrors `ExpenseApprovalLevelForm` |
| `src/Repository/TimesheetRepository.php` | Modify | add `findPendingApprovalForUser(User)` |
| `src/Repository/InvoiceRepository.php` | Modify | add `findPendingPaymentApprovalForUser(User)` |
| `src/Controller/ApprovalsDashboardController.php` | Create | aggregation controller |
| `templates/expense_approval_level/*` | Reference | copy structure for `templates/invoice_payment_approval_level/index.html.twig`, `edit.html.twig` |
| `templates/approvals_dashboard/index.html.twig` | Create | single new template, 3 sections (not composed partials — no existing "pending" partials are reusable fragments today, each domain's pending view is a full page) |
| `templates/timesheet/team_index.html.twig` (or equivalent) | Modify | add approve/reject buttons per row, gated `is_granted('approve', entry)` |
| `config/packages/gppro.yaml` | Modify | register `manage_invoice_payment_approval_levels` (ROLE_SUPER_ADMIN, alongside `manage_expense_approval_levels`); deliberately do NOT register `approve_timesheet`/`reject_timesheet`/`approve_invoice_payment`/`reject_invoice_payment` (mirrors D2 in `gppro.yaml:129` comment) |
| `migrations/VersionYYYYMMDDHHMMSS.php` (x2) | Create | Timesheet columns; InvoicePaymentApprovalLevel + InvoicePaymentApproval tables + Invoice columns (can combine into one or split per PR, see PR plan) |

## Interfaces / Contracts

```php
// Timesheet additions
#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(name: 'approved_by_id', nullable: true, onDelete: 'SET NULL')]
private ?User $approvedBy = null;

#[ORM\Column(name: 'approved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
private ?\DateTimeImmutable $approvedAt = null;

public function isApproved(): bool { return null !== $this->approvedAt; }

public function approve(User $approver): Timesheet
{
    $this->approvedBy = $approver;
    $this->approvedAt = new \DateTimeImmutable();
    return $this;
}

// Invoice additions (D4)
public const PAYMENT_APPROVAL_PENDING = 'pending';
public const PAYMENT_APPROVAL_APPROVED = 'approved';
public const PAYMENT_APPROVAL_REJECTED = 'rejected';

#[ORM\Column(name: 'payment_approval_status', type: Types::STRING, length: 20, nullable: true)]
private ?string $paymentApprovalStatus = null; // null = not yet submitted (incl. all grandfathered rows)

#[ORM\Column(name: 'payment_required_levels', type: Types::INTEGER, nullable: true)]
private ?int $paymentRequiredLevels = null;

#[ORM\Column(name: 'payment_current_level', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
private int $paymentCurrentLevel = 0;

public function isPaymentApproved(): bool { return self::PAYMENT_APPROVAL_APPROVED === $this->paymentApprovalStatus; }
public function nextPendingPaymentLevel(): ?int { /* mirrors Expense::nextPendingLevel() */ }
public function submitForPaymentApproval(int $requiredLevels): Invoice { /* mirrors submitForApproval(), freezes levels */ }
public function clearPaymentLevel(int $level): Invoice { /* mirrors clearLevel() */ }
public function rejectPaymentApproval(): Invoice { /* mirrors rejectApproval() */ }
```

## Permission Attribute Names (Open Question 1 — resolved)

| Attribute | Registered in `hasRolePermission`? | Resolution |
|---|---|---|
| `approve_timesheet` | No (mirrors `approve_expense`) | `TimesheetVoter`, team-lead check (D2) |
| `reject_timesheet` | No | `TimesheetVoter`, same check (D3: no distinct persisted state, still a real IsGranted attribute for the UI action) |
| `approve_invoice_payment` | No (mirrors `approve_expense`) | `InvoicePaymentApprovalPolicy::canApprove()`, wired into the existing `src/Voter/InvoiceVoter.php` (confirmed present; add to its `ALLOWED_ATTRIBUTES` and inject `InvoicePaymentApprovalPolicy`) |
| `reject_invoice_payment` | No | `InvoicePaymentApprovalPolicy::canReject()`, same `InvoiceVoter` wiring |
| `manage_invoice_payment_approval_levels` | Yes — registered, `ROLE_SUPER_ADMIN` | Admin CRUD gate, mirrors `manage_expense_approval_levels` placement in `gppro.yaml:132` |
| Invoice submit-for-approval action | reuses existing `edit_invoice` | Mirrors `expense_submit` reusing `edit_expense`, no new attribute |

## Route Names (Open Question 2 — resolved)

| Route | Path | Method |
|---|---|---|
| `admin_timesheet_approve` | `/team/timesheet/{id}/approve` | POST |
| `admin_timesheet_reject` | `/team/timesheet/{id}/reject` | POST |
| `invoice_submit_payment_approval` | `/invoice/{id}/submit-payment-approval` | POST |
| `invoice_approve_payment` | `/invoice/{id}/approve-payment` | POST |
| `invoice_reject_payment` | `/invoice/{id}/reject-payment` | POST |
| `admin_invoice_payment_approval_level_list` | `/admin/invoice/payment-approval-levels/` | GET |
| `admin_invoice_payment_approval_level_create` | `/admin/invoice/payment-approval-levels/create` | GET/POST |
| `admin_invoice_payment_approval_level_edit` | `/admin/invoice/payment-approval-levels/{id}/edit` | GET/POST |
| `admin_invoice_payment_approval_level_delete` | `/admin/invoice/payment-approval-levels/{id}/delete` | POST |
| `approvals_dashboard` | `/approvals` | GET |

## Testing Strategy (Strict TDD — RED tests required per capability)

| Layer | What to Test | Approach |
|---|---|---|
| Unit — Timesheet | `isApproved()`/`approve()` state transitions | Entity unit test, mirrors `ExpenseTest` state-machine cases |
| Unit — TimesheetVoter | team lead of project → approve granted; non-lead → denied; self-approval by lead → granted (decision 4); approved entry → edit/delete denied for owner and lead (D7) | Voter unit test with mocked `RolePermissionManager`/`LockdownService` |
| Unit — Invoice | `submitForPaymentApproval` freezes levels; `clearPaymentLevel` order enforcement; `rejectPaymentApproval` discards progress; already-approved invoice unaffected by later amount change (decision 5) | Entity unit test, mirrors `ExpenseTest` |
| Unit — InvoicePaymentApprovalLevelResolver | `resolve()`/`requiredLevelsFor()` against float amounts | Mirrors `ApprovalLevelResolverTest` |
| Unit — InvoicePaymentApprovalPolicy | four-eyes: creator cannot approve own invoice's payment (if applicable), already-approved-level user cannot re-approve, role/approver-user OR-semantics | Mirrors `ExpenseApprovalPolicyTest` |
| Functional — Timesheet read-only | approved entry: edit/delete routes return 403 for owner; `is_granted('edit')` false in template context | Controller functional test |
| Functional — Invoice PAID gate | both `changeStatusAction` and `editAction` paths: transition to PAID blocked without approval; allowed once `isPaymentApproved()`; grandfathered already-PAID invoice re-save (comment-only edit) never blocked (decision 8) | Controller functional test, both entry points explicitly |
| Functional — Admin CRUD | `InvoicePaymentApprovalLevelController` monotonic-ladder validation, level-1-cannot-delete | Mirrors `ExpenseApprovalLevelControllerTest` |
| Functional — Dashboard | user sees only items they're permitted to act on across all 3 domains; a user with `approve_expense` but not `approve_invoice_payment` sees Expense rows only; no permission leakage | Controller functional test with multiple fixture users/roles |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file
classification, or process-integration boundary. All new routes are ordinary
Symfony controller actions using existing `IsGranted`/voter/CSRF conventions.

## Migration / Rollout

Two new migrations (or split further per PR, see below):
1. `gppro_timesheet`: add nullable `approved_by_id` (FK → `gppro_users`, `SET NULL`), nullable `approved_at` (datetime).
2. `gppro_invoice_payment_approval_levels` (new table, mirrors `gppro_expense_approval_levels`), `gppro_invoice_payment_approvals` (new table, mirrors `gppro_expense_approvals`), plus `gppro_invoices` new columns: nullable `payment_approval_status`, nullable `payment_required_levels`, `payment_current_level` (default 0, not null).

All additive/nullable — no backfill, no data migration. Grandfathered PAID
invoices keep `payment_approval_status = NULL` permanently (decision 8);
`isPaymentApproved()` returns `false` for them, but the PAID-transition gate
(D6) only fires on a *new* transition into PAID, so they are never blocked
retroactively. No existing Expense table is touched.

## Recommended PR Split (chained, High 400-line-budget risk per proposal)

1. **PR1 — Invoice payment-approval foundation**: `InvoicePaymentApprovalLevel` +
   `InvoicePaymentApprovalLevelResolver` (D1) + repository + admin CRUD
   controller/form/templates + migration + permission registration. Fully
   standalone, testable, no `Invoice`/`InvoiceController` changes yet.
   Parallels the shipped `expense-category`-style single-table PR shape.
2. **PR2 — Invoice submit/approve/reject + PAID gate**: `Invoice` state
   fields (D4), `InvoicePaymentApproval` audit entity, `InvoicePaymentApprovalPolicy`/
   `Service`, new controller actions, D6's two gate insertion points, migration.
   Depends on PR1 (level resolver + levels table).
3. **PR3 — Timesheet approval**: entity fields, voter changes (D2, D7),
   `TimesheetTeamController` actions, migration. **Fully independent of
   PR1/PR2** — can run as its own first PR in parallel, or interleaved.
4. **PR4 — Approvals dashboard**: aggregation controller, two new repository
   query methods, template. Depends on PR2 (Invoice payment-approval query)
   and PR3 (Timesheet query) both being merged — the Expense side already exists.

Sequencing recommendation: PR3 (Timesheet) can ship first or in parallel
with PR1, since they touch disjoint files. PR2 must follow PR1. PR4 must be
last. Final ordering and exact PR boundaries are `sdd-tasks`'s call.

## Open Questions

None. Both proposal open questions (permission names, route names) are
resolved above. `src/Voter/InvoiceVoter.php` was confirmed to exist during
this design's codebase read (`ALLOWED_ATTRIBUTES = ['view_invoice',
'edit_invoice', 'delete_invoice']`) — PR2 extends it with
`approve_invoice_payment`/`reject_invoice_payment` rather than creating a new
voter class.
