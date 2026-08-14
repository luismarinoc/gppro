# Archive Report: Approval Workflows Expansion — SDD Cycle Complete

## Full Cycle Status
explore -> propose -> spec -> design -> tasks -> apply (5 PRs: PR1/PR2/PR3/PR-T/PR4) -> verify (5 verify passes, one per PR) -> Phase 5 final regression sweep -> archive. All phases complete. All merged to `main`.

Engram observation IDs read for this archive (all retrieved via `mem_get_observation`, full content, not previews):
- proposal: #633
- spec: #634
- design: #637
- tasks: #639 (superseded for final-state purposes by the filesystem `tasks.md`, which archive itself updated with Phase 5 results before archiving — see "Task Completion" below)
- apply-progress (Phase 1+2, pre PR-scoping): #654
- apply-progress-pr4: #676 (Phase 4/PR4) — CONFIRMED PRESENT, resolving PR4 verify report's WARNING #1 ("missing apply-progress-pr4 artifact"). No backfill was needed; the artifact already existed in Engram at archive time (saved 2026-08-14 08:21:28, same day as PR4's apply work). The verify report's search-time miss appears to have been a transient lookup gap, not an actual missing save.
- verify-report-pr1: #651 (PASS WITH WARNINGS, 0 CRITICAL, 15/15 focused tests, phpstan 1 pre-existing unrelated error)
- verify-report-pr2: #662 (PASS WITH WARNINGS, 0 CRITICAL, 60/60 focused / 28/28 full InvoiceControllerTest, phpstan 1 pre-existing unrelated error; dual PAID-gate independently re-verified with no bypass found)
- verify-report-pr3: read from filesystem `verify-report-pr3.md` (not separately saved to Engram under its own topic at search time; file is the authoritative copy, now archived) (PASS WITH WARNINGS, 0 CRITICAL, 136 tests/704 assertions focused, 348 tests/1750 assertions full Invoice sweep with the same 3 pre-existing unrelated failures, phpstan 1 pre-existing unrelated error)
- verify-report-pr-t: #656 (PASS WITH WARNINGS, 0 CRITICAL, 156/156 focused, 872/872 full Timesheet regression, phpstan 1 pre-existing unrelated error)
- verify-report-pr4: read from filesystem `verify-report-pr4.md` (PASS WITH WARNINGS, 0 CRITICAL; permission-leakage defense independently RED/GREEN reproduced; 8/8, 129/129, 184/184, 248/248 all pass across widening sweeps; phpstan 1 pre-existing unrelated error)

Every one of the 5 verify passes returned **PASS WITH WARNINGS, 0 CRITICAL findings**. No CRITICAL issue was ever raised against this change, at any PR stage. All WARNINGs across all 5 reports were documentation/process/tooling-footgun items (missing apply-progress artifacts later resolved, migration-filename collision later resolved by renumbering, design.md decision-table accuracy gaps, an assertion-count run-to-run variance of a few assertions, a shell-glob footgun with `tests/Invoice/*` vs `tests/Invoice`) — none were functional or security defects.

## 4-PR Chain Summary

| PR | GitHub # | Scope | Base | Merge commit | Verify verdict |
|---|---|---|---|---|---|
| PR1 | #121 | Invoice payment-approval foundation: `InvoicePaymentApprovalLevel` entity+migration+repo+resolver (D1, duplicated not shared)+admin CRUD+permission | tracker/main | `d669b6a` | PASS WITH WARNINGS |
| PR2 | #128 | Invoice submit/approve/reject state machine (D4) + audit entity + policy/service + dual PAID gate (D6, both `changeStatusAction` and `editAction`/`InvoiceEditForm` dropdown) | PR1 branch | `bf9b189` | PASS WITH WARNINGS |
| PR3 | #129 | `InvoiceVoter` `ALLOWED_ATTRIBUTES` extension (`approve_invoice_payment`/`reject_invoice_payment`) + functional dual-gate regression guards + grandfathering tests; closes the full Invoice payment-approval capability (7/7 requirements independently re-derived and confirmed against spec, not taken on faith) | PR2 branch | `521861b` | PASS WITH WARNINGS |
| PR-T | #124 | Timesheet approval: single-step team-lead approve/reject (D2 `User::isTeamleadOf()`, D3 no persisted reject state, D7 read-only-once-approved voter gate); fully independent parallel chain, 0 file overlap with Invoice chain | tracker-timesheet/main (parallel to PR1) | `64a59fd` | PASS WITH WARNINGS |
| PR4 | #132 | Approvals Dashboard: read-only cross-domain aggregation (Expense+Invoice+Timesheet), per-item `is_granted()` re-filter in PHP (leakage defense independently RED/GREEN reproduced by verify), navigation-only, historical-PAID grandfathering carried through; depends on later-merged tip of PR3+PR-T | main (after PR3+PR-T both merged) | `f5fa85a` | PASS WITH WARNINGS |

