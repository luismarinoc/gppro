# Design: Expense Approval By Person

## Technical Approach

Additive, single-column change following the `User::$supervisor` precedent end to
end. `ExpenseApprovalLevel` gains a nullable `ManyToOne User $approverUser`
(`approver_user_id`, `ON DELETE SET NULL`); `ExpenseApprovalPolicy::canDecide()`
gains exactly one positive branch **below** the three negative gates; the form
gains one `UserType::class` field with `required: false`; the index template gains
one column. No new class, table, service, permission, or namespace. `null`
reproduces today's behavior byte for byte (proposal A4).

## Architecture Decisions

| # | Decision | Choice | Rejected | Rationale |
|---|----------|--------|----------|-----------|
| D1 | Approver resolution | OR: named approver **or** role holder | Replace-semantics | PO decision A1; keeps the role fallback alive so no level can stall |
| D2 | Branch placement | After creator + already-approved + super-admin gates | Above the gates | A named approver must never bypass four-eyes; ordering is the security invariant |
| D3 | Persistence of the rule | Live read at decision time, no snapshot | Freeze on `submitForApproval()` | A2; symmetric with `requiredRole`, which is already not snapshotted (`requiredLevels` is the only frozen value) |
| D4 | FK deletion | `ON DELETE SET NULL` | `RESTRICT` | Only convention in this project (`created_by_id`, `approved_by_id`, `supervisor_id`); degrades to role-only |
| D5 | Help text delivery | Symfony `help` form option | New markup in `edit.html.twig` | `edit.html.twig` renders `form_widget(form)` generically, so `help` surfaces with zero template change (project convention: `UserEditType` `user_identifier.help`) |
| D6 | Disabled assigned approver | Pass `include_users` with current value | Rely on `include_disabled: false` alone | `UserType`'s own docblock (kimai#1841) documents `include_users` exactly to stop a disabled assignee being silently dropped on the next save |
| D7 | Identity check | `===` object identity | `getId()` comparison | Mirrors the adjacent `$expense->getCreatedBy() === $user`; changing one without the other would be inconsistent |
| D8 | Migration style | Raw `addSql`, `gppro_`-prefixed named index/FK | Schema-object API (`Version20230819090536`) | Every migration for this table uses raw SQL with `UPPER_SNAKE` constraint names |

## Data Flow

    Admin ─► ExpenseApprovalLevelController::form
              └─► ExpenseApprovalLevelForm (approverUser: UserType, required:false)
                    └─► ExpenseApprovalLevel.approverUser ──► gppro_expense_approval_levels.approver_user_id
                                                                       │ (read live, no snapshot)
    Approver ─► ExpenseVoter ─► ExpenseApprovalPolicy::canDecide ◄──────┘
                                 gates 1-3 (deny) → super-admin → approverUser === user → hasRole()

## Interfaces / Contracts

### `src/Entity/ExpenseApprovalLevel.php` — after `$requiredRole`

```php
#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(name: 'approver_user_id', nullable: true, onDelete: 'SET NULL')]
private ?User $approverUser = null;

public function getApproverUser(): ?User
{
    return $this->approverUser;
}

public function setApproverUser(?User $approverUser): ExpenseApprovalLevel
{
    $this->approverUser = $approverUser;

    return $this;
}
```

Add `use App\Entity\User;` is not needed (same namespace). Setter is nullable
(unlike the three existing setters) so the picker can clear the value.

### `src/Expense/ExpenseApprovalPolicy.php` — exact before/after of `canDecide()`

Before (lines 63-69):

```php
        if ($user->isSuperAdmin()) {
            return true;
        }

        $level = $this->findLevel($pendingLevel);

        return null !== $level && $user->hasRole($level->getRequiredRole());
```

After:

```php
        if ($user->isSuperAdmin()) {
            return true;
        }

        $level = $this->findLevel($pendingLevel);

        if (null === $level) {
            return false;
        }

        // OR-semantics (design D1/D2): the named approver is additive to the
        // required role and sits BELOW the creator / already-approved gates,
        // so it can never bypass four-eyes.
        if ($level->getApproverUser() === $user) {
            return true;
        }

        return $user->hasRole($level->getRequiredRole());
```

