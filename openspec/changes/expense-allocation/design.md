# Design: Expense Allocation

## Technical Approach

A self-contained module: four new tables, one migration, no existing table altered.
Entities in `App\Entity` (project convention — `Quotation`, `FxRate` all live there),
pure logic in `App\Expense` (screaming architecture, per `App\Milestone`/`App\FxRate`),
one `ExpenseVoter`, two controllers, one console command. Cross-charge consumes the
existing `Quotation`/`QuotationLine` aggregate; no quotation logic is duplicated.

## Architecture Decisions

### D1: `requiredRole` validated via `RoleService`, not `gppro_roles`

**Choice**: `required_role VARCHAR(50)`, validated with `Assert\Choice(callback)` against
`RoleService::getAvailableNames()`.
**Rejected**: FK to `Role` / `EntityType` over `RoleRepository`.
**Rationale**: `gppro_roles` holds only *custom* roles. `ROLE_USER/TEAMLEAD/ADMIN/SUPER_ADMIN`
are `security.yaml` constants and have **no row**. A FK would make it impossible to configure
a level for `ROLE_ADMIN`. `RoleService` merges both sources — it is the only correct oracle.
This corrects proposal wording ("validated against `gppro_roles`").

### D2: `approve_expense` is a voter attribute, never a registered permission

**Choice**: `ExpenseVoter` handles `approve_expense`/`reject_expense` with a subject; the rule
lives in `App\Expense\ExpenseApprovalPolicy` (pure, no DB). Controllers use
`#[IsGranted('approve_expense', 'expense')]`.
**Rejected**: (a) a static `approve_expense` permission in `gppro.yaml`; (b) inline controller checks.
**Rationale**: `RolePermissionVoter::supportsType()` only matches `subject === 'null'`, so a
registered permission would grant approval **globally**, bypassing level logic under the
affirmative decision strategy. A policy object keeps the four-eyes rules unit-testable and
reusable by Twig (`is_granted`) for button visibility.

### D3: CLP as PHP `int`, percentages as basis points internally

**Choice**: `amount`/`amount_clp` = `Types::INTEGER` (whole CLP, `Assert\Range(min:1, max:2147483647)`);
`percentage` = `DECIMAL(5,2)`; `AllocationSplitter` converts to basis points (0..10000) for
sum-to-100 and remainder math.
**Rejected**: `DECIMAL(18,4)` like `QuotationLine`.
**Rationale**: the last-allocation-remainder rule (A3) must be exact. Integer arithmetic is exact
and needs no bcmath; DECIMAL-as-string would. Cap documented at ~2.1B CLP per expense.

### D4: Level resolution is a service, not a repository method

**Choice**: `App\Expense\ApprovalLevelResolver` over `ExpenseApprovalLevelRepository`
(mirrors `ClpConverter` over `FxRateRepository`).
**Rejected**: `ExpenseApprovalLevelRepository::countRequiredFor(int $amount)`.
**Rationale**: the cumulative-monotonic rule is domain logic, needs unit tests against a fixed
level list, and is consumed by both the resolver-at-submit and the "pending my approval" query.

### D5: Cross-charge target quotation is chosen explicitly

**Choice**: the charge action posts a `quotation` id; `ExpenseCrossChargeService` validates
project match, `STATUS_DRAFT`, `CURRENCY_CLP`, allocation not already `charged`, parent `approved`.
**Rejected**: auto-selecting "the" draft quotation of the project.
**Rationale**: a project can have 0..N drafts; auto-pick is nondeterministic and mutates a
client-facing document by surprise.

### D6: One migration, four tables + level-1 seed

**Choice**: single `Version2026MMDDHHMMSS`, `up()` creates all four tables, FKs, indexes, and
inserts `(1, 0, 'ROLE_TEAMLEAD')`; `down()` drops FKs then tables.
**Rejected**: split schema/seed migrations.
**Rationale**: the rollback plan is atomic ("drop the four tables"); a split gives no independent
rollback value and risks a half-configured, unapprovable system.

### D7: Immutability enforced in voter + guarded transitions, not per-field setters

