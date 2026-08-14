# Verify Report: Login Security Management — PR3 (Admin User Quick Actions)

**Change**: `login-security-management`
**Scope**: PR3 / Phase 3 only (admin-user-quick-actions domain — 3 of 4 chained PRs)
**Branch**: `login-security-management-pr3-quick-actions` (worktree: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/login-security-management-pr3-quick-actions`), tip `60f39b7`, 3 commits ahead of stated base `origin/main`@`e05f3ee`
**Mode**: Full artifact verification (proposal + spec + design + tasks all present, retrieved from Engram + openspec mirror files)
**Verdict**: **PASS WITH WARNINGS**

## 0. Branch Freshness / Rebase Assessment (CRITICAL finding)

- `git fetch origin` then `git status`: branch and `origin/main` have **diverged — 3 commits local-only, 15 commits on `origin/main` not in this branch**.
- `git log --oneline main..HEAD` / `origin/main..HEAD`: both return the same 3 commits (`bd11a93`, `26af2ff`, `60f39b7`) — confirms the branch's own 3 commits are genuinely new, no accidental extra commits.
- `git merge-base HEAD origin/main` = `e05f3ee` — matches the branch's documented base exactly. **`origin/main` has advanced 15 commits since this branch was cut**, including:
  - `ee48551` `fix(security): update UserControllerTest fixture password to satisfy new complexity rule` (PR #125)
  - the full `self-registration-admin-approval` PR1/PR2 merge (`b5140d9`, touching `src/Entity/User.php`, `SelfRegistrationController.php`, `tests/Entity/UserTest.php`, `tests/Controller/Security/SelfRegistrationControllerTest.php`, `tests/phpstan.neon`, `translations/messages.{en,es}.xlf`, a migration)
  - `approval-workflows-expansion-pr-t-timesheet` merge (`64a59fd`, Timesheet team-lead approval — unrelated territory)
  - 3 version-bump chore commits
- **Rebase is required before merge.** This is a stale base, not a false alarm.

### Conflict risk assessment (empirically tested, not guessed)

Ran `git merge --no-commit --no-ff origin/main` in the worktree to get a real 3-way-merge signal, then `git merge --abort` to restore clean state (verified `git status` clean and `HEAD` unchanged at `60f39b7` afterward).

**Result: clean automatic merge, zero conflicts**, including on every file with real textual overlap:
- `translations/messages.en.xlf` / `messages.es.xlf` — PR3 adds `force_password_reset`/`revoke_remember_me` keys near line ~694-702; `self-registration-admin-approval` adds `registration.pending_approval` near line ~182. Different regions, no overlap.
- `tests/Controller/UserControllerTest.php` — `ee48551`'s 1-line fixture fix is at line ~107; PR3's 5 new test methods start at line ~220. Different regions, no overlap.
- `tests/phpstan.neon` — baseline-count bump from `self-registration-admin-approval`, untouched by PR3.

**No overlap at all** in `src/Controller/UserController.php` or `src/EventSubscriber/Actions/UserSubscriber.php` — `self-registration-admin-approval` never touches either file (confirmed via `git show --stat` on its 2 feature commits: only `src/Entity/User.php` +44 lines additive, plus `SelfRegistrationController.php`, its own test, template, translations).

**Conclusion**: despite the overlapping-territory concern (both changes touch `User.php`/`UserController.php`/`UserSubscriber.php` "space"), the actual overlap is **lower risk than initially flagged** — `self-registration-admin-approval` only touched `User.php` (additive fields, no line-range collision with PR1's already-merged regex constraint) and never touched `UserController.php`/`UserSubscriber.php` at all. A straightforward `git rebase origin/main` should apply cleanly with no manual conflict resolution required. (Caveat: a real rebase — replaying each commit individually — was not executed, since that is an apply-phase action outside verify's scope; the single-shot merge test is strong but not 100% identical evidence to a per-commit rebase.)

## 1. Completeness Table (Phase 3, 8 tasks)

| Task | Status | Evidence |
|---|---|---|
| 3.1 RED — `testForcePasswordResetActionSetsRequiresPasswordResetFlag` | [x] Complete | present in `tests/Controller/UserControllerTest.php`, exercises real POST through kernel |
| 3.2 GREEN — `forcePasswordResetAction` | [x] Complete | `src/Controller/UserController.php:186-201`, POST-only, CSRF-protected, `#[IsGranted('password','userToUpdate')]`, wraps `setRequiresPasswordReset(true)` |
| 3.3 RED — `testRevokeRememberMeActionChangesSecuritySignatureWithoutTouchingSession` | [x] Complete | present, asserts signature rotation without session-store call |
| 3.4 GREEN — `revokeRememberMeAction` | [x] Complete | `src/Controller/UserController.php:203-218`, same pattern, wraps `resetSecuritySignature()` |
| 3.5 RED — `testOnActionsAddsSecuritySubmenuWithBothActionsWhenPasswordGranted` | [x] Complete | present in `tests/EventSubscriber/Actions/UserSubscriberTest.php`, unit test w/ mocked collaborators |
| 3.6 GREEN — `UserSubscriber::onActions()` security submenu | [x] Complete | `src/EventSubscriber/Actions/UserSubscriber.php:80-86`, gated by `isGranted('password', $user)`, `templates/user/actions.html.twig` untouched (confirmed via `git diff` — file not in PR3's changeset) |
| 3.7 RED — non-admin 403 + submenu omission | [x] Complete | 3 functional/unit tests present covering denied-403 and submenu-absent paths |
| 3.8 Run targeted suite + phpstan, open PR3 | [x] Complete | independently re-executed below, matches apply report exactly |

8/8 Phase 3 tasks genuinely complete — verified against source, not checkbox trust alone.

## 2. Controller Actions — Source Verification

`src/Controller/UserController.php:186-218`, both actions confirmed:
- `methods: ['POST']` only (routes `admin_user_force_password_reset`, `admin_user_revoke_remember_me`) — no GET.
- CSRF: `CsrfTokenManagerInterface::isTokenValid(new CsrfToken('admin_user_{action}_' . $id, $csrfToken))`, invalid token → flash error + redirect (not silently ignored, not a 500).
- `#[IsGranted('password', 'userToUpdate')]` — authorization enforced at the controller boundary via existing `UserVoter`.
- `forcePasswordResetAction` wraps `$userToUpdate->setRequiresPasswordReset(true)` then `saveUser()`. Matches spec requirement exactly.
- `revokeRememberMeAction` wraps `$userToUpdate->resetSecuritySignature()` then `saveUser()`. No session-store service is called anywhere in either action — matches spec's "MUST NOT terminate an already-active session" requirement (there is nothing in the method that could touch an active session).

## 3. `UserSubscriber::onActions()` Wiring — Voter and CSRF-Pattern Verification

- **No new voter added.** `git diff e05f3ee 26af2ff --stat -- src/Voter/` returns empty — zero voter files touched in this PR. `UserVoter.php` already lists `'password'` as a supported attribute (line 30) with handling at line 93, pre-existing and unmodified. The `security` submenu is gated by `$this->isGranted('password', $user)` at `UserSubscriber.php:80`, reusing this existing attribute exactly as claimed.
- **CSRF-in-URL-path pattern — confirmed real, pre-existing precedent, not invented:**
  - `InvoiceController::deleteTemplate` (`src/Controller/InvoiceController.php:746-756`): route `path: '/template/{id}/delete/{csrfToken}'`, validated via `CsrfTokenManagerInterface::isTokenValid(new CsrfToken('invoice.delete_template', $csrfToken))` — identical shape to PR3's actions.
  - `ProjectController::duplicateAction` (`src/Controller/ProjectController.php:533-541`) + `ProjectSubscriber::onActions()` (line 91: `$this->path('admin_project_duplicate', ['id' => ..., 'token' => $payload['token']])`) — same URL-embedded-token pattern, token read from event payload rather than generated inline.
  - One minor architectural variance from the `ProjectSubscriber` precedent worth noting: `UserSubscriber` generates the CSRF token directly inside `onActions()` via an injected `CsrfTokenManagerInterface->getToken(...)`, whereas `ProjectSubscriber` reads a pre-generated token from `$payload['token']`. Functionally equivalent (both produce a real, session-bound token at page-render time), just a different call site for token generation — not a deviation that weakens security or contradicts the established pattern.

## 4. Full Test Suite — Independently Re-Executed

`BOOTSTRAP_RESET_DATABASE=0 vendor/bin/phpunit tests/Controller/UserControllerTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php tests/Voter/UserVoterTest.php`

**Result: 121 tests, 358 assertions, 1 failure.** Exact match to the apply report's claim.

The 1 failure: `UserControllerTest::testCreateAction` — "Failed asserting that 200 matches expected 201."

**Root-cause chain, fully traced (not assumed):**
1. `ee48551` (`fix(security): update UserControllerTest fixture password to satisfy new complexity rule`, merged PR #125) changed `tests/Controller/UserControllerTest.php` line ~107: `plainPassword` fixture `'12345678'` (all-digit) → `'Passw0rd'` (letter+digit), because PR1's password-complexity regex (already merged to `main`) rejects all-digit passwords.
2. `git merge-base HEAD origin/main` = `e05f3ee`, which **predates** `ee48551` (`ee48551` is one of the 15 commits ahead on `origin/main` that this branch does not have).
3. This branch's `UserControllerTest.php` therefore still carries the stale `'12345678'` fixture. `testCreateAction` posts this password; server-side validation correctly rejects it (per PR1's already-merged regex), the create form re-renders with a validation error (HTTP 200) instead of succeeding (HTTP 201) — precisely reproducing "200 vs 201".

**Determination: this is Case 1 (stale/expected), not a regression.** The failure is caused by this branch missing an already-merged fix, not by anything PR3 introduced or by rebasing away from a fix. After a rebase onto current `origin/main` (which includes `ee48551`), this specific fixture line will be updated by the rebase and the test is expected to pass, yielding 121/121. This expectation follows directly from the traced root cause and from the merge-test in §0 (which showed `tests/Controller/UserControllerTest.php` auto-merges cleanly, meaning `ee48551`'s fix will land intact); it was not re-verified by performing and re-testing an actual rebase, which is outside verify's scope.

## 5. Static Analysis

`vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` → **exactly 1 error**, pre-existing and unrelated: `Controller/QuotationControllerTest.php::decodeJsonResponse()` — `return.type` (declared `array<string,mixed>`, inferred `array<mixed,mixed>`). Matches the documented repo-wide baseline (same error cited in PR1's verify report and `expense-access-scoping`'s). No new phpstan errors introduced by PR3's changes.

## 6. Translation Lint

`bin/console lint:xliff translations/messages.en.xlf translations/messages.es.xlf` → **"All 2 XLIFF files contain valid syntax."** Pass.

## 7. Local `main` Integrity

`git merge-base --is-ancestor main origin/main` → true. Local `main` (`26569f3`) is a clean, unmodified ancestor of `origin/main` — no accidental commits landed on local `main`.

## 8. Frontend Wiring Gap — Assessed, Judged Acceptable (WARNING, not CRITICAL)

Read `templates/user/actions.html.twig` (unchanged by this PR — thin macro dispatching `actions.user` event to the generic `widgets.table_actions`/`widgets.page_actions` render path) and `UserSubscriber`'s new submenu entries (`security` submenu, `force_password_reset`/`revoke_remember_me`, rendered as ordinary action-menu links via the shared `action_button`/`@theme/components/button.html.twig` macros — no bespoke markup).

Confirmed the described gap is real: `assets/js/plugins/GpproConfirmationLink.js` (`init()`, lines 40-57) — when the clicked element is a bare `<a>` (not `target.form`), it performs `document.location = url` — a **plain GET navigation**. Since both new routes are `methods: ['POST']` only, a plain-link click today would hit a **405 Method Not Allowed**, not silently misbehave or bypass security — it fails closed/loud, not open.

**Scope judgment**: `design.md`'s File Changes table (the authoritative implementation table — it explicitly supersedes `proposal.md`'s Affected-Areas line, see design's own "Alternatives considered: editing `actions.html.twig` directly … rejected") lists exactly 3 backend files in scope for this domain: `User.php` (done in PR1), `UserController.php`, `UserSubscriber.php`. `templates/user/actions.html.twig` / JS wiring is not in that table. Task 3.6 explicitly instructs "Do NOT touch `templates/user/actions.html.twig` directly," which was honored.

**Call: acceptable, explicitly-scoped-out gap — does not block this PR's merge.**
- The backend contract (routes, CSRF, authorization, entity-state mutation) is complete, correct, and fully tested end-to-end through the real Symfony kernel.
- The gap fails safe: a stale/mis-wired click produces a 405, not an unauthorized action or a silent no-op that looks like success.
- It is honestly documented (not silently shipped) in the apply report's Deviations section, with a named, scoped fast-follow recommendation.
- This is an internal super-admin-only admin UI; the exposure window is "the button doesn't work yet," not a security or data-integrity risk.

**Recommendation**: track the click-to-POST wiring (small inline `<form method="post">` per action, or a `GpproConfirmationLink` enhancement) as an explicit fast-follow PR (e.g. PR3.1) before relying on this in the live admin UI. Flagged as WARNING in this report, not CRITICAL — it does not block merging PR3 as scoped, but should not be forgotten.

## Issues

### CRITICAL
None.

### WARNING
1. **Rebase required before merge** — branch base (`origin/main`@`e05f3ee`) is 15 commits stale. Empirically low conflict risk (clean auto-merge tested), but the rebase itself has not been performed. Do this before opening/merging the PR.
2. **Frontend click-to-POST wiring not yet built** — the 2 new routes are real, secure, and tested, but unreachable via a plain link click today (405). Explicitly scoped out per design's File Changes table; track as a fast-follow, do not forget it.
3. **`testCreateAction` currently fails on this branch** (200 vs 201) due to a stale fixture relative to already-merged `main` (`ee48551`). Expected to self-resolve on rebase — re-run the targeted suite after rebasing to confirm 121/121 before merge, do not merge on the strength of this report's pre-rebase run alone.

### SUGGESTION
1. Consider aligning `UserSubscriber`'s inline CSRF-token-generation call site with `ProjectSubscriber`'s payload-based pattern for full architectural consistency (cosmetic only, not a defect).

## Final Verdict

**PASS WITH WARNINGS**

All 8 Phase 3 tasks are genuinely complete and correctly implemented against the spec (force-password-reset, revoke-remember-me, non-admin 403, no-new-voter, established CSRF pattern — all confirmed via source + passing tests). Test suite, phpstan, and xliff lint all match the apply report's claims exactly, with the one failing test independently root-caused as a stale-base artifact, not a regression. The frontend click-wiring gap is real but explicitly scoped out and safely deferred. **Rebase onto current `origin/main` is required before merge** — conflict risk is empirically low (clean test-merge across all overlapping files, zero touch to the 2 controller/subscriber files in question by `self-registration-admin-approval`), but the rebase must actually be performed and the targeted test suite re-run to confirm 121/121 before this PR opens.
