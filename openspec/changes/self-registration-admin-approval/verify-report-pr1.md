# Verification Report: self-registration-admin-approval (PR1 — Phases 1-4)

**Change**: self-registration-admin-approval
**Scope**: PR1 only — Phases 1-4 / 18 tasks (entity+migration, `confirmAction()` change, pending-approval page, reject-then-reregister guard)
**Branch**: `self-registration-admin-approval` @ `ee2b3371f156fcf45897dbaab468ae9a04db2e1f`
**Worktree**: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/self-registration-admin-approval`
**Base**: `main` @ `c194c8b` (merge-base). Local `main` has since advanced to `e05f3ee` (19 commits ahead; this branch is 3 commits ahead of the merge-base, 0 commits behind on its own lineage but the merge-base is stale).
**Mode**: Strict TDD
**Persistence mode observed**: Engram-only (spec/tasks/design/apply-progress found only via `mem_search`; no `openspec/changes/self-registration-admin-approval/` directory existed pre-verify)

## Verdict: PASS WITH WARNINGS

## 1. Task Completeness (18/18)

All 18 Phase 1-4 tasks are genuinely complete — verified against actual code, not just the apply-progress claim:

| # | Task | Evidence |
|---|------|----------|
| 1.1 | RED `UserTest::testIsPendingApproval` 4-state provider | `tests/Entity/UserTest.php:792-815` — never-confirmed/pending/rejected/approved, matches design exactly |
| 1.2 | GREEN `User::$emailConfirmedAt`/`$rejectedAt`/getters/setters/`isPendingApproval()` | `src/Entity/User.php:227-238,985-1015` |
| 1.3 | Migration `Version20260814110000.php` | Present, guarded (`hasColumn` checks), reversible `down()` |
| 1.4 | Migration verified live | Re-verified independently: `doctrine:migrations:migrate --env=test` on isolated `test_selfreg` DB reports already at latest version |
| 2.1 | RED rewritten `testConfirmAccount` | `tests/.../SelfRegistrationControllerTest.php:135-160` — asserts `emailConfirmedAt` set, `enabled` false, token cleared, redirect to `registration_pending_approval`, `assertRequestIsSecured` proves no auto-login |
| 2.2 | RED mirror-trap test | `testConfirmAccountRendersPendingApprovalPageFromSessionForFreshClient` (lines 162-183) — genuinely uses two separate `createClient()` instances, second one only carries the `MOCKSESSID` cookie copied from the first, never authenticates. This is a real mirror-trap, not a same-client shortcut. |
| 2.3 | GREEN `confirmAction()` change | Read directly — no `setEnabled(true)`, no `LoginManager` usage/import anywhere in the file. Sets `emailConfirmedAt`, stores email in session key `pending_approval_email_address`, redirects to `registration_pending_approval` |
| 2.4 | GREEN `pendingApprovalAction()` | Reads `$request->getSession()->get('pending_approval_email_address')`, NOT `$this->getUser()`. Empty-session guard redirects to `registration_register` |
| 2.5 | Template | `templates/security/self-registration/pending_approval.html.twig` — static, extends layout, no personalized/logged-in chrome |
| 2.6 | Translations | `registration.pending_approval` present in both `messages.en.xlf` and `messages.es.xlf` |
| 2.7 | Test/lint run | Independently re-run, all green (see §2) |
| 3.1/3.2 | RED reject-reregister tests | `testRejectThenReregisterReusesSameRowAndClearsRejection`, `testRejectThenReregisterFullReviewCycleRepeats` — both present and passing |
| 3.3 | GREEN `findRejectedUserForReregistration()` | `registerAction()` calls it before `createNewUser()`; reuses the same row by binding the form to the existing rejected entity (avoids `UniqueEntity` collisions since Doctrine excludes the entity-under-validation by identity), clears the 3 state fields, issues a fresh token |
| 4.1-4.4 | Regression + verification | Independently re-run: 164/164 tests, 1 pre-existing unrelated phpstan error, twig/xliff lint clean |

## 2. Independent Test Execution (re-run by verifier, not trusted from apply report)

```
vendor/bin/phpunit tests/Entity/UserTest.php tests/Controller/Security/SelfRegistrationControllerTest.php \
  tests/User/UserServiceTest.php tests/Controller/UserControllerTest.php tests/Voter/UserVoterTest.php