**Choice**: `Expense::isEditable()` (`status === draft`) gates `edit_expense`. `ExpenseVoter::delete_expense`
allows `status in (draft, rejected)` — a rejected expense is discarded, not edited, so it can be deleted
without reopening the immutability question. Transitions are guarded methods throwing `\DomainException`.
No public `setStatus()`.
**Rejected**: guards inside every setter.
**Rationale**: setter guards break Symfony Forms data binding. This is the `Quotation` precedent.

## Data Flow

    ExpenseForm ──> Expense(draft) + N ExpenseAllocation ──> ExpenseRepository::saveExpense()
         │                                     │
         │                          AllocationSplitter (bp -> CLP, remainder to last)
         ▼
    submit ──> ApprovalLevelResolver(amount) ──> requiredLevels (FROZEN) ──> pending_approval
         │
         ▼
    approve ──> ExpenseVoter -> ExpenseApprovalPolicy(level.requiredRole, creator, distinct-user, SUPER_ADMIN)
         │           └─> ExpenseApprovalService: +ExpenseApproval row, currentLevel++, one transaction
         │                  currentLevel === requiredLevels ──> approved
         │                  reject at any level ──────────────> rejected (levels discarded)
         ▼
    charge ──> ExpenseCrossChargeService ──> new QuotationLine on a draft CLP Quotation
                                             allocation.charged = true, allocation.quotationLine set

    cron ──> gppro:expenses:generate-recurring ──> clone(draft) keyed (sourceExpense, periodKey)

## File Changes

### Entities (`src/Entity/`)

| File | Key fields |
|---|---|
| `Expense.php` | `id`, `description` (TEXT, NotBlank), `amount` (INT CLP), `expenseDate` (DATE_IMMUTABLE, NotNull), `recurrence` (`null\|'month'`, `Assert\Choice`), `status` (draft/pending_approval/approved/rejected, private `setStatus`), `requiredLevels` (INT, default 0), `currentLevel` (INT, default 0), `createdBy` (ManyToOne User, SET NULL), `sourceExpense` (self ManyToOne, SET NULL), `periodKey` (VARCHAR(7) nullable), `allocations` (OneToMany, cascade persist/remove, orphanRemoval, `Assert\Count(min:1,max:20)`), `approvals` (OneToMany), `CreatedTrait`/`ModifiedTrait`. Methods: `submitForApproval(int $requiredLevels)`, `clearLevel(int $level)`, `rejectApproval()`, `isEditable()`, `isApproved()`, `nextPendingLevel(): ?int`, `addAllocation`/`removeAllocation` |
| `ExpenseAllocation.php` | `expense` (ManyToOne inversedBy `allocations`, CASCADE, NotNull), `project` (ManyToOne, RESTRICT, NotNull), `percentage` (DECIMAL(5,2), `Assert\Range(min:0.01,max:100)`), `amountClp` (INT, derived), `charged` (BOOL default false), `quotationLine` (OneToOne QuotationLine nullable, SET NULL, unique). Methods: `markCharged(QuotationLine $line)` throwing `\DomainException` when already charged |
| `ExpenseApprovalLevel.php` | `level` (INT, unique, `Assert\Range(min:1)`), `minAmount` (INT, `Assert\PositiveOrZero`), `requiredRole` (VARCHAR(50), `Assert\Choice(callback)` → D1). Class-level `Assert\Callback` for the monotonic invariant |
| `ExpenseApproval.php` | `expense` (ManyToOne, CASCADE), `level` (INT), `decision` (`approved\|rejected`), `approvedBy` (ManyToOne User, SET NULL), `approvedAt` (DATETIME_IMMUTABLE), `note` (TEXT nullable, `Assert\Length(max:1000)`). Unique index `(expense_id, level)` |

Tables: `gppro_expenses`, `gppro_expense_allocations`, `gppro_expense_approval_levels`,
`gppro_expense_approvals`. Unique index `uniq_gppro_expenses_recurrence (source_expense_id, period_key)`
— DB-level idempotency for the cron, safe under concurrency.

### Repositories (`src/Repository/`)

