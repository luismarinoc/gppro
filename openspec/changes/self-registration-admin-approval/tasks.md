# Tasks: Self-Registration Admin Approval

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~560-620 aggregate (PR1 ~250-290, PR2 ~300-330 — 1 entity, 1 migration, 2 controllers, 1 voter, 1 subscriber, 1 query, 1 repository, 3 new templates/classes, 2 translation domains x2 locales, 7 test files) |
| 400-line budget risk | High as single PR / Low-Medium for PR1 alone / Medium for PR2 alone |
| Chained PRs recommended | Yes |
| Suggested split | PR1 (entity+migration+confirmAction+reject-reregister-guard+pending-page) -> PR2 (admin approve/reject+voter+subscriber+email+query filter+badge) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

**Sequencing call (confirms design's flagged split as final, resolves the borderline call):** design estimated 330-400 total and flagged the 2-PR split as conditional on RED-scaffolding weight. Reading the actual touched surface (`SelfRegistrationController`, `UserController`, `UserVoter`, `UserSubscriber`, `UserQuery`, `UserRepository`, plus 3 new files and 4 translation files) shows 7 distinct test files spanning nearly every architectural layer — materially heavier than the design's estimate, so the single-PR path is ruled out. Splitting exactly along the design's own boundary works cleanly: PR1 is the safety-critical core (no live account without email+admin review) and is independently mergeable — the feature stays OFF (`isSelfRegistrationActive()`) so an interim state with no admin UI yet is inert, not broken. PR2 adds the admin-facing half. `stacked-to-main` (not `feature-branch-chain`) because PR1 is safe to land alone (dormant feature) and PR2's only real dependency is the merged column/route from PR1, not a coordinated release — same reasoning pattern as `login-security-management`.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | `emailConfirmedAt`/`rejectedAt` on `User`, migration, `confirmAction()` behavior change, pending-approval page, reject-then-reregister guard | PR1 (base: main) | `phpunit tests/Entity/UserTest.php tests/Controller/Security/SelfRegistrationControllerTest.php` | manual: confirm a self-registration in staging, verify no auto-login and pending page renders | revert migration `down()` (drops 2 columns) + revert controller/template/translation changes; feature stays OFF regardless |
| 2 | Admin approve/reject actions, `UserVoter`, `UserSubscriber` quick actions, `EmailUserApprovedEvent`, `UserQuery`/`UserRepository` pending filter, admin list badge | PR2 (base: main, after PR1 merges) | `phpunit tests/Voter/UserVoterTest.php tests/Controller/UserControllerTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php` | manual: approve/reject a pending user in staging, confirm email sent only on approve and badge disappears | revert 2 `UserController` actions + voter/subscriber entries + query/repository filter + badge template block; no schema change in PR2 |

## Phase 1: Entity + Migration (PR1, base: main)

- [x] 1.1 RED: `tests/Entity/UserTest.php` — data-provider `isPendingApproval()` over 4 states: never-confirmed (both null, false), pending (`emailConfirmedAt` set, `enabled=false`, `rejectedAt` null, true), rejected (`rejectedAt` set, false), approved (`enabled=true`, false).
- [x] 1.2 GREEN: `src/Entity/User.php` — add `#[ORM\Column(name: 'email_confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] ?\DateTimeImmutable $emailConfirmedAt` and `rejected_at` sibling (mirrors `passwordRequestedAt`'s type, not `lastLogin`'s legacy mutable type), getters/setters, `isPendingApproval(): bool`. Run 1.1 green.
- [x] 1.3 Create `migrations/Version20260814110000.php` — `up()` adds 2 nullable `email_confirmed_at`/`rejected_at` columns to `gppro_users`; `down()` drops both.
- [x] 1.4 Verify: apply migration on test DB, confirm both columns exist/nullable; confirm `down()` reverses cleanly.

## Phase 2: `confirmAction()` Behavior Change + Pending-Approval Page (PR1)

- [x] 2.1 RED: rewrite `tests/Controller/Security/SelfRegistrationControllerTest.php::testConfirmAccount` — assert `emailConfirmedAt` set, `enabled` stays `false`, `confirmationToken` cleared, redirect target is `registration_pending_approval` (not `registration_confirmed`), and no security token exists on the client after the request.
- [x] 2.2 RED (mirror-trap guard): new test — request `/register/confirm/{token}` then follow the redirect with a **fresh unauthenticated client session carrying only the session cookie**; assert the pending-approval page renders successfully and shows the confirmed email. This catches a `getUser()`-based implementation, which would 302-to-login instead of rendering (user is never auto-logged-in per D2).
- [x] 2.3 GREEN: `SelfRegistrationController::confirmAction()` — remove `setEnabled(true)` and the `LoginManager::logInUser()` call/param; set `emailConfirmedAt = now`; store the confirmed email into session (new key, mirrors `registerAction()`'s `confirmation_email_address` pattern); redirect to `registration_pending_approval`.
- [x] 2.4 GREEN: new `pendingApprovalAction()` + route `registration_pending_approval` (GET) — reads email from `$request->getSession()->get(...)`, NOT `$this->getUser()`; empty-session guard redirects to `registration_register` (mirrors `checkEmailAction()`). Run 2.1-2.2 green.
- [x] 2.5 GREEN: `templates/security/self-registration/pending_approval.html.twig` — mirrors `check_email.html.twig` structure, static message with session-carried email, no personalized/logged-in chrome.
- [x] 2.6 `translations/messages.en.xlf` + `messages.es.xlf` — add `registration.pending_approval` key/target pair.
- [x] 2.7 Run `phpunit tests/Controller/Security/SelfRegistrationControllerTest.php`; `lint:twig` on the new template; `lint:xliff` on both touched files.

## Phase 3: Reject-Then-Reregister Guard (PR1)

- [x] 3.1 RED: extend `SelfRegistrationControllerTest` — a user row with `rejectedAt` set submits `registerAction()` with that same email; assert the SAME row id is reused, `rejectedAt`/`confirmationToken`/`emailConfirmedAt` are cleared, a new `confirmationToken` is issued, redirect is the normal check-email flow (no silent bypass).
- [x] 3.2 RED: regression — after 3.1's re-registration, confirming the new token sets `isPendingApproval() === true` again (full review cycle repeats, not auto-approved).
- [x] 3.3 GREEN: `SelfRegistrationController::registerAction()` — before `createNewUser()`, call `findUserByEmail($email)`; if found with `rejectedAt !== null`, reuse that row (clear the 3 fields, issue new token) instead of creating a new one; else unchanged flow. Run 3.1-3.2 green.

## Phase 4: PR1 Regression + Verification

- [x] 4.1 Run `phpunit tests/Entity/UserTest.php tests/Controller/Security/SelfRegistrationControllerTest.php` — all green.
- [x] 4.2 Confirm unaffected existing scenarios stay green unmodified: `testConfirmedAnonymousRedirectsToLogin`, `testRegisterAccount`, `testCheckEmailWithoutEmail`, `testConfirmWithInvalidToken`, validation-error data provider.
- [x] 4.3 `phpstan analyse -c tests/phpstan.neon` — expect exactly 1 pre-existing unrelated error (`Controller/QuotationControllerTest.php::decodeJsonResponse()` baseline).
- [x] 4.4 `lint:twig` on both touched templates; `lint:xliff` on `messages.en.xlf`/`messages.es.xlf`; open PR1 (base: main). NOTE: PR NOT opened per orchestrator instruction (do not push/open PR) — branch `self-registration-admin-approval` (worktree: `gppro-worktrees/self-registration-admin-approval`) is ready for the orchestrator/user to push and open PR1 manually.

## Phase 5: Admin Approve/Reject Actions + Voter (PR2, base: main, after PR1 merges)

- [x] 5.1 RED: `tests/Voter/UserVoterTest.php` — `ROLE_SUPER_ADMIN` (holds `create_user`) granted `approve`/`reject` on any `User` subject; `ROLE_ADMIN` without `create_user` denied both.
- [x] 5.2 GREEN: `src/Voter/UserVoter.php` — add `approve`, `reject` to `ALLOWED_ATTRIBUTES`; branch in `voteOnAttribute()` returning `hasRolePermission($user, 'create_user')` for both, placed alongside the existing `delete`/`2fa` special-case branches (default `_own_profile`/`_other_profile` suffix path does not apply here). Run 5.1 green.
- [x] 5.3 RED: extend `tests/Controller/UserControllerTest.php` — `ROLE_SUPER_ADMIN` POST to `admin_user_approve` on a pending user sets `enabled = true`; same role's POST to `admin_user_reject` sets `rejectedAt`, leaves `enabled = false`, does NOT delete the row; `ROLE_ADMIN` (non-super) POST to either action gets 403 (spec: non-admin denied). DEVIATION: 5.3-5.6 implemented as one coupled RED→GREEN batch — the success-path test needs a real, session-bound CSRF token which (per the existing `extractQuickActionUrl` convention documented in this same test file) can only be read back from the actually-rendered quick-action link, which requires the Phase 5.5/5.6 subscriber wiring to exist. The 403-denial cases (no valid token needed) were independently RED before any controller code existed.
- [x] 5.4 GREEN: `src/Controller/UserController.php` — `approveAction`/`rejectAction`: POST-only, CSRF-protected via URL-embedded token (mirrors the actual established `force-password-reset`/`revoke-remember-me` quick-action convention already in this controller/file, not the older `deleteAction` modal-form pattern — same wiring convention referenced by the design doc), `#[IsGranted('approve'/'reject', 'userToApprove'/'userToReject')]`, routes `admin_user_approve`/`admin_user_reject`.
- [x] 5.5 RED: extend `tests/EventSubscriber/Actions/UserSubscriberTest.php` — `onActions()` adds approve/reject quick-action entries only when `$user->isPendingApproval()` AND `isGranted('approve'/'reject', $user)`; absent for approved/rejected/never-confirmed users and for non-admin viewers.
- [x] 5.6 GREEN: `src/EventSubscriber/Actions/UserSubscriber.php::onActions()` — added a new `approval` submenu with the 2 gated entries, right before the existing `delete` action wiring. Run 5.3-5.6 green together (24/24 UserControllerTest, 6/6 UserSubscriberTest).

## Phase 6: Notification Email (PR2)

- [x] 6.1 RED: extend `UserControllerTest` — `approveAction()` dispatches exactly one email (`self::assertEmailCount(1)`, real Symfony `MailerAssertionsTrait` spy) addressed to the approved user; `rejectAction()` dispatches ZERO emails (`self::assertEmailCount(0)`, explicit negative assertion per D5/D8). Included in the same coupled batch as 5.3.
- [x] 6.2 GREEN: `src/Event/EmailUserApprovedEvent.php` — `extends UserEmailEvent`, mirrors `EmailSelfRegistrationEvent` exactly (empty body).
- [x] 6.3 GREEN: `templates/emails/user_approved.html.twig` — mirrors `emails/confirmation.html.twig` structure.
- [x] 6.4 GREEN: `UserController::approveAction()` — private `generateApprovalEmail()` helper (mirrors `SelfRegistrationController::generateConfirmationEmail`), dispatches `EmailUserApprovedEvent` then `EmailEvent` (same dual-dispatch pattern as `registerAction()` — confirmed via reading `Symfony\Component\EventDispatcher\EventDispatcher::dispatch()` that listeners are matched by exact event class name only, so `EmailSubscriber::onUserMailEvent` — registered for the `UserEmailEvent` parent class string — is never triggered by a subclass instance; the actual send always goes through the explicit `EmailEvent` dispatch, so no double-send risk regardless of `enabled` state).
- [x] 6.5 `translations/email.en.xlf` + `email.es.xlf` — added `user_approved.subject`/`.title`/`.intro`/`.button` keys (EN + ES).

## Phase 7: `UserQuery` Pending-Approval Filter + Badge (PR2)

- [x] 7.1 RED: `tests/Repository/UserRepositoryTest.php` (new) — `pendingApproval=true` returns only `emailConfirmedAt` set + `enabled=false` + `rejectedAt` null; explicitly EXCLUDES a never-confirmed user (`emailConfirmedAt` null, `enabled=false` — spec: distinct from pending), a rejected user, and an approved user (4 separate assertions). Also `tests/Repository/Query/UserQueryTest.php::testPendingApproval` for the getter/setter.
- [x] 7.2 GREEN: `src/Repository/Query/UserQuery.php` — added `?bool $pendingApproval` property, getter/setter, default `null` in `setDefaults()`.
- [x] 7.3 GREEN: `src/Repository/UserRepository.php::getQueryBuilderForQuery()` — WHERE `emailConfirmedAt IS NOT NULL AND enabled = false AND rejectedAt IS NULL` when `$query->getPendingApproval() === true`. Note: caller must set `visibility=SHOW_BOTH` (or `SHOW_HIDDEN`) since the pending filter requires `enabled=false`, which the default `SHOW_VISIBLE` filter (`enabled=true`) would otherwise conflict with — same as how `systemAccount` is an orthogonal filter layered on top of visibility. Run 7.1 green.
- [x] 7.4 GREEN: `templates/user/index.html.twig` — `active` column: added a "Pending approval" badge via `entry.isPendingApproval()`, alongside the existing `label_visible`/`system_account` pattern. Runtime-verified via `UserControllerTest::testIndexActionShowsPendingApprovalBadgeOnlyForPendingUsers`.
- [x] 7.5 `translations/messages.en.xlf` + `messages.es.xlf` — added `pending_approval` badge-label key (EN+ES). `approve`/`reject` action-button labels already existed globally in every locale (reused, not duplicated).

## Phase 8: PR2 Regression + Full Sweep (after both PRs merged)

- [ ] 8.1 Run `phpunit tests/Voter/UserVoterTest.php tests/Controller/UserControllerTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php` plus the Phase 7 query/repository test file — all green.
- [ ] 8.2 Confirm existing `UserController` delete/edit/roles suites and `UserQuery`'s `systemAccount`/`role`/`searchTeams` filters are unaffected (regression).
- [ ] 8.3 `phpstan analyse -c tests/phpstan.neon` — expect exactly 1 pre-existing unrelated error (same baseline as Phase 4).
- [ ] 8.4 `lint:twig` on `templates/user/index.html.twig` and `templates/emails/user_approved.html.twig`; `lint:xliff` on all 4 touched translation files.
- [ ] 8.5 Full sweep: run `SelfRegistrationControllerTest` + `UserControllerTest` + `UserVoterTest` + `UserSubscriberTest` + `UserTest` together once both PRs are merged; confirm zero cross-PR regression.
- [ ] 8.6 Open PR2 (base: main, after PR1 merges).
