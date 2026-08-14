# Tasks: Approval Workflows Expansion

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~950-1150 (3 new entities, 2 new repos, 1 new resolver, 1 new policy, 1 new service, 2 new controllers, 1 new form, 3 admin templates, 1 dashboard template, 2 modified controllers, 2 modified voters, 2 modified repos, 3 migrations, full test suite) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 -> PR2 -> PR3 (Invoice chain, sequential) `\|\|` PR-T (Timesheet, parallel to PR1) -> PR4 (Dashboard, base = later of PR3/PR-T) |
| Delivery strategy | ask-on-risk |
| Chain strategy | feature-branch-chain |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

Two independent chains converge before the dashboard: Invoice (PR1->PR2->PR3, D1/D4/D6 state-machine + dual PAID-gate complexity) and Timesheet (PR-T, disjoint files, D2/D3/D7). PR4 depends on the LAST-merged tip of both chains, not on the tracker directly.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | `InvoicePaymentApprovalLevel` entity+migration+repo+resolver(D1)+admin CRUD+permission | PR1 (base: tracker) | `phpunit tests/Entity/InvoicePaymentApprovalLevelTest.php tests/Invoice/InvoicePaymentApprovalLevelResolverTest.php tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` | manual: `/admin/invoice/payment-approval-levels/` CRUD in staging | revert 1 migration + delete entity/repo/resolver/controller/form/templates; zero coupling to `Invoice` |
| 2 | Invoice state fields(D4) + audit entity + policy/service + submit/approve/reject actions + dual PAID gate(D6) | PR2 (base: PR1 branch) | `phpunit tests/Entity/InvoiceTest.php tests/Invoice/InvoicePaymentApprovalPolicyTest.php tests/Invoice/InvoicePaymentApprovalServiceTest.php tests/Controller/InvoiceControllerTest.php` | manual: submit invoice, approve levels, confirm PAID blocked via BOTH `changeStatusAction` and edit-form dropdown until cleared | revert 1 migration + Invoice/InvoiceController/InvoiceService diff; PR1 tables stay usable standalone |
| 3 | `InvoiceVoter` `ALLOWED_ATTRIBUTES` extension + functional dual-gate + grandfathering tests | PR3 (base: PR2 branch) | `phpunit tests/Voter/InvoiceVoterTest.php tests/Controller/InvoiceControllerTest.php` | manual: pre-existing PAID invoice re-save (comment edit) is never blocked | revert `InvoiceVoter` diff only; PR1/PR2 remain functional |
| T | Timesheet entity fields(fields only) + voter(D2/D7) + controller actions + migration | PR-T (base: tracker, parallel to PR1) | `phpunit tests/Entity/TimesheetTest.php tests/Voter/TimesheetVoterTest.php tests/Controller/TimesheetTeamControllerTest.php` | manual: team lead approves own+member hours in staging, confirm approved row un-clickable | revert 1 migration + Timesheet/TimesheetVoter/TimesheetTeamController diff; fully independent of Invoice |
| 4 | Dashboard aggregation controller + 2 new repo queries + template | PR4 (base: later of PR3/PR-T tip, merges last) | `phpunit tests/Controller/ApprovalsDashboardControllerTest.php tests/Repository/TimesheetRepositoryTest.php tests/Repository/InvoiceRepositoryTest.php` | manual: `/approvals` shows correct per-user rows across all 3 domains, empty state, no leakage | revert controller/template/2 repo methods; no schema, no dependency on removing it breaks PR1-3/T |

## Phase 1: Invoice — Approval-Level Foundation (PR1)