| File | Methods |
|---|---|
| `ExpenseRepository.php` | `findForListing(?string $status)`, `findPendingForUser(User)`, `findRecurringSources(string $periodKey)`, `saveExpense()`, `deleteExpense()` |
| `ExpenseApprovalLevelRepository.php` | `findAllOrdered()`, `saveLevel()`, `deleteLevel()` (refuses to delete level 1) |
| `ExpenseApprovalRepository.php` | `findByExpense(Expense)`, `hasUserApprovedAnyLevel(Expense, User)` |

### Domain services (`src/Expense/`)

| File | Responsibility |
|---|---|
| `AllocationSplitter.php` | percentage (bp) → CLP per allocation; remainder to the last |
| `AllocationPercentageValidator.php` | ≤100% in draft, exactly 100% at submit |
| `ApprovalLevelResolver.php` | amount → ordered `ExpenseApprovalLevel[]`; `requiredLevelsFor(int): int`; `levelFor(Expense, int)` |
| `ExpenseApprovalPolicy.php` | pure four-eyes rules: creator≠approver, distinct user per level, role match, `ROLE_SUPER_ADMIN` break-glass |
| `ExpenseApprovalService.php` | transactional submit / approve / reject: audit row + counter + status |
| `ExpenseCrossChargeService.php` | D5 validation + `QuotationLine` creation (qty `'1'`, unitPrice = `amountClp`, description `"<expense.description> (<expenseDate>)"`) |
| `RecurringExpenseGenerator.php` | clone header+allocations for a `periodKey`; returns a per-source result object (`GENERATED`/`SKIPPED_EXISTING`/`SKIPPED_NOT_RECURRING`), mirroring `FxRateSyncResult`/`FxRateSyncStatus` |

### Command, Voter, Forms, Controllers

