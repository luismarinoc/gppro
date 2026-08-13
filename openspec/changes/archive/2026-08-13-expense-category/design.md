# Design: Expense Category

## Technical Approach

One nullable column, one const family, one form field, three template edits, nine
translation keys, one setter call. `Expense` already carries three closed-set const
families (`STATUS_*`/`STATUSES`, `RECURRENCE_*`/`RECURRENCES`); `CATEGORY_*`/`CATEGORIES`
is the fourth, written in exactly that shape. Nothing in `src/Expense/` other than
`RecurringExpenseGenerator::cloneForPeriod()` is touched; no allocation, approval,
voter, permission, or cross-charge code changes.

## Architecture Decisions

### D1: Fourth const family on `Expense`, no new type or entity

**Choice**: `CATEGORY_RENT|EQUIPMENT|ELECTRICITY|PHONE|SERVICES|OTHER` + `/** @var string[] */ public const CATEGORIES`, placed after `RECURRENCES` (Expense.php:45), before `use CreatedTrait`.
**Rejected**: PHP backed enum; `ExpenseCategory` entity.
**Rationale**: `STATUS_*`, `RECURRENCE_*` and `FxRate::INDICATOR_*` are all plain string consts with an array companion and `Assert\Choice`. A backed enum would be the only one in the codebase and would break `ChoiceType` symmetry with `recurrence`. Entity rejection is already argued in the proposal.

### D2: `Assert\NotBlank` lives on the FORM field, not the entity property

**Choice**: entity carries `#[Assert\Choice(choices: self::CATEGORIES)]` only; `ExpenseForm` carries `'constraints' => [new NotBlank()]`.
**Rejected**: `#[Assert\NotBlank]` on `Expense::$category` (the `FxRate::$indicator` / `Expense::$description` shape).
**Rationale**: Rule 7 is scoped to the *write path* ("required whenever an expense is created or edited through the form; pre-existing legacy rows may be uncategorized"). `Assert\Choice` ignores `null`, so an entity-level `Choice` alone states the true at-rest invariant: *if set, one of six*. Entity-level `NotBlank` would declare every legacy row structurally invalid while the column is deliberately nullable — a contradiction any future validated path (import, API, re-validation) would trip on, and it would mark a `cloneForPeriod()` copy of a legacy source invalid. `FxRate::$indicator` and `Expense::$description` are `nullable: false` columns, so their entity-level `NotBlank` is consistent there and is *not* the applicable precedent. Form-level `NotBlank` is an established project pattern: `RoleType`, `UserTwoFactorType`, `TimesheetEditForm`, `InvoiceDocumentUploadForm`.

### D3: `required: true` **plus** an explicit placeholder

**Choice**: `'required' => true, 'placeholder' => 'expense.category_placeholder'`.
**Rejected**: `required: true` with the default placeholder.
**Rationale**: for a required, collapsed `ChoiceType` Symfony defaults `placeholder` to `null`, which emits no empty `<option>` — the browser then silently preselects `Rent` on every new expense and `NotBlank` never fires. The explicit placeholder forces a conscious pick and lets HTML5 `required` block submit client-side. This detail was open in the proposal.

### D4: Additive nullable column, no index

**Choice**: `ALTER TABLE gppro_expenses ADD category VARCHAR(20) DEFAULT NULL`; no index.
**Rejected**: indexing `category`; a `NOT NULL DEFAULT 'other'` backfill.
**Rationale**: A4 rules out filtering/grouping UI, so there is no query predicate to serve; a six-value, low-cardinality index would be ignored by MySQL anyway. A backfill would mislabel real history as `other`. Length 20 matches `status`; the longest value is `electricity` (11).

### D5: Null display is a translated `expense.category_none`, not `''` or a bare em dash

**Choice**: `{{ expense.category ? ('expense.category.' ~ expense.category)|trans : 'expense.category_none'|trans }}` in both `view` and `index`.
**Rejected**: the local `x ? y : ''` idiom (view.html.twig:25,36); a literal `—`.
**Rationale**: the existing `: ''` fallbacks guard fields that are `NotNull` at the DB level — the ternary only satisfies a nullable PHP type, and the empty branch is unreachable. Null category is a *real, legible business state* (legacy uncategorized), so an empty cell would be indistinguishable from a rendering bug, and a bare em dash is neither translatable nor assertable. One key, one expression, identical in both templates.

### D6: The generator copies the category verbatim, including `null`

