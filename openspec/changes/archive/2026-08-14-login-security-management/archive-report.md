# Archive Report: Login Security Management

**Change**: login-security-management
**Mode**: hybrid (Engram + OpenSpec)
**Archived**: 2026-08-14
**Archive location**: `openspec/changes/archive/2026-08-14-login-security-management/`
**Archive commit**: `9c965ae` on `main` (not pushed; orchestrator pushes after review)

## Full SDD Cycle Status

| Phase | Status | Evidence |
|---|---|---|
| Explore | Done | `sdd/login-system-improvements/explore` (referenced by proposal #640) |
| Propose | Done | `sdd/login-security-management/proposal` — Engram #640 |
| Spec | Done | `sdd/login-security-management/spec` — Engram #641 (4 full new capability specs, no existing spec covered auth/login) |
| Design | Done | `sdd/login-security-management/design` — Engram #642 (5 architecture decisions) |
| Tasks | Done | `sdd/login-security-management/tasks` — Engram #644, filesystem `tasks.md` (33/33 tasks checked at close, 0 unchecked) |
| Apply | Done (4 PRs) | `apply-progress` #648, `apply-progress-pr4` #668, plus in-repo commits per PR |
| Verify | Done (4 PRs, each independently PASS/PASS WITH WARNINGS, 0 CRITICAL) | `verify-report-pr1` #652, `verify-report-pr2` #655, `verify-report-pr3` #661, `verify-report-pr4` #671 |
| Archive | Done (this report) | This document |

## 4-PR Chain Summary

Chain strategy: `stacked-to-main` (each capability independently revertible, zero cross-capability dependency per design). All 4 PRs merged to `main`:

| PR | GitHub # | Capability | Merge commit | Verify verdict |
|---|---|---|---|---|
| PR1 | #122 | password-policy — `Assert\Regex` (letter+digit) on `User::$plainPassword`, alongside existing `Assert\Length(min:8,max:60)` | `4e0eda5` | PASS WITH WARNINGS (verify-report-pr1, Engram #652) — 3 non-blocking warnings, 0 CRITICAL |
| PR2 | #123 | remember-me-policy — `always_remember_me: true → false`, restored theme's opt-in `_remember_me` checkbox at `login.html.twig:101` | `5236a5d` | PASS (verify-report-pr2, Engram #655) — 0 CRITICAL, 1 non-blocking infra WARNING (shared test-DB contention, not a code defect) |
| PR3 | #127 | admin-user-quick-actions — `forcePasswordResetAction`/`revokeRememberMeAction` on `UserController`, gated by existing `UserVoter('password')`, submenu wiring via `UserSubscriber::onActions()` | `27522fa` | PASS WITH WARNINGS (verify-report-pr3, Engram #661) — 0 CRITICAL; rebase-before-merge and frontend click-to-POST wiring flagged as scoped, non-blocking |
| PR4 | #130 | login-audit-trail — new `LoginAttempt` entity + migration `Version20260814130000` + `LoginAuditSubscriber` + `ROLE_SUPER_ADMIN`-only `LoginAuditController` list | `ccb232a` | PASS WITH WARNINGS (verify-report-pr4, Engram #671) — 0 CRITICAL; 2 non-blocking WARNINGs (PR4 diff size over 400-line guard; Phase 1 checkbox gap, fixed at archive — see below) |

Each PR's own `sdd-verify` was independently re-executed against source and test evidence (not checkbox trust), and each closed 0 CRITICAL findings.

## Phase-1-Checkbox-Gap Fix (documentation-accuracy reconciliation)

**Finding**: PR1 (#122, password-policy) was genuinely merged to `main` and independently verified in full (`verify-report-pr1`, Engram #652, confirms all 4 Phase 1 tasks complete against source: RED tests present at `tests/Entity/UserTest.php:468-513`, GREEN `Assert\Regex` present at `User.php:216` byte-for-byte matching design decision #3, 5 validation groups confirmed identical, phpunit/phpstan independently re-run). However, unlike PR2/PR3/PR4 — each of which carries its own dedicated "check off Phase N" commit — no such commit for Phase 1 ever landed on `main`. `verify-report-pr4` (Engram #671) independently flagged this exact gap as WARNING #2 and recommended the cleanup before archive.

**Fix applied** (this archive phase, exceptional reconciliation per the Task Completion Gate's stale-checkbox provision, backed by verify-report-pr1's proof of completion): marked all 4 Phase 1 checkboxes (`1.1`-`1.4`) `[x]` in `tasks.md`, with an inline reconciliation note recording the reason and citing verify-report-pr1/verify-report-pr4 as evidence. Pure documentation-accuracy fix — zero functional/code change, committed alongside the archive move in commit `9c965ae`.

## Phase 5: Final Regression Sweep (executed live, this session, on `main` @ `ccb232a` before archive commit)

All 5 Phase 5 tasks (`tasks.md` 5.1-5.5) executed for real and marked `[x]` with actual results:

- **5.1** — Full combined suite (`tests/Controller/Security/ tests/EventSubscriber/ tests/EventSubscriber/Actions/UserSubscriberTest.php tests/Voter/UserVoterTest.php tests/Entity/UserTest.php tests/Entity/LoginAttemptTest.php tests/Controller/UserControllerTest.php tests/Controller/LoginAuditControllerTest.php`) run together (not per-PR subsets): **OK, 327 tests, 1556 assertions**, exit 0. Matches `verify-report-pr4`'s own regression numbers exactly.
- **5.2** — `tests/EventSubscriber/LastLoginSubscriberTest.php` isolation: **OK, 3 tests, 12 assertions** — confirmed genuinely untouched/green, zero regression on pre-existing last-login tracking.
- **5.3** — `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress`: exactly **1 pre-existing, unrelated error** (`Controller/QuotationControllerTest.php::decodeJsonResponse()`, `return.type` — return type mismatch, `array<string,mixed>` vs `array<mixed,mixed>`), matching the repo's documented baseline (same finding referenced in `expense-approval-by-person` and `expense-access-scoping` verify-reports).
- **5.4** — `bin/console lint:twig templates/security/login.html.twig templates/login_audit/index.html.twig`: **OK, 2/2 valid**. `bin/console lint:xliff translations/`: **OK, 602/602 valid** (covers `messages.en.xlf`/`messages.es.xlf` with the new `login_audit.*` keys).
- **5.5** — Migration reversibility on the `kimai2_test` DB: `Version20260814130000` was already at head (104/104 executed, 0 new). Executed `doctrine:migrations:execute --down` (2 SQL queries, OK) then `--up` (2 SQL queries, OK) to prove clean reversibility — status returned to head both times, confirming the apply-phase's double-drop-FK bug fix (`down()` uses only `$schema->dropTable()`) holds.

**Regression numbers are consistent across the whole chain**: `verify-report-pr4` (Engram #671) independently ran this identical combined-suite command post-rebase and got the same 327 tests / 1556 assertions — this session's live re-run on the final merged `main` state reproduces that result exactly, with zero drift.

## Requirements/Scenarios Coverage — 12/12 requirements, 17/17 scenarios (final confirmation)

Per `sdd/login-security-management/spec` (Engram #641) and cross-checked against `verify-report-pr4`'s closing spec-completeness check (Engram #671):

| Capability | Requirements | Scenarios | Status |
|---|---|---|---|
| password-policy | 3 | 5 | Implemented (`User.php:216` `Assert\Regex`) and tested (`testPasswordAcceptsLetterAndDigit`, `testPasswordRejectsLettersOnly`, `testPasswordRejectsDigitsOnly`, `testPasswordBelowMinimumLengthIsStillRejected`, plus structural confirmation of uniform enforcement) — confirmed present in source at final merged `main` tip |
| remember-me-policy | 2 | 3 | Implemented (`security.yaml` `always_remember_me:false`, `login.html.twig:101` restored checkbox) and tested (`testLoginWithoutRememberMeDoesNotIssuePersistentCookie`, `testLoginWithRememberMeCheckedIssuesPersistentCookie`, `testLoginPageRendersRememberMeCheckboxUncheckedByDefault`) |
| admin-user-quick-actions | 3 | 3 | Implemented (`forcePasswordResetAction`/`revokeRememberMeAction`, `UserVoter('password')` gate, `UserSubscriber` submenu) and tested (force-reset flag change, revoke-remember-me signature change without session kill, non-admin 403 + submenu absence) |
| login-audit-trail | 4 | 6 | Implemented (`LoginAttempt` entity, `LoginAuditSubscriber`, `ROLE_SUPER_ADMIN`-gated `LoginAuditController`) and tested (`testOnLoginSuccessPersistsLoginAttempt`, `testOnLoginFailureWithKnownUsernamePersistsLoginAttemptWithUser`, `testOnLoginFailureWithUnknownUsernamePersistsLoginAttemptWithNullUser`, `testSuperAdminCanViewAuditList`/`CanFilterByOutcome`/`CanFilterByUser`, `testAdminIsDeniedAccess`, `testOldRecordsRemainQueryableWithoutDateFilter`, plus dedicated `testFailureReasonNeverContainsRawExceptionMessage` privacy guard) |
| **Total** | **12** | **17** | **All 12 requirements / 17 scenarios confirmed implemented and tested, independently re-verified per-PR and via this session's combined Phase 5 sweep** |

## Spec Merge Summary (new capabilities, not deltas)

No existing `openspec/specs/` capability covered auth/login before this change (confirmed in proposal #640 and design #642). All 4 delta specs were therefore full new specs, mechanically copied (never Read→Write) into `openspec/specs/`:

| Domain | Action | Target |
|---|---|---|
| login-audit-trail | Created | `openspec/specs/login-audit-trail/spec.md` |
| password-policy | Created | `openspec/specs/password-policy/spec.md` |
| admin-user-quick-actions | Created | `openspec/specs/admin-user-quick-actions/spec.md` |
| remember-me-policy | Created | `openspec/specs/remember-me-policy/spec.md` |

**Mechanical copy verification**: all 4 `cp` operations verified via `diff -r` (source vs. temp file, and again source vs. final target) — every diff empty, zero bytes altered.

## Archive Integrity

**Mechanical move verification**: `openspec/changes/login-security-management/` moved via `git mv` to `openspec/changes/archive/2026-08-14-login-security-management/`. A pre-move recursive snapshot was taken (`cp -R` to a `mktemp -d` staging dir) and compared post-move via `diff -r snapshot archived-folder` — **empty diff, exit 0**. Source directory confirmed removed. Archive contains:
- ✓ proposal.md
- ✓ design.md
- ✓ tasks.md (33/33 tasks checked, 0 unchecked, including the Phase 1 reconciliation and Phase 5 completion)
- ✓ specs/login-audit-trail/spec.md, specs/password-policy/spec.md, specs/admin-user-quick-actions/spec.md, specs/remember-me-policy/spec.md
- ✓ verify-report-pr1.md, verify-report-pr3.md, verify-report-pr4.md (PR2's verify report exists in Engram as #655 only — not written as a filesystem mirror during that phase; not a completeness gap, Engram is canonical for that artifact)

## Task Completion Gate

At archive time, filesystem `tasks.md` (source of truth for openspec/hybrid mode) showed **0 unchecked implementation tasks** (33/33 `[x]`) after this session's two fixes: the Phase 1 exceptional reconciliation (backed by verify-report-pr1 proof) and the live Phase 5 execution. No blocker.

## Native Review Receipt Gate

`reviewGate` was not present/applicable in this session's structured status for this candidate — archive proceeded under ordinary repository policy (this repo's established convention: direct-to-`main` documentation commits for archive operations, confirmed as this session's convention).

## SDD Cycle Complete

The login-security-management change has been:
1. Explored and proposed (4 concrete login-security gaps identified and scoped)
2. Specified with 12 requirements and 17 scenarios across 4 new capabilities
3. Designed with 5 architecture decisions, confirmed zero cross-capability coupling
4. Implemented across 4 chained PRs (#122, #123, #127, #130), all merged to `main`
5. Verified independently per-PR — 4/4 verify reports, each 0 CRITICAL (PASS or PASS WITH WARNINGS)
6. Regression-swept as a whole (Phase 5, this session) — 327/327 tests green, phpstan/twig/xliff clean, migration reversibility proven
7. Archived with specs synced to source of truth and the Phase 1 documentation gap closed

Ready for the next change.

---

**Engram artifact references for traceability (all read and verified this session):**
- Proposal: #640 (sdd/login-security-management/proposal)
- Spec: #641 (sdd/login-security-management/spec)
- Design: #642 (sdd/login-security-management/design)
- Tasks: #644 (sdd/login-security-management/tasks) — note: this Engram copy is a stale snapshot (revision 4, predates PR4 checkoff and this archive's Phase 1/5 fixes); the filesystem `tasks.md` at archive time is authoritative per hybrid-mode convention
- Apply progress: #648 (apply-progress), #668 (apply-progress-pr4)
- Verify reports: #652 (verify-report-pr1, PASS WITH WARNINGS), #655 (verify-report-pr2, PASS), #661 (verify-report-pr3, PASS WITH WARNINGS), #671 (verify-report-pr4, PASS WITH WARNINGS, 12/12 req, 17/17 scenarios)
- This archive report: Engram #673 (sdd/login-security-management/archive-report)