→ OK (164 tests, 791 assertions)
```
Matches apply report exactly. Used the isolated per-worktree test DB (`test_selfreg`, via `DATABASE_URL` env override matching `.env.test.local`) to avoid drift/collision with concurrent agents' shared `kimai2_test` schema — same isolation mechanism the apply report described, confirmed still in place and functional.

```
vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
→ 1 error: Controller/QuotationControllerTest.php::decodeJsonResponse() return.type (pre-existing, unrelated)
```

`tests/phpstan.neon` diff (`c194c8b..HEAD`) touches exactly one line: `count: 17` → `count: 18` for the existing `assertStringContainsString($haystack, string|false)` ignore pattern, scoped to `path: Controller/Security/SelfRegistrationControllerTest.php`. Independently counted `rg -c "assertStringContainsString" tests/Controller/Security/SelfRegistrationControllerTest.php` → **18**, matching the new count exactly. This is a narrowly-scoped, legitimate bump of a hand-maintained per-file/per-message occurrence count (not an auto-generated baseline, not a broadened suppression) — the new mirror-trap test added one more occurrence of the same pre-existing pattern.

```
bin/console lint:twig templates/security/self-registration/pending_approval.html.twig → OK
bin/console lint:xliff translations/messages.en.xlf → OK
bin/console lint:xliff translations/messages.es.xlf → OK
```

## 3. Spec Compliance Matrix (PR1-relevant requirements only)

| Requirement | Status | Evidence |
|---|---|---|
| Email confirmation marks pending-approval without enabling | PASS | `testConfirmAccount` (runtime) |
| Confirmed user sees static pending-approval page (not personalized) | PASS | `testConfirmAccountRendersPendingApprovalPageFromSessionForFreshClient` (runtime) + template read |
| Pending accounts cannot authenticate | PASS | `assertRequestIsSecured($client, '/homepage')` in `testConfirmAccount` (runtime) |
| Rejected email cannot silently re-register to bypass rejection | PASS | `testRejectThenReregisterReusesSameRowAndClearsRejection` + `testRejectThenReregisterFullReviewCycleRepeats` (runtime) |
| Admin can view pending-approval accounts distinctly / never-confirmed excluded / approve+reject actions / non-admin denied | OUT OF SCOPE for PR1 (Phase 5-8, PR2) | Not evaluated here |

## 4. Design Coherence

`User::$emailConfirmedAt`/`$rejectedAt` types (nullable `datetime_immutable`, mirroring `passwordRequestedAt` not `lastLogin`), `isPendingApproval()` boolean expression, `confirmAction()` behavior, reject-then-reregister approach, and file list all match `design.md` (Engram id 646) exactly. No deviations found.

## 5. CRITICAL Check — `src/Entity/User.php` overlap with merged `login-security-management` PR1

- `login-security-management` PR1 added a `#[Assert\Regex(...)]` password-complexity constraint on `$plainPassword` around line 213-215 of the current `main` version of `User.php`.
- This branch's own diff (`c194c8b..HEAD`) touches two disjoint regions: new field declarations after `$passwordRequestedAt` (~line 224+) and new getters/setters after `getConfirmationToken()` (~line 970+).
- Non-destructive 3-way check performed: `git merge-tree $(git merge-base main HEAD) main HEAD`. Result: `User.php` (and `UserTest.php`, `messages.en.xlf`, `messages.es.xlf`) are listed as "changed in both" but **git's tree-level 3-way merge resolves all of them automatically — zero `<<<<<<<` conflict markers, exit code 0**. The two `User.php` changes are additive and non-overlapping (different fields/methods, no semantic conflict), consistent with the task's expectation.

**Rebase needed**: **YES.** `git rev-list --left-right --count main...HEAD` → this branch is 3 ahead / 19 behind current `main`. `main` has advanced significantly (Invoice PR1, login PR1-3, Timesheet PR-T all merged). A rebase (or merge) onto current `main` is required before this can be opened as a mergeable PR — the branch was created off a now-stale point. **Conflict risk: low** per the merge-tree dry-run above; expect a clean or near-trivial rebase.

## 6. Additional Finding — Migration Version Class-Name Collision Risk (not this PR's fault, but a real merge hazard)

The primary `gppro` worktree (main checkout, currently dirty with uncommitted concurrent SDD work, appears to be `approval-workflows-expansion`-related) has an **untracked** file at `migrations/Version20260814110000.php` with the **same class name** (`DoctrineMigrations\Version20260814110000`) as this branch's migration, but completely different content (`"Add invoice payment approval state machine and audit trail"` vs. this branch's `email_confirmed_at`/`rejected_at` columns on `gppro_users`).

- This is **not a defect in this PR** — this PR's migration file is correct and does not collide with anything currently committed on `main`.
- It IS a real collision risk: if that other concurrent work is later committed with the same migration class name, merging both to `main` would produce a duplicate-class-declaration fatal error / Doctrine migrations version-identity collision.
- **Recommendation**: flag to whichever agent/PR owns that other uncommitted migration to rename its version identifier before it is committed. No action needed on this PR1 branch itself.

## 7. Local `main` Cleanliness

`git log --oneline -5 main` shows only the expected merged-PR history (`e05f3ee` release bump, `login-security-management-pr2-remember-me` merge, etc.) — **no accidental self-registration commits landed on local `main`.**

## Issues

### CRITICAL
None.

### WARNING
1. Branch is 19 commits behind current `main` (created off a now-stale merge-base) — rebase required before opening/merging the PR. Dry-run (`git merge-tree`) shows no conflicts, but this must be done and re-verified (tests re-run post-rebase) before merge, not assumed clean from a dry-run alone.
2. Migration class-name collision risk (`Version20260814110000`) between this branch and an unrelated uncommitted concurrent change in the primary `gppro` worktree — no action needed on this PR1 branch, but must be resolved by the other change before it commits/merges.

### SUGGESTION
None.

## Next Recommended

`sdd-archive` for PR1 is appropriate **after** the branch is rebased onto current `main` and the full test suite is re-run post-rebase to confirm no regression. Do not treat the pre-rebase `merge-tree` dry-run as a substitute for that post-rebase re-verification.