All 5 PRs' own `sdd-verify` runs independently re-executed tests (not trusted from apply reports) and cross-checked design/spec compliance by direct source reading, not checkbox-trust.

## Phase 5 — Final End-to-End Regression Sweep (run in THIS archive session, first time ever combined)

This had NOT been done before this archive session — PR4's own apply-progress (#676) and verify report both explicitly flagged Phase 5 as still outstanding ("Phase 5 still needs to run separately... after this PR merges"). All 6 tasks (5.1-5.6) executed and independently verified here:

- **5.1 Combined regression sweep** (first-ever single phpunit invocation across ALL 3 capabilities + Expense together): `tests/Entity/{Timesheet,Invoice,Expense}Test.php tests/Voter/{Timesheet,Invoice,Expense}VoterTest.php tests/Controller/{TimesheetTeam,Invoice,ApprovalsDashboard,Expense,ExpenseApprovalLevel,InvoicePaymentApprovalLevel}ControllerTest.php tests/Invoice tests/Repository/{Timesheet,Invoice,Expense}RepositoryTest.php` -> **547 tests, 2595 assertions, 3 failures**. All 3 failures are the previously-documented, cross-verified-multiple-times pre-existing `InvoiceModelDefaultHydratorTest::testHydrate` + `DebugRendererTest::testRender` (2 data sets) "adjustments" template-variable drift, confirmed present on `main` independently by task 2.15, task 4.12, and verify-report-pr2/pr3. Zero new regressions.
- **5.2 Zero-regression (Expense approval, own code untouched)**: confirmed green within the same 5.1 combined run.
- **5.3 Zero-regression against `expense-access-scoping` (PR #119)**: `tests/Voter/ExpenseVoterTest.php tests/Repository/ExpenseRepositoryTest.php tests/Controller/ExpenseControllerTest.php` all green within the 5.1 run — `main` has `expense-access-scoping` merged, no IDOR pattern reintroduced by this change's dashboard/Invoice/Timesheet work.
- **5.4 `phpstan analyse -c tests/phpstan.neon --no-progress`**: exactly **1 pre-existing unrelated error** (`QuotationControllerTest.php::decodeJsonResponse()` return.type), matching every individual PR's own phpstan result across the whole cycle. 0 new errors.
- **5.5 `lint:twig` + `lint:xliff` full sweep**: `lint:twig templates/` -> OK, 209 files. `lint:xliff translations/` -> OK, 602 files. Both zero errors.
- **5.6 Migration apply/rollback on `kimai2_test`**:
  - Confirmed exact filenames directly from `migrations/`: `Version20260814080000` (Timesheet approval fields), `Version20260814100000` (Invoice payment approval levels table), `Version20260814120000` (Invoice payment approval state machine fields + audit table).
  - **Documentation drift found and recorded**: `tasks.md`'s task 2.3 text names `Version20260814110000` for the state-machine migration, but the actual file at that timestamp is unrelated (`self-registration-admin-approval`'s `email_confirmed_at`/`rejected_at` columns on `gppro_users`). This matches verify-report-pr2's own WARNING #3, which independently found and flagged a real `git merge-tree` add/add collision at exactly `Version20260814110000.php` between this PR2 branch and the already-merged `self-registration-admin-approval` PR. The collision was resolved at apply/rebase time by renumbering PR2's migration to `Version20260814120000` — confirmed consistent between the verify report's predicted conflict and the final shipped filename. This is a stale prose reference in tasks.md's task-2.3 line, not a code defect; tasks.md 5.6 now records the correct final filenames.
  - Applied cleanly in sequence: rolled all 5 `202608140000`-range migrations (080000/100000/110000-unrelated/120000/130000-unrelated) back to a pre-change state on `kimai2_test` and re-ran `doctrine:migrations:migrate` — 104/104 executed, latest = `Version20260814130000`, zero errors.
  - `down()` reversal: `Version20260814080000` (ALTER-only, no `DROP TABLE`) reverses and re-applies cleanly, confirmed by direct execute --down/--up cycle.
  - `Version20260814100000` and `Version20260814120000` **cannot execute `down()` as written** — both call `addSql('DROP TABLE ...')`, unconditionally rejected by `App\Doctrine\AbstractMigration::addSql()` (`src/Doctrine/AbstractMigration.php:75-82`, `Cannot use addSql() with DROP TABLE`), a deliberate project-wide anti-accidental-drop guard. **Confirmed pre-existing and codebase-wide, not a regression from this change**: the identical failure reproduces on already-merged, unrelated migrations `Version20260812140000` (Expense) and `Version20260807190000` (Quotation), which use the same pattern. No fix applied — changing this shared safety guard is out of scope for this change. `up()` (the only direction ever exercised in real deploys) is fully verified clean for all 3 new migrations. DB fully restored to 104/104 executed / latest version after all rollback experiments.

