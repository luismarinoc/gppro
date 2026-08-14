# Archive Report: self-registration-admin-approval

**Archived**: 2026-08-14
**Archived to**: `openspec/changes/archive/2026-08-14-self-registration-admin-approval/`
**Mode**: hybrid (Engram + OpenSpec filesystem)

## Full Cycle Status

| Phase | Status | Artifact |
|---|---|---|
| Explore/Propose | ✅ | Engram `sdd/self-registration-admin-approval/proposal` (obs #643) |
| Spec | ✅ | Engram `sdd/self-registration-admin-approval/spec` (obs #645) |
| Design | ✅ | Engram `sdd/self-registration-admin-approval/design` (obs #646) |
| Tasks | ✅ | Engram `sdd/self-registration-admin-approval/tasks` (obs #650) |
| Apply — PR1 | ✅ | Engram `sdd/self-registration-admin-approval/apply-progress` (obs #658) — 18/18 tasks |
| Apply — PR2 | ✅ | Engram `sdd/self-registration-admin-approval/apply-progress-pr2` (obs #669) — 21/22 tasks at apply time (see Task Completion Gate note below) |
| Verify — PR1 | ✅ PASS WITH WARNINGS, 0 CRITICAL | Engram `sdd/self-registration-admin-approval/verify-report-pr1` (obs #659) |
| Verify — PR2 | ✅ PASS WITH WARNINGS, 0 CRITICAL | Engram `sdd/self-registration-admin-approval/verify-report-pr2` (obs #672) |
| Archive | ✅ | This report |

**Change delivered as 2 chained PRs** (`stacked-to-main` per tasks.md's Review Workload Forecast, ~560-620 aggregate changed lines exceeding the 400-line single-PR budget):
- **PR1 (#126)**: entity + migration (`emailConfirmedAt`/`rejectedAt`), `confirmAction()` behavior change (no auto-enable, no auto-login), pending-approval page, reject-then-reregister guard. Merged to `main`.
- **PR2 (#131)**: admin approve/reject actions (`UserVoter`, `UserController`), `UserSubscriber` quick-actions, approval-notification email (`EmailUserApprovedEvent`), `UserQuery`/`UserRepository` pending-approval filter, admin list badge. Merged to `main` at commit `8273fec` ("Merge pull request #131 from luismarinoc/self-registration-admin-approval-pr2").

Both PRs confirmed merged to `main` via `git log --oneline` at archive time (tip `235abe0`, with `8273fec` present in history as the PR #131 merge commit, and PR #126 already an ancestor of that per the PR2 verify report's base-branch assessment).

## Task Completion Gate — Reconciliation Note

At archive time, `openspec/changes/self-registration-admin-approval/tasks.md` had one stale unchecked checkbox: task 8.6 "Open PR2 (base: main, after PR1 merges)". This was intentionally left unchecked by the apply agent per explicit orchestrator instruction not to push/open PRs itself (documented inline in the PR2 apply-progress report, obs #669, and in the original tasks.md note).

Per the Final-State Authority hierarchy and the Task Completion Gate's exceptional-reconciliation allowance, `sdd-archive` reconciled this single checkbox to `[x]` at archive time, backed by:
1. **Git evidence**: `git log --oneline` on `main` shows commit `8273fec` — "Merge pull request #131 from luismarinoc/self-registration-admin-approval-pr2" — confirming PR2 was in fact opened and merged (just not by the apply agent itself).
2. **Explicit final-state fact in the archive launch prompt**: "Both PRs (PR1 #126, PR2 #131) are merged to `main`."

No other tasks were reconciled — all other 39 tasks across both PRs (18 in PR1, 21 of 22 in PR2) were already checked `[x]` by the apply agent and independently re-verified against actual code by `sdd-verify` in both verify reports.

## Final Regression Sweep (combined, both PRs together — run fresh at archive time, not assumed from prior reports)

**Test command** (identical to the PR2 verify report's combined suite):
```
vendor/bin/phpunit tests/Entity/UserTest.php tests/Controller/UserControllerTest.php tests/Controller/Security/SelfRegistrationControllerTest.php tests/Voter/UserVoterTest.php tests/User/UserServiceTest.php tests/Repository/Query/UserQueryTest.php tests/Repository/UserRepositoryTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php
```
**Result**: `OK (200 tests, 980 assertions)` — exact match to both the PR2 apply-progress (obs #669, task 8.5) and PR2 verify-report (obs #672) numbers. Zero regression confirmed on `main` post-merge, across PR1 + PR2 + all concurrently-merged sibling changes (`login-security-management`, `approval-workflows-expansion`, `expense-*` families).

**Static analysis**:
```
vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
```
**Result**: exactly 1 error — `Controller/QuotationControllerTest.php::decodeJsonResponse()` return.type — pre-existing, unrelated to this change, identical baseline to both PR1 and PR2 verify reports.

**Lint**:
- `bin/console lint:twig templates/security/self-registration/pending_approval.html.twig templates/emails/user_approved.html.twig templates/user/index.html.twig` → `[OK] All 3 Twig files contain valid syntax.`
- `bin/console lint:xliff translations/messages.en.xlf translations/messages.es.xlf translations/email.en.xlf translations/email.es.xlf` → `[OK] All 4 XLIFF files contain valid syntax.`
- A repo-wide `bin/console lint:twig` (no path filter) surfaces 12 pre-existing vendor-bundle template errors (`jms/serializer-bundle`, `symfony/twig-bridge` — missing `twig-bundle`/`markdown_to_html` filter registration in this CLI context) unrelated to this change; none of the 3 templates touched by this change are among them. A repo-wide `bin/console lint:xliff` requires explicit filenames (does not accept a bare repo-wide invocation), so the 4 touched translation files were linted explicitly above.

## Spec Compliance — 8/8 Requirements, 9/9 Scenarios (combined PR1 + PR2)

Per the PR2 verify report's compliance matrix (obs #672), independently re-confirmed here as still valid post-merge (no code changes since that verification, only the archive-time regression re-run above which reproduces identical numbers):

| # | Requirement | Scenario | Test | PR |
|---|---|---|---|---|
| 1 | Email confirmation marks pending without enabling | User confirms email token | `SelfRegistrationControllerTest::testConfirmAccount` | PR1 |
| 1 | Email confirmation marks pending without enabling | Static pending-approval page | `SelfRegistrationControllerTest::testConfirmAccountRendersPendingApprovalPageFromSessionForFreshClient` | PR1 |
| 2 | Pending accounts cannot authenticate | Confirmed-but-unapproved login denied | `UserCheckerTest` (framework guard) + `testConfirmAccount`'s `assertRequestIsSecured` | PR1 |
| 3 | Admin can view pending-approval accounts distinctly | Admin views user list with pending accounts | `UserControllerTest::testIndexActionShowsPendingApprovalBadgeOnlyForPendingUsers` | PR2 |
| 4 | Never-confirmed users excluded from pending list | Unconfirmed registrant not shown as pending | `UserRepositoryTest::testPendingApprovalFilterReturnsOnlyEmailConfirmedDisabledNotRejectedUsers` | PR2 |
| 5 | Admin approve enables account + notifies | Admin approves a pending account | `UserControllerTest::testApproveActionEnablesUserAndSendsApprovalEmail` | PR2 |
| 6 | Admin reject sets soft-rejected state, no email | Admin rejects a pending account | `UserControllerTest::testRejectActionSetsRejectedAtWithoutEnablingOrEmailingAndKeepsTheRow` | PR2 |
| 7 | Rejected email cannot silently re-register | Rejected applicant re-registers with same email | `SelfRegistrationControllerTest::testRejectThenReregisterReusesSameRowAndClearsRejection` + `testRejectThenReregisterFullReviewCycleRepeats` | PR1 |
| 8 | Non-admin users cannot reach approve/reject | Non-admin attempts approve/reject | `UserControllerTest::testApproveActionIsDeniedForNonSuperAdmin` + `testRejectActionIsDeniedForNonSuperAdmin` (403) | PR2 |

**All 8 requirements / 9 scenarios confirmed implemented and tested across both PRs combined.**

## Known Warnings (carried from verify reports, resolved or non-blocking)

- PR1 verify report flagged a migration class-name collision risk (`Version20260814110000`) with an unrelated concurrent uncommitted change on a different worktree. PR2 verify report confirmed this never materialized on `main` (`grep -rn "class Version20260814110000" migrations/` → exactly one match).
- PR1's "rebase needed before merge" warning is moot — PR1 is merged.
- PR2 disclosed a deliberate design deviation: admin approve/reject CSRF pattern reused the already-merged `login-security-management` PR3's URL-embedded-CSRF-token quick-action convention instead of the design doc's literal `deleteAction`-form-pattern wording — simpler and precedent-consistent, does not break any spec scenario.
- Open suggestion (non-blocking): expose `UserQuery::pendingApproval` as an explicit toolbar filter control in a future follow-up (currently spec-compliant via badge + underlying filter, just less discoverable).

No CRITICAL issues were found in either verify report at any point in the cycle.

## Specs Synced

| Domain | Action | Details |
|---|---|---|
| `self-registration-admin-approval` | Created | New capability — no pre-existing main spec covered self-registration; delta spec copied mechanically (`cp`+`diff -r` verified empty) to `openspec/specs/self-registration-admin-approval/spec.md`. 8 requirements, 9 scenarios. |

## Mechanical Copy/Move Verification

**Spec sync** (`openspec/changes/self-registration-admin-approval/specs/self-registration-admin-approval/spec.md` → `openspec/specs/self-registration-admin-approval/spec.md`):
```
diff -r <source> <temp-copy>
```
Result: empty diff (PASS).

**Archive move** (`openspec/changes/self-registration-admin-approval/` → `openspec/changes/archive/2026-08-14-self-registration-admin-approval/`):
```
diff -r <pre-move-snapshot> <archived-dir>
```
Result: empty diff (PASS). This archive-report.md file is additive and was written after the move, so it is excluded from that comparison by design.

## Archive Contents

- `proposal.md` ✅
- `design.md` ✅
- `tasks.md` ✅ (40/40 tasks complete — 39 originally checked by apply agents + 1 reconciled at archive time per the note above)
- `specs/self-registration-admin-approval/spec.md` ✅
- `verify-report-pr1.md` ✅
- `verify-report-pr2.md` ✅
- `archive-report.md` ✅ (this file)

## Source of Truth Updated

`openspec/specs/self-registration-admin-approval/spec.md` now reflects the shipped behavior: mandatory admin-review gate between email confirmation and account activation, soft-reject with re-registration guard, admin approve/reject quick-actions, pending-approval filter and badge.

## SDD Cycle Complete

The `self-registration-admin-approval` change has been fully explored, proposed, specified, designed, task-planned, implemented (2 chained PRs), verified (both PASS WITH WARNINGS, 0 CRITICAL), and archived. Both PRs are merged to `main`. Ready for the next change.