- [x] 1.1 RED `tests/Entity/InvoicePaymentApprovalLevelTest.php` — level ordering/validation mirrors `ExpenseApprovalLevelTest`, `minAmount` accepts `float` (D5).
- [x] 1.2 GREEN `src/Entity/InvoicePaymentApprovalLevel.php` — mirrors `ExpenseApprovalLevel` shape, `minAmount` `Types::FLOAT` (D5).
- [x] 1.3 `migrations/Version20260814100000.php` — CREATE `gppro_invoice_payment_approval_levels` (mirrors `gppro_expense_approval_levels`).
- [x] 1.4 `src/Repository/InvoicePaymentApprovalLevelRepository.php` — mirrors `ExpenseApprovalLevelRepository`.
- [x] 1.5 RED `tests/Invoice/InvoicePaymentApprovalLevelResolverTest.php` — `resolve()`/`requiredLevelsFor()` against float amounts, mirrors `ApprovalLevelResolverTest`.
- [x] 1.6 GREEN `src/Invoice/InvoicePaymentApprovalLevelResolver.php` — duplicate ~15-line algorithm (D1), constructed with `InvoicePaymentApprovalLevelRepository`; do NOT touch `ExpenseApprovalLevelRepository`.
- [x] 1.7 RED `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` — monotonic-ladder validation, level-1-cannot-delete, mirrors `ExpenseApprovalLevelControllerTest`.
- [x] 1.8 GREEN `src/Controller/InvoicePaymentApprovalLevelController.php` + `src/Form/InvoicePaymentApprovalLevelForm.php` — mirror `ExpenseApprovalLevelController`/`ExpenseApprovalLevelForm` exactly; routes `admin_invoice_payment_approval_level_{list,create,edit,delete}`. Also registered `App\Repository\InvoicePaymentApprovalLevelRepository` as a DI service in `config/services.yaml` (factory: `doctrine.orm.entity_manager::getRepository`), mirroring `ExpenseApprovalLevelRepository`'s existing entry — required because `EntityRepository` subclasses are not auto-registered by Symfony's autowiring (discovered via a 500/`RuntimeException: Cannot autowire service` during GREEN).
- [x] 1.9 `templates/invoice_payment_approval_level/{index,edit}.html.twig` — copy structure from `templates/expense_approval_level/*`.
- [x] 1.10 `config/packages/gppro.yaml` — register `manage_invoice_payment_approval_levels` (`ROLE_SUPER_ADMIN`), alongside `manage_expense_approval_levels`.
- [x] 1.11 Run `phpunit tests/Entity/InvoicePaymentApprovalLevelTest.php tests/Invoice/InvoicePaymentApprovalLevelResolverTest.php tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`, `phpstan analyse -c tests/phpstan.neon`, `lint:twig`, `lint:xliff`; open PR1 (base: tracker).

## Phase 2: Invoice — Submit/Approve/Reject State Machine (PR2, base: PR1 branch)