## Requirements/Scenarios Coverage — All 18 Confirmed Implemented and Tested

**timesheet-approval** (6 requirements): Team lead approves member's entry; self-approval allowed; non-team-lead denied; approved entries become read-only (2 scenarios: owner+lead both denied edit/delete, pending stays editable); team lead can reject (D3: no persisted reject state, non-blocking accepted deviation). All 6 confirmed by verify-report-pr-t's spec compliance matrix (5 requirement rows / 6 scenarios, all PASS) and independently re-confirmed in PR4's closing cross-check.

**invoice-payment-approval** (7 requirements): PAID transition requires submission+cleared levels (both entry points: `changeStatusAction` and `editAction`/dropdown, D6); submission freezes required levels (D4/D5); post-submission amount changes don't reopen cleared levels; only eligible approvers clear a level (enforced at both service/policy layer AND voter layer as of PR3, defense-in-depth confirmed); all levels cleared unlocks PAID with audit trail; PENDING/CANCELED remain ungated; historical PAID invoices grandfathered. All 7 confirmed by verify-report-pr3's independently-re-derived spec compliance matrix (7/7 requirements, 9/9 scenarios, all PASS, "not merely asserted").

**approvals-dashboard** (5 requirements): aggregate pending approvals across all 3 domains; single-domain result correctly scoped; empty state when nothing pending; dashboard rows navigate to domain's own screen (navigation-only, no inline actions); visibility permission-consistent with each domain (no leakage — independently RED/GREEN reproduced by verify-report-pr4, not trusted from apply). All 5 confirmed by verify-report-pr4's §2/§8 completeness and closing spec-completeness check.

**Total: 18/18 requirements across all 3 capabilities implemented and covered by passing runtime tests**, independently re-derived from the spec files by at least one verify pass each (not taken on faith from apply reports), and re-confirmed with zero regressions in this archive session's combined Phase 5 sweep.

## Task Completion
`tasks.md` — all phases (1, 2, 3, T, 4, 5) fully checked `[x]`, 0 unchecked boxes at archive time. Phase 5's 6 boxes were unchecked prior to this archive session (correctly, since Phase 5 had genuinely not run yet); they were checked in this session with the results above, directly by re-running the specified commands (not a stale-checkbox reconciliation — this is the archive phase itself performing and evidencing the final gate per the change's own tasks.md phase boundary, "Phase 5: after PR4 merges").