**Choice**: `$copy->setCategory($source->getCategory());` — no `?? CATEGORY_OTHER` default.
**Rationale**: A5 is "same category as its source". Defaulting would invent data for a legacy recurring source and quietly violate D4's no-backfill stance.

## Data Flow

    ExpenseForm(category required) ──> Expense::$category ──> gppro_expenses.category
            │                                                        │
            │  Assert\Choice (entity, null-tolerant)                 │
            ▼                                                        ▼
    edit/view/index Twig ──('expense.category.' ~ v)|trans      cloneForPeriod()
                          └─ null ──> 'expense.category_none'   └─ setCategory(source)

Category never reaches `ExpenseAllocation`, `AllocationSplitter`, `ApprovalLevelResolver`,
`ExpenseApprovalPolicy`, or `ExpenseCrossChargeService`.

## File Changes

| File | Action | Exact change |
|---|---|---|
| `src/Entity/Expense.php` | Modify | Six `CATEGORY_*` consts + `CATEGORIES` after line 45; `$category` property after `$recurrence` (line 72); `getCategory()`/`setCategory(?string)` after `setRecurrence()` (line 166) |
| `migrations/Version20260813150000.php` | Create | `AbstractMigration`, `final`, description `'Add an overhead category to expenses'` |
| `src/Form/ExpenseForm.php` | Modify | `category` `ChoiceType` inserted between `expenseDate` and `recurrence`; `use Symfony\Component\Validator\Constraints\NotBlank;` |
| `templates/expense/edit.html.twig` | Modify | `{{ form_row(form.category) }}` between line 11 (`expenseDate`) and line 12 (`recurrence`) |
| `templates/expense/view.html.twig` | Modify | One `<p>` between line 25 (date) and line 26 (status) |
| `templates/expense/index.html.twig` | Modify | `<th>` + `<td>` between date and status; **`colspan="5"` → `colspan="6"`** on line 25 |
| `translations/messages.en.xlf` | Modify | 9 units `gpExpense41`–`gpExpense49` after `gpExpense40` (line 2388) |
| `translations/messages.es.xlf` | Modify | Same 9 ids after `gpExpense40` (line 2368), with `xml:space="preserve"` + `state="translated"` |
| `src/Expense/RecurringExpenseGenerator.php` | Modify | One line after line 76 |
| `tests/Form/ExpenseFormTest.php` | Modify | Category field case + presence assertion |
| `tests/Entity/ExpenseValidationTest.php` | Create | `EntityValidationTestTrait`, `#[Group('integration')]` |
| `tests/Expense/RecurringExpenseGeneratorTest.php` | Modify | Category on the fixture + two assertions |
| `templates/expense/pending.html.twig` | **Unchanged** | Deliberate: it is a separate approval-queue table (description / amount / level), not a copy of the list; adding a classification column there serves no approver decision |
| `src/Entity/ExpenseAllocation.php`, `src/Voter/ExpenseVoter.php`, `config/packages/gppro.yaml` | Unchanged | No allocation, voter, or permission change |

## Interfaces / Contracts

```php
// src/Entity/Expense.php — after RECURRENCES (line 45)
public const CATEGORY_RENT = 'rent';
public const CATEGORY_EQUIPMENT = 'equipment';
public const CATEGORY_ELECTRICITY = 'electricity';
public const CATEGORY_PHONE = 'phone';
public const CATEGORY_SERVICES = 'services';
public const CATEGORY_OTHER = 'other';

/** @var string[] */
public const CATEGORIES = [
    self::CATEGORY_RENT,
    self::CATEGORY_EQUIPMENT,
    self::CATEGORY_ELECTRICITY,
    self::CATEGORY_PHONE,
    self::CATEGORY_SERVICES,
    self::CATEGORY_OTHER,
];

// after $recurrence (line 72)
#[ORM\Column(name: 'category', type: Types::STRING, length: 20, nullable: true)]
#[Assert\Choice(choices: self::CATEGORIES)]
private ?string $category = null;

// after setRecurrence() (line 166)
public function getCategory(): ?string
{
    return $this->category;
}

public function setCategory(?string $category): Expense
{
    $this->category = $category;

    return $this;
}
```

```php
// src/Form/ExpenseForm.php — between expenseDate and recurrence
->add('category', ChoiceType::class, [
    'required' => true,
    'placeholder' => 'expense.category_placeholder',
    'label' => 'expense.category',
    'constraints' => [new NotBlank()],
    'choices' => [
        'expense.category.rent' => Expense::CATEGORY_RENT,
        'expense.category.equipment' => Expense::CATEGORY_EQUIPMENT,
        'expense.category.electricity' => Expense::CATEGORY_ELECTRICITY,
        'expense.category.phone' => Expense::CATEGORY_PHONE,
        'expense.category.services' => Expense::CATEGORY_SERVICES,
        'expense.category.other' => Expense::CATEGORY_OTHER,
    ],
])
```