- [ ] 2.1 RED `tests/Entity/InvoiceTest.php` — `submitForPaymentApproval()` freezes levels(decision 5); `clearPaymentLevel()` order enforcement; `rejectPaymentApproval()` discards progress; amount change post-submission does not reopen cleared levels.
- [ ] 2.2 GREEN `src/Entity/Invoice.php` — add `paymentApprovalStatus`/`paymentRequiredLevels`/`paymentCurrentLevel` (D4) + `isPaymentApproved()`/`nextPendingPaymentLevel()`/`submitForPaymentApproval()`/`clearPaymentLevel()`/`rejectPaymentApproval()` per design's Interfaces/Contracts block.
- [ ] 2.3 `migrations/VersionYYYYMMDDHHMMSS_invoice_payment_approval.php` — CREATE `gppro_invoice_payment_approvals` (audit, mirrors `gppro_expense_approvals`) + ALTER `gppro_invoices` add nullable `payment_approval_status`, nullable `payment_required_levels`, `payment_current_level` default 0.
- [ ] 2.4 `src/Entity/InvoicePaymentApproval.php` + `src/Repository/InvoicePaymentApprovalRepository.php` — mirror `ExpenseApproval`/`ExpenseApprovalRepository`.
- [ ] 2.5 RED `tests/Invoice/InvoicePaymentApprovalPolicyTest.php` — eligible role/named-approver clears; ineligible denied; already-cleared level not re-approvable. Mirrors `ExpenseApprovalPolicyTest`.
- [ ] 2.6 GREEN `src/Invoice/InvoicePaymentApprovalPolicy.php` — mirrors `ExpenseApprovalPolicy` (`canApprove`/`canReject`).
- [ ] 2.7 RED `tests/Invoice/InvoicePaymentApprovalServiceTest.php` — `submit()` resolves+freezes via resolver; `approve()`/`reject()` persist audit rows.
- [ ] 2.8 GREEN `src/Invoice/InvoicePaymentApprovalService.php` — mirrors `ExpenseApprovalService`, adds `submit(Invoice)`.
- [ ] 2.9 RED `tests/Controller/InvoiceControllerTest.php` — new routes: `submitPaymentApprovalAction`/`approvePaymentAction`/`rejectPaymentAction` reachable and correctly gated.
- [ ] 2.10 GREEN `src/Controller/InvoiceController.php` — add the 3 new actions + routes `invoice_{submit_payment_approval,approve_payment,reject_payment}`.
- [ ] 2.11 RED `tests/Controller/InvoiceControllerTest.php` — dual PAID-gate cases (D6): unsubmitted invoice via `changeStatusAction` denied; unsubmitted invoice via `editAction`/`InvoiceEditForm` status dropdown denied; both entry points explicitly.
- [ ] 2.12 GREEN `src/Controller/InvoiceController.php::changeStatusAction()` — inside `if ($status === Invoice::STATUS_PAID)`, before any setter, check `!$invoice->isPaid() && !$invoice->isPaymentApproved()` -> flash error, redirect, skip mutation (D6 point 1).
- [ ] 2.13 GREEN `src/Controller/InvoiceController.php::editAction()` — capture `$wasPaid` before `handleRequest()`; after valid submit, if `$invoice->isPaid() && !$wasPaid && !$invoice->isPaymentApproved()` -> flash error, re-render, skip `saveInvoice()` (D6 point 2, closes the `InvoiceEditForm` dropdown bypass).
- [ ] 2.14 RED same file — already-PAID invoice comment-only re-save (grandfathered, decision 8) is NOT blocked by either gate.
- [ ] 2.15 Run `phpunit tests/Entity/InvoiceTest.php tests/Invoice/InvoicePaymentApprovalPolicyTest.php tests/Invoice/InvoicePaymentApprovalServiceTest.php tests/Controller/InvoiceControllerTest.php`, `phpstan analyse`, `lint:twig`; open PR2 (base: PR1 branch, retarget/rebase if diff shows PR1 files).

## Phase 3: Invoice — Voter Extension + Functional Closure (PR3, base: PR2 branch)