| File | Notes |
|---|---|
| `src/Command/ExpensesGenerateRecurringCommand.php` | `#[AsCommand('gppro:expenses:generate-recurring')]`, `--period=YYYY-MM` (default: current month in `America/Santiago`), `--force`, `--dry-run`. `SUCCESS`/`FAILURE`/`INVALID` exactly like `FxRatesSyncCommand`; logs via `LoggerInterface`, `ClockInterface` injected |
| `src/Voter/ExpenseVoter.php` | attributes `view/create/edit/delete/charge/approve/reject_expense`. Static ones via `RolePermissionManager::hasRolePermission`; `approve/reject` delegate to `ExpenseApprovalPolicy`; `edit/delete` also require `isEditable()`; `charge_expense` requires `isApproved()` |
| `src/Form/ExpenseForm.php` | `description`, `amount` (`IntegerType`), `expenseDate` (`DatePickerType`), `recurrence` (`ChoiceType`), `allocations` (`CollectionType`, `allow_add/allow_delete`, `by_reference:false`, `prototype:true`) — `QuotationForm` shape |
| `src/Form/ExpenseAllocationForm.php` | `project` (`ProjectType`, `query_builder_for_user:true`, `ignore_date:true`), `percentage` (`NumberType`, scale 2) |
| `src/Form/ExpenseApprovalLevelForm.php` | `level`, `minAmount`, `requiredRole` (`ChoiceType` fed by `RoleService::getAvailableNames()`) |
| `src/Form/ExpenseChargeForm.php` | `quotation` (`EntityType`, draft+CLP quotations of the allocation's project) |
| `src/Form/ExpenseApprovalDecisionForm.php` | optional `note` |
| `src/Controller/ExpenseController.php` | `/expense`: `expense_list` GET, `expense_pending` GET, `expense_create`, `expense_edit`, `expense_view`, `expense_submit` POST+CSRF, `expense_approve` POST+CSRF, `expense_reject` POST+CSRF, `expense_delete` POST+CSRF, `expense_allocation_charge` POST+CSRF. `\DomainException` → `flashError`, per `QuotationController::convert()` |
| `src/Controller/ExpenseApprovalLevelController.php` | `/admin/expense/approval-levels`, `#[IsGranted('manage_expense_approval_levels')]`, index/create/edit/delete — `QuotationCatalogController` shape |

### Templates (`templates/expense/`, `templates/expense_approval_level/`)

| Template | Analogue |
|---|---|
| `expense/index.html.twig` | `quotation/index.html.twig` — status filter, amount, levels badge `n/N` |
| `expense/pending.html.twig` | same table, filtered to "pending my approval" |
| `expense/edit.html.twig` | `quotation/edit.html.twig` — collection prototype for allocations, live percentage total |
| `expense/view.html.twig` | `quotation/view.html.twig` — allocations, approval timeline, submit/approve/reject/charge buttons behind `is_granted(..., expense)` |
| `expense_approval_level/index.html.twig` + `edit.html.twig` | `quotation_catalog/*` — index warns when a level's role has zero active users |

### Modified

| File | Change |
|---|---|
| `config/packages/gppro.yaml` | `sets.EXPENSES: ['view_expense','create_expense','edit_expense','charge_expense']`, `sets.EXPENSES_ALL: ['delete_expense']`, `roles.ROLE_SUPER_ADMIN += manage_expense_approval_levels`; `maps`: `EXPENSES` → TEAMLEAD/ADMIN/SUPER_ADMIN, `EXPENSES_ALL` → SUPER_ADMIN. **`approve_expense` is deliberately absent** (D2) |
| `src/EventSubscriber/MenuSubscriber.php` | `expenses` parent with `expense_list`, `expense_pending`, `admin_expense_approval_levels` children, guarded by `isGranted` |
| `translations/messages.*.xlf` | new keys |

## Interfaces / Contracts

```php
final class ApprovalLevelResolver
{
    /** @return ExpenseApprovalLevel[] ordered by level ASC, every level with minAmount <= $amountClp */
    public function resolve(int $amountClp): array;
    public function requiredLevelsFor(int $amountClp): int;
}

final class ExpenseApprovalPolicy
{
    /** Creator cannot approve; a user clears at most one level per expense;
     *  user must hold level.requiredRole, or be ROLE_SUPER_ADMIN (break-glass). */
    public function canApprove(Expense $expense, User $user): bool;
    public function canReject(Expense $expense, User $user): bool;
}

final class AllocationSplitter
{
    /** @param int[] $basisPoints summing to 10000 @return int[] CLP, remainder on the last */
    public function split(int $amountClp, array $basisPoints): array;
}
```

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit | `AllocationSplitter` (40/60 → 400k/600k; 33.33/33.33/33.34; remainder), `ApprovalLevelResolver` (500k→1, 2M→2, boundary `amount === minAmount`), `ExpenseApprovalPolicy` (creator, repeat approver, wrong role, SUPER_ADMIN), `Expense` transitions (`\DomainException` on illegal moves) | PHPUnit, no DB — `tests/Expense/`, `tests/Entity/ExpenseTest.php` |
| Unit | `ExpenseVoter` | `tests/Voter/ExpenseVoterTest.php`, mirroring `QuotationVoterTest` |
| Integration | `ExpenseApprovalService` transactionality, `(source_expense_id, period_key)` uniqueness, `ExpenseCrossChargeService` double-charge rejection | `KernelTestCase` + DB |
| Integration | Command idempotency: two runs on the same period generate one copy | `CommandTester` |
| Controller | route-level `#[IsGranted]` for each role | `ControllerBaseTest` precedent |

## Threat Matrix

N/A — no routing-security, shell, subprocess, VCS/PR automation, executable-file classification,
or process-integration boundary. The new console command takes only `--period`/`--force`/`--dry-run`
scalars, spawns nothing, and touches no filesystem.

## Migration / Rollout

One migration creates four tables and seeds level 1 (`minAmount = 0`, `ROLE_TEAMLEAD`) so the
system is approvable on day one (D6). No existing table is altered; no data backfill. `down()`
drops FKs then tables. Rolling back also requires reverting `gppro.yaml`.

## Resolved Questions

- A2b (each level cleared by a distinct user) stays strict, as originally proposed — confirmed by the user.
- `delete_expense` is allowed on both `draft` and `rejected` — confirmed by the user.
