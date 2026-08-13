# Tasks: Expense Approval By Person

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~180–250 (8 files: 1 entity, 1 migration, 1 policy, 1 form, 1 template, 2 translation files, 4 test files) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

Single additive nullable column plus one policy branch, one form field, one template column, two translation keys — smaller in file count than the precedent `expense-category` change (no generator, no `pending.html.twig`), same "Low" class.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Full `expense-approval-by-person` change: entity+migration, policy, form, template, translations, tests | PR 1 (single) | `phpunit tests/Entity/ExpenseApprovalLevelTest.php tests/Expense/ExpenseApprovalPolicyTest.php tests/Form/ExpenseApprovalLevelFormTest.php tests/Controller/ExpenseApprovalLevelControllerTest.php` | `doctrine:migrations:migrate` on test DB, then manual create/edit level with a named approver in staging | revert migration `down()` (drops FK, index, column) + revert entity/policy/form/template/translation files |

## Phase 1: Entity + Migration

- [x] 1.1 RED: extend `tests/Entity/ExpenseApprovalLevelTest.php` — `getApproverUser()` null by default; setter accepts a `User` and accepts `null`. Traces: "Level saved with and without a named approver".
- [x] 1.2 GREEN: `src/Entity/ExpenseApprovalLevel.php` — add `#[ORM\ManyToOne] ?User $approverUser` (`approver_user_id`, `onDelete: 'SET NULL'`) after `$requiredRole`, plus nullable `getApproverUser()`/`setApproverUser(?User)`, per design's exact interface block. Run 1.1 green.
- [x] 1.3 Create `migrations/Version20260813160000.php` (`final class`, description `'Add an optional named approver to expense approval levels'`) — `up()` adds `approver_user_id INT DEFAULT NULL` + `IDX_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER` + `FK_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER ... ON DELETE SET NULL`; `down()` reverses in order (drop FK, drop index, drop column).
- [x] 1.4 Verify: run the migration against the test DB, confirm `gppro_expense_approval_levels.approver_user_id` exists, is nullable, and is indexed.

## Phase 2: `canDecide()` Policy Change (security-relevant — D2 invariant)

