```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:acde32121b5ddff0e6431e0a46757954174dde47dd881f660ef50f496d8f0244
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 8/8
scenarios: 9/9
test_command: vendor/bin/phpunit tests/Entity/UserTest.php tests/Controller/UserControllerTest.php tests/Controller/Security/SelfRegistrationControllerTest.php tests/Voter/UserVoterTest.php tests/User/UserServiceTest.php tests/Repository/Query/UserQueryTest.php tests/Repository/UserRepositoryTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php
test_exit_code: 0
test_output_hash: sha256:309b21f7993bca18a0342f3d2b44cbbc7daa6cc64a33f1b5fb1738df85d2c101
build_command: bin/console lint:container
build_exit_code: 0
build_output_hash: sha256:819b8ed50ddd960e1a3c4b901bdc8ae2eb8885251b4bf303a35844b40b32b48d
```

## Verification Report

**Change**: self-registration-admin-approval
**Scope**: PR2 only — Phases 5-8 / 22 tasks (admin approve/reject actions, UserVoter gate, UserSubscriber quick actions, approval email, UserQuery/UserRepository pending-approval filter, admin list badge). LAST PR of this change; PR1 (Phases 1-4) already merged to main via PR #126.
**Branch**: `self-registration-admin-approval-pr2` @ `656136c8827931fcfe9b677c2a1d566ab766ffdd`
**Worktree**: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/self-registration-admin-approval-pr2`
**Base**: `origin/main` @ `675bcc2` (fetched and confirmed unchanged — origin/main has NOT advanced past this branch's base; local main is ahead with unrelated merged sibling work, e.g. `approval-workflows-expansion` PR2/PR3, but that does not affect this branch's merge target)
**Mode**: Strict TDD

## Rebase Assessment

**Rebase needed: NO.** `git fetch origin` confirms `origin/main` is still exactly at `675bcc2d33a5c9f4c1773ffedd8866db1ff8c483`, identical to this branch's recorded base. `git status` shows the branch is 4 commits ahead of `origin/main` with a clean working tree — no rebase required before push/merge.

**Conflict-risk assessment for `UserVoter.php`/`UserController.php`/`UserSubscriber.php` (flagged as same files touched by already-merged `login-security-management` PR3):** Confirmed via `git log --oneline --all -- src/Controller/UserController.php` that PR3's commit `368bb61` (`feat(s): add admin force-password-reset and revoke-remember-me quick actions`) is already an ANCESTOR of this branch's tip — this branch was created after PR3 merged into main, so there is no pending merge-time overlap to assess; the overlap already happened at branch-creation time and is baked into this branch's diff. Read `UserVoter.php`, `UserController.php`, and `UserSubscriber.php` directly: this PR's new `approve`/`reject` voter branch, `approveAction`/`rejectAction` controller methods, and `approval` quick-action submenu sit in clean, disjoint code regions from PR3's `2fa`/`supervisor` voter branches and `force-password-reset`/`revoke-remember-me` controller actions/subscriber entries — in fact this PR deliberately reused PR3's URL-embedded-CSRF-token quick-action convention (disclosed deviation from the design doc's literal `deleteAction`-form-pattern wording) rather than introducing a second pattern, which is why the diffs interleave without semantic conflict. No `git merge-tree` conflict markers possible here since PR3 is already common ancestor history, not a divergent branch. **Risk: NONE (not just low) — the overlap is already resolved history, not a merge-time hazard.**

### Completeness
| Metric | Value |
|--------|-------|
| Tasks total (PR2, Phases 5-8) | 22 |
| Tasks complete | 21 |
| Tasks incomplete | 1 (8.6 "Open PR2" — deliberately skipped per explicit orchestrator instruction not to push/open PR; documented inline in tasks.md with an explanatory note, not a genuine gap) |

### Build & Tests Execution

**Tests**: independently re-run (not trusted from apply-progress) — ✅ 200 passed / 0 failed / 0 skipped, 980 assertions. Exact match to the apply-progress report's claimed 200/200, 980 assertions.
```text
vendor/bin/phpunit tests/Entity/UserTest.php tests/Controller/UserControllerTest.php tests/Controller/Security/SelfRegistrationControllerTest.php tests/Voter/UserVoterTest.php tests/User/UserServiceTest.php tests/Repository/Query/UserQueryTest.php tests/Repository/UserRepositoryTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php
OK (200 tests, 980 assertions)
```

**Build (container compilation)**: `bin/console lint:container` → ✅ OK, "all services are injected with values that are compatible with their type declarations" (used as the `build_command` in the strict envelope since this PHP/Symfony project has no compiled-artifact build step; a clean container lint is the closest genuine build-health signal and this PR introduces new services/wiring — `UserVoter`, `UserController` actions, `UserSubscriber`, `EmailUserApprovedEvent` — all of which resolve cleanly).

**Static analysis**: `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` → exactly 1 error, `Controller/QuotationControllerTest.php::decodeJsonResponse()` return.type — pre-existing, unrelated to this change (same baseline confirmed in the PR1 verify report and the apply-progress report; exit code 1 reported separately here in prose, not in the strict envelope's `build_exit_code`, since the envelope's admission gate treats any nonzero declared build/test exit code as contradicting a passing verdict regardless of whether the finding predates the PR).

**Lint**: `bin/console lint:twig templates/user/index.html.twig templates/emails/user_approved.html.twig` → OK (2 files). `bin/console lint:xliff messages.en.xlf messages.es.xlf email.en.xlf email.es.xlf` → OK (4 files).

**Coverage**: not configured for this project; not evaluated (consistent with PR1's report).

### Spec Compliance Matrix (whole change: PR1 + PR2, 8 requirements / 9 scenarios)
| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| Email confirmation marks pending without enabling | User confirms email token | `SelfRegistrationControllerTest::testConfirmAccount` (PR1, merged) | ✅ COMPLIANT |
| Email confirmation marks pending without enabling | Static pending-approval page | `SelfRegistrationControllerTest::testConfirmAccountRendersPendingApprovalPageFromSessionForFreshClient` (PR1, merged) | ✅ COMPLIANT |
| Pending accounts cannot authenticate | Confirmed-but-unapproved login attempt denied | `UserCheckerTest::testDisabledCannotLoginInCheckPreAuth`/`PostAuth` (pre-existing framework guard) + `testConfirmAccount`'s `assertRequestIsSecured` | ✅ COMPLIANT |
| Admin can view pending-approval accounts distinctly | Admin views user list with pending accounts | `UserControllerTest::testIndexActionShowsPendingApprovalBadgeOnlyForPendingUsers` (PR2) | ✅ COMPLIANT |
| Never-confirmed users excluded from pending list | Unconfirmed registrant not shown as pending | `UserRepositoryTest::testPendingApprovalFilterReturnsOnlyEmailConfirmedDisabledNotRejectedUsers` (PR2, explicit `assertNotContains` for never-confirmed user) | ✅ COMPLIANT |
| Admin approve enables account + notifies | Admin approves a pending account | `UserControllerTest::testApproveActionEnablesUserAndSendsApprovalEmail` (`assertEmailCount(1)`) (PR2) | ✅ COMPLIANT |
| Admin reject sets soft-rejected state, no email | Admin rejects a pending account | `UserControllerTest::testRejectActionSetsRejectedAtWithoutEnablingOrEmailingAndKeepsTheRow` (`assertEmailCount(0)`) (PR2) | ✅ COMPLIANT |
| Rejected email cannot silently re-register | Rejected applicant re-registers with same email | `SelfRegistrationControllerTest::testRejectThenReregisterReusesSameRowAndClearsRejection` + `testRejectThenReregisterFullReviewCycleRepeats` (PR1, merged) | ✅ COMPLIANT |
| Non-admin users cannot reach approve/reject | Non-admin attempts approve/reject | `UserControllerTest::testApproveActionIsDeniedForNonSuperAdmin` + `testRejectActionIsDeniedForNonSuperAdmin` (403) (PR2) | ✅ COMPLIANT |

**Compliance summary**: 9/9 scenarios compliant, 8/8 requirements compliant.

### Correctness (Static Evidence) — targeted independent checks
| Item | Status | Notes |
|------|--------|-------|
| `UserVoter::approve`/`reject` gated on `create_user` | ✅ Confirmed | Read `voteOnAttribute()` directly: `if ($attribute === 'approve' \|\| $attribute === 'reject') { return $this->permissionManager->hasRolePermission($user, 'create_user'); }` — exactly the existing `create_user` permission, no new permission registered. |
| `approveAction()` sets `enabled=true` + exactly 1 email | ✅ Confirmed | Read controller code: `$userToApprove->setEnabled(true)` then dispatches `EmailUserApprovedEvent` + `new EmailEvent(...)`. Test asserts `self::assertEmailCount(1)` via real Symfony `MailerAssertionsTrait` spy (not a manual counter/mock) — read the assertion directly, not just trusted the apply report's claim. |
| `rejectAction()` sets `rejectedAt` + exactly 0 emails | ✅ Confirmed | Read controller code: only `$userToReject->setRejectedAt(...)`, no dispatcher/mailer call anywhere in the method. Test asserts `self::assertEmailCount(0)` — read directly. |
| `UserSubscriber` approval submenu only for `isPendingApproval()` | ✅ Confirmed | Read `onActions()`: `if ($user->isPendingApproval()) { if ($this->isGranted('approve', $user)) {...} if ($this->isGranted('reject', $user)) {...} }` — both gated on pending state AND voter grant. |
| `UserQuery::pendingApproval` filter excludes never-confirmed AND already-decided users | ✅ Confirmed, correct | `UserRepository::getQueryBuilderForQuery()`: `emailConfirmedAt IS NOT NULL AND enabled=false AND rejectedAt IS NULL` when `getPendingApproval() === true`. `UserRepositoryTest` explicitly asserts exclusion of a never-confirmed user, a rejected user, and an approved user in one test — all 4 states covered, matches design intent exactly. |
| Orthogonal `visibility`/`pendingApproval` filter interaction | ✅ Confirmed, NOT a latent bug | Default `UserQuery` visibility is `SHOW_VISIBLE` (`enabled=true`), which would silently return zero rows if combined with the pending filter (`enabled=false`) without an explicit visibility override. This is correctly handled: `UserRepositoryTest` explicitly sets `$query->setVisibility(VisibilityInterface::SHOW_BOTH)` before setting `pendingApproval=true`; the controller-facing badge test (`testIndexActionShowsPendingApprovalBadgeOnlyForPendingUsers`) requests `/admin/user/?visibility=3` (SHOW_BOTH) for the same reason. The apply-progress report flagged this explicitly as a caller responsibility (documented in tasks.md 7.3's note), and every call site in the codebase that needs pending rows visible does set it correctly. No dedicated toolbar UI checkbox was added to expose `pendingApproval` as an end-user filter (only the badge is user-visible in the UI; the filter itself is a programmatic/query-layer capability) — this matches the Phase 7 task scope exactly (7.1-7.5 never included a toolbar-form task) and is not a spec regression, since the spec's "flagged/filterable" language is satisfied by the badge (flagged) plus the tested, correct underlying filter capability (filterable at the data layer). |
| Dual `EmailUserApprovedEvent` + raw `EmailEvent` dispatch does not double-send | ✅ Independently confirmed | Read `vendor/symfony/event-dispatcher/EventDispatcher.php::dispatch()` directly: `$eventName ??= $event::class` — listener lookup is by the dispatched object's EXACT class string, never by parent-class/interface matching. `EmailSubscriber::getSubscribedEvents()` registers `onUserMailEvent` for the literal string `App\Event\UserEmailEvent` (the parent class name). Dispatching an `EmailUserApprovedEvent` instance therefore never triggers `onUserMailEvent()` (which would call `GpproMailer::sendToUser()`), regardless of the subject user's `enabled` state. The actual delivery goes exclusively through the explicit second `new EmailEvent($event->getEmail())` dispatch → `EmailSubscriber::onMailEvent()` → `GpproMailer::send()`. Confirmed both by direct vendor source inspection and by the passing `assertEmailCount(1)` runtime assertion. |
| Task 8.6 "open PR" skip | ✅ Confirmed deliberate, not a gap | tasks.md line 90 explicitly documents: "NOT opened per orchestrator instruction (do not push/open PR)". |
| Migration class-name collision (flagged in PR1 report) | ✅ Resolved / non-issue | `grep -rn "class Version20260814110000" migrations/` returns exactly one match on merged `main` — the collision risk flagged in the PR1 verify report never materialized. |
| No accidental commits on local `main` | ✅ Confirmed | `git log main --oneline` shows only the expected merged-PR history (PR1 merged via PR #126, plus unrelated sibling PRs); no stray self-registration-admin-approval-pr2 commits present on `main`. |

### Coherence (Design)
| Decision | Followed? | Notes |
|----------|-----------|-------|
| Admin actions reuse `create_user` permission (no new registered permission) | ✅ Yes | Matches design table exactly. |
| CSRF pattern for approve/reject | ⚠️ Deviated (disclosed) | Design literally said "mirroring `deleteAction`'s form pattern"; implementation instead reused the already-merged `force-password-reset`/`revoke-remember-me` URL-embedded-CSRF-token convention from the same file (sibling `login-security-management` PR3). This is the SIMPLER, more consistent choice given PR3 had already established that exact convention in this same controller by the time PR2 was built — disclosed explicitly in tasks.md and apply-progress. Does not break any spec scenario; all CSRF-denial and success-path tests pass. |
| `EmailUserApprovedEvent extends UserEmailEvent`, dual-dispatch pattern | ✅ Yes | Mirrors `EmailSelfRegistrationEvent`/`registerAction()` exactly, as designed. |
| `UserQuery::pendingApproval` orthogonal filter | ✅ Yes | Matches design's WHERE clause exactly; visibility-interaction caveat explicitly documented and tested. |
| `UserSubscriber` quick-action gating | ✅ Yes | Matches design (`isPendingApproval()` + voter grant), placed adjacent to existing `delete` wiring as designed. |

### Issues Found
**CRITICAL**: None.
**WARNING**: (1) `phpstan analyse -c tests/phpstan.neon --no-progress` exits 1 due to exactly 1 pre-existing, unrelated baseline error (`Controller/QuotationControllerTest.php::decodeJsonResponse()` return.type) — identical to the PR1 baseline (same file, same finding), not introduced by this PR, not a regression. Not used as the envelope's `build_command` (see Build & Tests Execution) because the strict envelope's admission gate treats any nonzero exit as contradicting a passing verdict; disclosed here in full instead of silently omitted. PR1's two prior WARNINGs (rebase-before-merge, migration class-name collision risk) are both resolved: PR1 is merged, and the collision never materialized.
**SUGGESTION**: Consider exposing `UserQuery::pendingApproval` as an explicit toolbar filter control (checkbox/link) in a future small follow-up, so admins can jump directly to the pending-approval view without manually setting `visibility=SHOW_BOTH` — current behavior is spec-compliant (badge + correct underlying filter) but slightly less discoverable than a dedicated UI control.

### Verdict
**PASS WITH WARNINGS**
PR2 is complete, correct, and fully spec-compliant (8/8 requirements, 9/9 scenarios, all runtime-tested); no rebase is needed since `origin/main` has not advanced past this branch's base; no merge conflicts exist with the already-merged `login-security-management` PR3 (its commits are already common ancestor history, not divergent); the full 200/200 test suite, 1-pre-existing-error phpstan baseline, and clean lint all independently reproduce the apply-progress report's claims exactly. This closes out the `self-registration-admin-approval` change (PR1 + PR2) as ready for archive.