Unchanged and still evaluated first, in this order: `null === $pendingLevel`,
`$expense->getCreatedBy() === $user`, `hasUserApprovedAnyLevel()`. All three
`return false` before any approver logic runs.

### `src/Form/ExpenseApprovalLevelForm.php` — 4th field

```php
        /** @var ExpenseApprovalLevel|null $level */
        $level = \array_key_exists('data', $options) ? $options['data'] : null;

        // ... existing three ->add() calls, then:
            ->add('approverUser', UserType::class, [
                'required' => false,
                'label' => 'expense_approval_level.approver_user',
                'help' => 'expense_approval_level.approver_user.help',
                // keep an already-assigned but disabled user selectable (D6)
                'include_users' => ($level?->getApproverUser() !== null ? [$level->getApproverUser()] : []),
            ]);
```

Add `use App\Form\Type\UserType;` (the form currently imports only `UserRoleType`).

### `migrations/Version20260813160000.php`

Extends `App\Doctrine\AbstractMigration`. Description:
`'Add an optional named approver to expense approval levels'`.

```php
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expense_approval_levels ADD approver_user_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER ON gppro_expense_approval_levels (approver_user_id)');
        $this->addSql('ALTER TABLE gppro_expense_approval_levels ADD CONSTRAINT FK_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER FOREIGN KEY (approver_user_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expense_approval_levels DROP FOREIGN KEY FK_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER');
        $this->addSql('DROP INDEX IDX_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER ON gppro_expense_approval_levels');
        $this->addSql('ALTER TABLE gppro_expense_approval_levels DROP approver_user_id');
    }
```

No backfill, no data transformation (A8). `20260813160000` is later than the
current newest migration `Version20260813150000`.

### `templates/expense_approval_level/index.html.twig`

Header row — insert after the `required_role` `<th>`:

```twig
<th>{{ 'expense_approval_level.approver_user'|trans }}</th>
```

Body row — insert after the `label_role` `<td>`:

```twig
<td>{% if level.approverUser %}{{ widgets.label_user(level.approverUser) }}{% else %}<span class="text-muted">&mdash;</span>{% endif %}</td>
```

`widgets.label_user()` already exists (`templates/macros/widgets.html.twig:115`,
used in `invoice/listing`, `export/index`, `timesheet/index`) and is the badge
counterpart of `label_role` — no new macro. The file already imports `widgets`.

Empty-state row: change `colspan="4"` to `colspan="5"`.

### `templates/expense_approval_level/edit.html.twig`

**Unchanged.** `form_widget(form)` renders the new field and its `help` text
(D5).

### Translations — continue from the current max id `gpExpense49`

`translations/messages.en.xlf` (insert after `gpExpense49`, before `gpActBrd1`):

```xml
      <trans-unit id="gpExpense50" resname="expense_approval_level.approver_user">
        <source>expense_approval_level.approver_user</source>
        <target>Named approver</target>
      </trans-unit>
      <trans-unit id="gpExpense51" resname="expense_approval_level.approver_user.help">
        <source>expense_approval_level.approver_user.help</source>
        <target>Optional. This person can clear the level in addition to anyone holding the required role. Leave it empty to use the role only.</target>
      </trans-unit>
```

`translations/messages.es.xlf` — same ids/resnames, with this file's
`xml:space="preserve"` attribute and `state="translated"` targets:
`Aprobador designado` / `Opcional. Esta persona puede aprobar el nivel además de
cualquier usuario con el rol requerido. Déjalo vacío para usar solo el rol.`