- [x] 2.1 RED (MANDATORY): extend `tests/Expense/ExpenseApprovalPolicyTest.php` — a level names user D as `approverUser`; the expense's creator is also user D; assert `canDecide()` denies. Traces: "Named approver who is the creator is still denied".
- [x] 2.2 RED (MANDATORY): extend the same file — user B already approved level 1 and is also named `approverUser` on level 2 of the same expense; assert `canDecide()` denies level 2. Traces: "Same approver cannot clear two levels" (named-approver variant; the explicit RED-first guard for the D2 branch-ordering invariant).
- [x] 2.3 RED: a level requires ROLE_TEAMLEAD and names user C, who does not hold the role; assert user C clears it. Traces: "Named approver clears a level without holding the role".
- [x] 2.4 RED: a level requires ROLE_TEAMLEAD and names user C; assert a different ROLE_TEAMLEAD holder still clears it. Traces: "Role holder clears a level that names a different approver".
- [x] 2.5 RED: an expense pending at a level naming user C; reassign `approverUser` to user E between two `canDecide()` calls (no snapshot); assert E now clears it and C alone (absent the role) no longer can. Traces: "Reassigning the named approver applies live to a pending level".
- [x] 2.6 RED: integration-style — persist a level naming user C and requiring ROLE_TEAMLEAD, delete user C via the EM, `refresh()` the level; assert `getApproverUser()` is `null` and a ROLE_TEAMLEAD holder still clears it. Traces: "Disabled or removed named approver falls back to role-based decision". (Placed in `tests/Expense/ExpenseApprovalServiceTest.php` — the existing real-EM/integration harness that already wires `ExpenseApprovalPolicy`; `ExpenseApprovalPolicyTest.php` stayed pure-unit with exactly the 5 new tests design's File Changes table calls for.)
- [x] 2.7 GREEN: `src/Expense/ExpenseApprovalPolicy.php` `canDecide()` — insert the one positive branch (`$level->getApproverUser() === $user` → `true`) strictly below the existing `null === $level`, creator, and already-approved gates and the `isSuperAdmin()` check, above the `hasRole()` return, per design's exact before/after. Run 2.1–2.6 green.

## Phase 3: Form

- [x] 3.1 RED: extend `tests/Form/ExpenseApprovalLevelFormTest.php` — assert `approverUser` field is present and `required === false`. Traces: "Level saved with and without a named approver". (Presence-only assertion, matching the `requiredRole`/`UserType` unmockable-constructor caveat already documented in this file; `required === false` is covered by the controller integration test in 3.3 instead of `$form->get()`, which would eagerly instantiate `UserType` and fail in `TypeTestCase`.)
- [x] 3.2 GREEN: `src/Form/ExpenseApprovalLevelForm.php` — add `use App\Form\Type\UserType;`; add `approverUser` field (`UserType::class`, `required: false`, `label`/`help` translation keys, `include_users` set to the level's current `approverUser` when non-null per D6). Run 3.1 green.
- [x] 3.3 RED: extend `tests/Controller/ExpenseApprovalLevelControllerTest.php` — POST a level create/edit with `approverUser` set (succeeds, persists), and separately without it (succeeds, behaves as before). Traces: "Level saved with and without a named approver". (The "without it" case was already covered by the pre-existing `testSuperAdminCanListAndCreateApprovalLevel`.)
- [x] 3.4 Verify 3.3 green with no controller change (`isMonotonic()` reads only `level`/`minAmount`, A7); existing POSTs that omit `approverUser` stay green.

## Phase 4: Templates

- [x] 4.1 `templates/expense_approval_level/index.html.twig` — insert `<th>{{ 'expense_approval_level.approver_user'|trans }}</th>` after the `required_role` header.
- [x] 4.2 Same file — insert `<td>` rendering `widgets.label_user(level.approverUser)` when set, else a muted `&mdash;`, after the role badge `<td>`.
- [x] 4.3 Same file — change the empty-state row's `colspan="4"` to `colspan="5"`.
- [x] 4.4 Verify: `templates/expense_approval_level/edit.html.twig` needs NO change — `form_widget(form)` already renders the new field and its `help` text generically (D5); confirm via `lint:twig` only.

## Phase 5: Translations

- [x] 5.1 `translations/messages.en.xlf` — insert `gpExpense50` (`expense_approval_level.approver_user` → "Named approver") and `gpExpense51` (`.help` → the OR-semantics help text) after `gpExpense49`.
- [x] 5.2 `translations/messages.es.xlf` — same ids/resnames, `xml:space="preserve"`, `state="translated"`: "Aprobador designado" / "Opcional. Esta persona puede aprobar el nivel además de cualquier usuario con el rol requerido. Déjalo vacío para usar solo el rol."

## Phase 6: Full Regression + Verification

- [x] 6.1 Run `phpunit tests/Entity/ExpenseApprovalLevelTest.php tests/Expense/ExpenseApprovalPolicyTest.php tests/Form/ExpenseApprovalLevelFormTest.php tests/Controller/ExpenseApprovalLevelControllerTest.php` — all green. (Also ran `tests/Expense/ExpenseApprovalServiceTest.php` and `tests/Repository/ExpenseApprovalLevelRepositoryTest.php` together: 35/35 tests, 100 assertions, OK.)
- [x] 6.2 Confirm unaffected existing scenarios stay green: "Correct-role user clears a role-only level", "Creator cannot approve own expense", "SUPER_ADMIN clears any level", "Final level completes approval", "Same approver cannot clear two levels" (base case), "Unauthorized user cannot edit levels", "Non-monotonic threshold is rejected", "Last remaining level cannot be deleted". (All present in the 35/35 run above — no regressions.)
- [x] 6.3 Run `lint:twig` on `index.html.twig`/`edit.html.twig` and `lint:xliff` on both translation files. (Both `[OK]`.)
- [x] 6.4 Manual/staging: no staging environment in this sandbox — automated equivalent only (6.1–6.3); true visual confirmation of both locales deferred to a human reviewer with browser access.

## Verification: phpstan

`vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` — 1 error found, and it is the pre-existing, unrelated `Controller/QuotationControllerTest.php::decodeJsonResponse()` return-type error. No new phpstan errors were introduced by this change.