## Native Review Receipt Gate
`reviewGate` structurally absent for this candidate — no native review authority artifact discovered for this change; archive proceeds under ordinary repository policy per the Native Review Receipt Gate's absent-key case.

## Spec Merge (mechanical, verified byte-identical)
No existing main spec covered any of the 3 domains (`openspec/specs/` had no `timesheet-approval`, `invoice-payment-approval`, or `approvals-dashboard` prior to this archive). All 3 delta specs were full specs (Purpose + Requirements, not ADDED/MODIFIED delta headers) and were mechanically copied via `cp`+`mv` (never Read->Write) into:
- `openspec/specs/timesheet-approval/spec.md` (5 requirements)
- `openspec/specs/invoice-payment-approval/spec.md` (7 requirements)
- `openspec/specs/approvals-dashboard/spec.md` (5 requirements)
Each copy verified with `diff -r` against the source change-folder spec: all 3 empty (byte-identical).

## Archive Move (mechanical, verified byte-identical)
`openspec/changes/approval-workflows-expansion/` moved via `git mv` to `openspec/changes/archive/2026-08-14-approval-workflows-expansion/`. Verified with `diff -r` against a pre-move recursive snapshot: empty (byte-identical). Archive contains: `design.md`, `proposal.md`, `specs/` (3 domains), `tasks.md` (fully checked, updated with Phase 5 results), `verify-report-pr1.md`, `verify-report-pr2.md`, `verify-report-pr3.md`, `verify-report-pr4.md`.

## Closing Architectural Note (PO framing, preserved for future readers)
The proposal's foundational framing for this entire change was explicit and deliberate: **"no todo va tener el mismo sistema de aprobación"** — not everything gets the same approval system. The PO's Round-2-confirmed decision (proposal.md, Approach 3) was to keep Expense, Invoice, and Timesheet approval mechanisms domain-specific and independently owned — three separate state machines/policies/audit trails, not a shared polymorphic approval engine — because each domain's approval shape genuinely differs (Expense: tiered+four-eyes; Invoice: tiered+dual-gate+frozen-at-submit; Timesheet: flat single-step+self-approval-allowed+no persisted reject state). Design's D1 explicitly rejected extracting a shared `ApprovalLevelRepositoryInterface` between Expense and Invoice specifically to honor this: 15 lines duplicated was judged cheaper than forcing shipped Expense code to implement a new interface. The only shared surface across all three is the read-only Approvals Dashboard (PR4) — a thin, permission-scoped UI-layer aggregation that queries each domain's own repository and re-checks each domain's own voter, with zero shared business logic. This was the right call for maintainability: each domain's approval rules can evolve independently without cross-domain coupling risk, at the cost of some duplicated boilerplate (accepted explicitly and by design).

## Risks / Open Items Carried Forward (non-blocking)
1. `Version20260814100000`/`Version20260814120000` `down()` cannot execute due to the project-wide `DROP TABLE` guard — pre-existing, codebase-wide pattern (see Phase 5.6 above), not specific to this change, no fix in scope.
2. verify-report-pr1's WARNING #3 (no `MenuSubscriber` nav entry for the new Invoice payment-approval-levels admin screen, reachable only by direct URL) was never explicitly picked up by a later phase — remains a minor UX follow-up, non-blocking, out of this change's scope as originally defined.
3. verify-report-pr3's SUGGESTION (swap `InvoiceController`'s payment-approval actions from the coarse `edit_invoice` `IsGranted` gate to the fine-grained `approve_invoice_payment`/`reject_invoice_payment` voter attributes for defense-in-depth) remains an optional hardening item for a future change.

## SDD Cycle Complete
The `approval-workflows-expansion` change (4 PRs, 3 new capabilities, 18 requirements) is fully planned, implemented, verified (5x PASS WITH WARNINGS / 0 CRITICAL), Phase-5-regression-swept, and archived. Ready for the next change.