```php
// migrations/Version20260813150000.php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE gppro_expenses ADD category VARCHAR(20) DEFAULT NULL');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE gppro_expenses DROP category');
}
```

`AbstractMigration::addSql()` rejects `DROP TABLE `, not `ALTER TABLE ... DROP` — the
`down()` above is the exact `Version20260811210000` precedent.

```php
// src/Expense/RecurringExpenseGenerator.php — one line, after line 76
$copy->setCategory($source->getCategory());
```

```twig
{# view.html.twig, between date (line 25) and status (line 26) #}
<p>{{ 'expense.category'|trans }}: {{ expense.category ? ('expense.category.' ~ expense.category)|trans : 'expense.category_none'|trans }}</p>

{# index.html.twig, <th> and <td> between date and status #}
<th>{{ 'expense.category'|trans }}</th>
<td>{{ expense.category ? ('expense.category.' ~ expense.category)|trans : 'expense.category_none'|trans }}</td>
```

### Translation units (current max is `gpExpense40`)

| id | resname | en | es |
|---|---|---|---|
| `gpExpense41` | `expense.category` | Category | Categoría |
| `gpExpense42` | `expense.category_placeholder` | Select a category | Seleccione una categoría |
| `gpExpense43` | `expense.category_none` | Uncategorized | Sin categoría |
| `gpExpense44` | `expense.category.rent` | Rent | Arriendo |
| `gpExpense45` | `expense.category.equipment` | Equipment | Equipamiento |
| `gpExpense46` | `expense.category.electricity` | Electricity | Electricidad |
| `gpExpense47` | `expense.category.phone` | Phone | Teléfono |
| `gpExpense48` | `expense.category.services` | Other services | Otros servicios |
| `gpExpense49` | `expense.category.other` | Other | Otro |

`services` is deliberately worded "Other services" / "Otros servicios" against a bare
"Other" / "Otro", resolving the proposal's disambiguation risk.

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit (form) | `category` present; `required === true`; `placeholder === 'expense.category_placeholder'`; choices count `=== \count(Expense::CATEGORIES)` and contain `CATEGORY_RENT` | `tests/Form/ExpenseFormTest.php`, `TypeTestCase`, mirroring `testRecurrenceFieldIsOptionalAndRestrictedToMonth` |
| Integration (validation) | invalid `'gasoline'` → violation on `category`; **`null` category → no violation** (proves D2 and Rule 7's legacy carve-out); each of the six values valid | new `tests/Entity/ExpenseValidationTest.php`, `KernelTestCase` + `EntityValidationTestTrait`, `#[Group('integration')]`, per `ExpenseApprovalLevelValidationTest` |
| Integration (generator) | source `CATEGORY_RENT` → copy `CATEGORY_RENT`; source `null` → copy `null` | `tests/Expense/RecurringExpenseGeneratorTest.php`: add `->setCategory(Expense::CATEGORY_RENT)` to `createRecurringSource()`, assert in `testGeneratesNewDraftCopyWithTheSameAllocationSplit`, plus one null-source test |
| Controller | existing `ExpenseControllerTest` create/edit posts must add `category` or start failing — treat any red there as the required-field guard working | `tests/Controller/ExpenseControllerTest.php` |

## Threat Matrix

N/A — no routing-security, shell, subprocess, VCS/PR automation, executable-file
classification, or process-integration boundary. One additive nullable column, one
form field, template text, and one setter call.

## Migration / Rollout

Single additive migration, no data transformation, no downtime, deployable ahead of the
code. Rollback: `down()` drops the column; entity, form, template, translation, and
generator changes revert independently and in any order. Legacy rows read as
`Uncategorized` / `Sin categoría` until their next save.

## Review Workload Forecast

Roughly 120–150 authored changed lines across 12 files, concentrated in translations and
templates. `Decision needed before apply: No`. `Chained PRs recommended: No`.
`400-line budget risk: Low`.

## Open Questions

- [ ] None blocking. Details the proposal left open are now decided: `NotBlank` on the form (D2), explicit placeholder (D3), no index (D4), `expense.category_none` fallback (D5), `colspan` 5→6 in `index.html.twig`, `pending.html.twig` deliberately unchanged, and `Version20260813150000` as the migration id.