- [ ] 3.1 RED `tests/Voter/InvoiceVoterTest.php` — `approve_invoice_payment`/`reject_invoice_payment` attributes: eligible approver granted, ineligible denied, wired through `InvoicePaymentApprovalPolicy`.
- [ ] 3.2 GREEN `src/Voter/InvoiceVoter.php` — extend existing `ALLOWED_ATTRIBUTES` array with `approve_invoice_payment`/`reject_invoice_payment` (confirmed: no new voter class per design's Open Questions resolution); inject `InvoicePaymentApprovalPolicy`.
- [ ] 3.3 RED `tests/Controller/InvoiceControllerTest.php` — PENDING/CANCELED transitions remain ungated (regression guard scenario).
- [ ] 3.4 RED same file — historical PAID invoice (pre-change, `paymentApprovalStatus = null`) shows no "unapproved" flag in any new UI touchpoint.
- [ ] 3.5 Run `phpunit tests/Voter/InvoiceVoterTest.php tests/Controller/InvoiceControllerTest.php`, full Invoice test surface (`*Invoice*`), `phpstan analyse`; open PR3 (base: PR2 branch).

## Phase T: Timesheet Approval (PR-T, base: tracker, parallel to PR1)

> Ordering note: mirrors the `isEditable()`/row-click-to-edit gate PATTERN from `templates/expense/index.html.twig` + `ExpenseVoter` (`row-click-edit-consistency`, PR #120 — check current `main` merge state before starting; not a hard blocker since only the pattern, not shared code, is mirrored). Design D7 confirms `templates/timesheet/index.html.twig:88` already gates row-click via `is_granted('edit', entry)` — no template change needed regardless of PR #120's merge status.

- [ ] T.1 RED `tests/Entity/TimesheetTest.php` — `isApproved()`/`approve(User)` state transitions, mirrors `ExpenseTest`.
- [ ] T.2 GREEN `src/Entity/Timesheet.php` — add `approvedBy` (?User, `SET NULL`), `approvedAt` (?DateTimeImmutable), `isApproved()`, `approve(User)` per design's Interfaces/Contracts block.
- [ ] T.3 `migrations/VersionYYYYMMDDHHMMSS_timesheet_approval.php` — ALTER `gppro_timesheet` add nullable `approved_by_id` (FK, `SET NULL`), nullable `approved_at`.
- [ ] T.4 RED `tests/Voter/TimesheetVoterTest.php` — team lead of project grants `approve`; non-lead denied; self-approval by lead granted (decision 4, no creator-exclusion).
- [ ] T.5 GREEN `src/Voter/TimesheetVoter.php` — add `approve`/`reject` attributes using `User::isTeamleadOf(Team $team)` (D2) looping `$timesheet->getProject()?->getTeams()`. Do NOT call the private `RolePermissionManager::checkTeamLeadAccess()` and do NOT reuse `checkTeamAccessTimesheet()` (short-circuits true for owner, breaks self-approval-by-lead-only semantics per D2 rationale).
- [ ] T.6 RED same file — approved entry: edit/delete denied for owner AND lead (D7 `isAllowedApproved()` guard).
- [ ] T.7 GREEN `src/Voter/TimesheetVoter.php` — add private `isAllowedApproved()` check (same shape as `isAllowedExported()`/`isAllowedInLockdown()`) to `canEdit()`/`canDelete()`, returns `false` when `isApproved()` true.
- [ ] T.8 RED `tests/Controller/TimesheetTeamControllerTest.php` — `approveAction` sets approvedBy/approvedAt; `rejectAction` is no-op/flash only, entry stays unapproved/editable (D3, no persisted reject state).
- [ ] T.9 GREEN `src/Controller/TimesheetTeamController.php` — add `approveAction`/`rejectAction`, routes `admin_timesheet_approve`/`admin_timesheet_reject`, `IsGranted('approve_timesheet', 'entry')`.
- [ ] T.10 RED functional `tests/Controller/TimesheetTeamControllerTest.php` — approved entry: edit/delete routes return 403 for owner; `is_granted('edit')` false in template context (row-click-to-edit gate closed via existing template wiring, D7).
- [ ] T.11 `templates/timesheet/team_index.html.twig` (or equivalent) — add approve/reject buttons per row, gated `is_granted('approve', entry)`.
- [ ] T.12 Run `phpunit tests/Entity/TimesheetTest.php tests/Voter/TimesheetVoterTest.php tests/Controller/TimesheetTeamControllerTest.php`, full Timesheet test surface (`*Timesheet*`), `phpstan analyse`, `lint:twig`; open PR-T (base: tracker).

## Phase 4: Approvals Dashboard (PR4, base: later-merged tip of PR3/PR-T)

> Hard dependency: requires PR3 (Invoice payment-approval query surface) and PR-T (Timesheet query surface) both merged. Do not start before both land.

- [ ] 4.1 RED `tests/Repository/TimesheetRepositoryTest.php` — `findPendingApprovalForUser(User)` returns only entries where user `isTeamleadOf()` the project's team(s), excludes already-approved entries.
- [ ] 4.2 GREEN `src/Repository/TimesheetRepository.php` — add `findPendingApprovalForUser(User)`.
- [ ] 4.3 RED `tests/Repository/InvoiceRepositoryTest.php` — `findPendingPaymentApprovalForUser(User)` returns submitted, non-fully-cleared invoices (creator-exclusion only at repo layer per design's flagged gap — NOT approver-eligibility filtering).
- [ ] 4.4 GREEN `src/Repository/InvoiceRepository.php` — add `findPendingPaymentApprovalForUser(User)`.
- [ ] 4.5 RED `tests/Controller/ApprovalsDashboardControllerTest.php` — user with pending items in all 3 domains sees all 3; user with only-Timesheet sees only Timesheet rows; empty state when nothing pending.
- [ ] 4.6 RED same file — permission-leakage guard: raw repository results are NOT trusted as-is (design's explicit flag: `findPendingForUser()`-style queries only exclude the creator, they do not filter by approver eligibility) — controller MUST additionally filter each result set in PHP via `is_granted()` per item (`approve_expense`/`approve_timesheet`/`approve_invoice_payment`) before merge.
- [ ] 4.7 RED same file — user with `approve_expense` but not `approve_invoice_payment` sees Expense rows only, no Invoice/Timesheet leakage.
- [ ] 4.8 RED same file — dashboard row navigates to the domain's own approve/reject screen; no inline approve/reject controls rendered (navigation-only, decision 7).
- [ ] 4.9 RED same file — historical PAID invoice never appears as "unapproved" (decision 8, grandfathering carried into dashboard).
- [ ] 4.10 GREEN `src/Controller/ApprovalsDashboardController.php` — `indexAction`: query Expense (`findPendingForUser`, existing) + Timesheet + Invoice, filter each with `is_granted()` per item, merge, sort by date; route `approvals_dashboard` -> `/approvals`.
- [ ] 4.11 `templates/approvals_dashboard/index.html.twig` — single new template, 3 sections, each row links to its own domain screen (not composed partials, per design's file-changes note).
- [ ] 4.12 Run `phpunit tests/Controller/ApprovalsDashboardControllerTest.php tests/Repository/TimesheetRepositoryTest.php tests/Repository/InvoiceRepositoryTest.php`, `phpstan analyse`, `lint:twig`; open PR4 (base: later of PR3/PR-T tip).

## Phase 5: Final End-to-End Sweep (after PR4 merges, all 3 capabilities together)

- [ ] 5.1 Run full approval-domain test surface together: `phpunit tests/Entity/{Timesheet,Invoice,Expense}Test.php tests/Voter/{Timesheet,Invoice,Expense}VoterTest.php tests/Controller/{TimesheetTeam,Invoice,ApprovalsDashboard,Expense,ExpenseApprovalLevel,InvoicePaymentApprovalLevel}ControllerTest.php tests/Invoice/*Test.php tests/Repository/{Timesheet,Invoice,Expense}RepositoryTest.php`.
- [ ] 5.2 Zero-regression check: `ExpenseApprovalLevelControllerTest`, `ExpenseVoterTest`, `ExpenseControllerTest` unchanged/green — confirm no accidental touch to shipped Expense approval code (out of scope per proposal).
- [ ] 5.3 Zero-regression check against `expense-access-scoping` (PR #119): re-run `tests/Voter/ExpenseVoterTest.php tests/Repository/ExpenseRepositoryTest.php tests/Controller/ExpenseControllerTest.php` — confirm current `main` state (merged or not) and that this change's dashboard/Invoice/Timesheet work does not reintroduce the IDOR pattern that fix closed.
- [ ] 5.4 `phpstan analyse -c tests/phpstan.neon --no-progress` across the whole diff — zero new errors.
- [ ] 5.5 `lint:twig` + `lint:xliff` full sweep across all new/modified templates and translation files.
- [ ] 5.6 Confirm migrations apply cleanly in sequence on a fresh test DB (`doctrine:migrations:migrate`) and each `down()` reverses cleanly.