The help text is the mitigation for the proposal's "reviewers read *asignar
persona* as *only that person can approve*" risk.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `src/Entity/ExpenseApprovalLevel.php` | Modify | `$approverUser` + nullable accessors |
| `migrations/Version20260813160000.php` | Create | Column + index + `SET NULL` FK |
| `src/Expense/ExpenseApprovalPolicy.php` | Modify | One positive branch below the gates |
| `src/Form/ExpenseApprovalLevelForm.php` | Modify | 4th field, `include_users`, `help` |
| `templates/expense_approval_level/index.html.twig` | Modify | Column + colspan 4→5 |
| `translations/messages.{en,es}.xlf` | Modify | `gpExpense50`, `gpExpense51` |
| `tests/Entity/ExpenseApprovalLevelTest.php` | Modify | Null default + setter round-trip |
| `tests/Expense/ExpenseApprovalPolicyTest.php` | Modify | 5 new OR-semantics/gate tests |
| `tests/Form/ExpenseApprovalLevelFormTest.php` | Modify | Field presence + `required === false` |
| `tests/Controller/ExpenseApprovalLevelControllerTest.php` | Modify | Save with and without an approver |
| `src/Controller/ExpenseApprovalLevelController.php` | Unchanged | `isMonotonic()` reads only `getLevel()`/`getMinAmount()` — verified, no `approverUser` reference |
| `templates/expense_approval_level/edit.html.twig` | Unchanged | Generic `form_widget` (D5) |
| `src/Repository/ExpenseApprovalLevelRepository.php`, `ExpenseApprovalService`, `ApprovalLevelResolver`, `ExpenseVoter`, `Expense`, `ExpenseAllocation` | Unchanged | Out of scope |

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit (entity) | `getApproverUser()` null by default; setter accepts `User` and `null` | Extend `ExpenseApprovalLevelTest`, same fluent style |
| Unit (policy) | Named approver without the role clears; role holder clears a level naming someone else; neither → denied; creator-who-is-named → denied; already-approved-who-is-named → denied; null approver parity | Extend `makeLevel()` with an optional `?User $approverUser` argument; existing mocks unchanged |
| Unit (form) | `approverUser` present, `required` option is `false` | `TypeTestCase`; assert presence only where `UserType` needs `UserRepository` (same caveat as `requiredRole`) |
| Integration | Create a level with and without an approver; existing POSTs that omit `approverUser` still succeed | Extend `ExpenseApprovalLevelControllerTest`; existing tests already post without the key and must stay green |
| Integration | `SET NULL` fallback: delete the assigned user, level still resolvable by role | Delete via EM, `refresh()` the level, assert `getApproverUser()` is null and the policy still allows a role holder |

RED-first order: policy branch-ordering tests (creator-who-is-named denied,
already-approved-who-is-named denied) must exist and fail before the branch is
written — they are the guard for the D2 security invariant.

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file
classification, or process-integration boundary. The one security-relevant
surface is authorization branch ordering (D2), covered by the two explicit RED
tests above rather than by the matrix.

## Migration / Rollout

Single forward migration, no backfill, no feature flag. Existing rows get
`NULL` and behave identically. Rollback = `down()` (drops FK, index, column) plus
reverting code; no expense data is touched.

## Open Questions

Implementation details the proposal left open, resolved here:

- [x] **Disabled assignee silently cleared** — `UserType` defaults to
      `include_disabled: false`, so an assigned user who is later disabled drops
      out of the choice list and the next save would silently null the column.
      Resolved by D6 (`include_users`), not by flipping `include_disabled`
      (which would let admins newly assign disabled users).
- [x] **Help text location** — form `help` option, not a template edit (D5).
- [x] **List rendering for null** — muted `&mdash;`, no new translation key.
- [x] **`colspan`** — the empty-state row must go 4→5.
- [x] **Existing controller POSTs** — omit `approverUser`; `required: false`
      accepts a missing key, so no existing test fixture changes.
- [ ] **Identity vs id comparison** (D7): `$level->getApproverUser() === $user`
      relies on the Doctrine identity map, exactly as the adjacent
      `getCreatedBy() === $user` does. If an integration test ever shows a
      proxy/identity mismatch, both comparisons must switch to `getId()`
      together — changing only the new one is worse than leaving both.
- [ ] **No self-exclusion on the picker** — unlike `supervisor`
      (`ignore_users: [$user]`), a level has no user to exclude; any user may be
      named, including the expense creator. That case is intentionally handled by
      the creator gate at decision time, not by the form.
