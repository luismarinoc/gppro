# Tasks: Login Security Management

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~700-950 aggregate (PR1 ~90-120, PR2 ~50-70, PR3 ~200-260, PR4 ~380-500 — 1 new entity, 1 migration, 1 subscriber, 1 repository, 1 query object, 1 controller, 1 template, full RED/GREEN test suite per capability) |
| 400-line budget risk | High as single PR / Medium-High for PR4 alone / Low for PR1-PR3 individually |
| Chained PRs recommended | Yes |
| Suggested split | PR1 (password-policy) -> PR2 (remember-me-policy) -> PR3 (admin-user-quick-actions) -> PR4 (login-audit-trail) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

**Sequencing call (confirms design's smallest-first suggestion, does not bundle):** design flagged smallest-first as a suggestion; I confirm it as final, as 4 separate PRs rather than bundling the 3 small capabilities into one. Reasoning: (1) `admin-user-quick-actions` alone is ~200-260 lines once its 2 controller actions + submenu wiring + 3 test files are counted — not trivial like the other two; bundling all three risks landing at/over 400 lines once test weight is included, precisely the outcome chaining exists to avoid. (2) The proposal's own Rollback Plan states "each item is independently revertible" — bundling three unrelated capabilities into one PR blurs that boundary for no gain, since zero cross-capability dependency exists (design's own finding) and nothing is lost by keeping them separate. (3) Unlike `expense-approval-by-person` (single PR precedent — ONE cohesive capability, ~180-250 lines, one entity touched throughout), this change is four genuinely distinct capabilities each with its own spec, closer in shape to `approval-workflows-expansion`'s multi-capability chaining precedent, just with smaller individual slices. `stacked-to-main` (not `feature-branch-chain`) because zero cross-capability dependency means no coordinated-release/rollback-control need — each slice merges independently as soon as it is green, favoring speed.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Password complexity `Assert\Regex` on `User::$plainPassword` | PR1 (base: main) | `phpunit tests/Entity/UserTest.php` | manual: attempt password change with letters-only in staging, confirm rejected | revert `User.php:215` constraint line only; no schema, no other file touched |
| 2 | `always_remember_me: false` + restore theme's remember-me checkbox | PR2 (base: main, after PR1) | `phpunit tests/Controller/Security/SecurityControllerTest.php` | manual: login unchecked in staging, confirm session-only cookie (no `GPPRO_REMEMBER` cookie set) | revert `security.yaml` flag + `login.html.twig:101` block; no schema |
| 3 | Force-password-reset + revoke-remember-me quick actions via `UserSubscriber`/`UserVoter('password')` | PR3 (base: main, after PR2) | `phpunit tests/Controller/UserControllerTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php tests/Voter/UserVoterTest.php` | manual: admin triggers both actions on a test user in staging, confirm flag/signature change and non-admin 403 | revert 2 `UserController` actions + `UserSubscriber::onActions()` submenu block; no schema |
| 4 | `LoginAttempt` entity + migration + `LoginAuditSubscriber` + `ROLE_SUPER_ADMIN` list | PR4 (base: main, after PR3) | `phpunit tests/Entity/LoginAttemptTest.php tests/EventSubscriber/LoginAuditSubscriberTest.php tests/Controller/LoginAuditControllerTest.php` | `doctrine:migrations:migrate` on test DB, then manual login success + 2 failure scenarios in staging, confirm rows and super-admin-only list | revert migration `down()` (drops `gppro_login_attempts`) + delete entity/subscriber/repository/query/controller/template; zero coupling to PR1-3 |

## Phase 1: Password Policy (PR1, base: main)

- [ ] 1.1 RED: extend `tests/Entity/UserTest.php` — accepts `Passw0rd` (letter+digit); rejects `Password` (letters-only); rejects `12345678` (digits-only); regression: `Pa1` still rejected on length alone.
- [ ] 1.2 GREEN: `src/Entity/User.php:215` — add `#[Assert\Regex(pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/', message: '...')]` alongside the existing `Assert\Length(min: 8, max: 60, ...)`, same validation groups (`Registration`, `PasswordUpdate`, `UserCreate`, `ResetPassword`, `ChangePassword`). Run 1.1 green.
- [ ] 1.3 Confirm all 5 groups still validate identically (single field, single constraint — no per-path duplication needed).
- [ ] 1.4 Run `phpunit tests/Entity/UserTest.php`, `phpstan analyse -c tests/phpstan.neon`, `lint:xliff` if a new message key was added; open PR1 (base: main).

## Phase 2: Remember-Me Policy (PR2, base: main, after PR1 merges)

- [x] 2.1 RED: extend `tests/Controller/Security/SecurityControllerTest.php` — login with `_remember_me` unchecked/absent establishes a session without a persistent remember-me cookie.
- [x] 2.2 GREEN: `config/packages/security.yaml:49` — `always_remember_me: true` -> `false`.
- [x] 2.3 GREEN: `templates/security/login.html.twig:101` — `{% block remember_me %}{{ parent() }}{% endblock %}`, restoring the theme's already-working `_remember_me` checkbox (was blanked by the override).
- [x] 2.4 RED: same file — login form renders with the checkbox unchecked by default.
- [x] 2.5 RED: same file — login with `_remember_me` checked issues a persistent cookie with unchanged lifetime/security properties (secure, httpOnly).
- [x] 2.6 Run `phpunit tests/Controller/Security/SecurityControllerTest.php`, `lint:twig` on `login.html.twig`; open PR2 (base: main, after PR1 merges).

## Phase 3: Admin User Quick Actions (PR3, base: main, after PR2 merges) — COMPLETE

- [x] 3.1 RED: extend `tests/Controller/UserControllerTest.php` — admin POST to `admin_user_force_password_reset` sets the target's `requiresPasswordReset()` to true.
- [x] 3.2 GREEN: `src/Controller/UserController.php` — add `forcePasswordResetAction`, route `admin_user_force_password_reset` (POST-only, CSRF-protected), `#[IsGranted('password', 'userToUpdate')]` (mirrors `deleteAction`'s single-subject pattern at line ~138-140), wraps `setRequiresPasswordReset(true)`.
- [x] 3.3 RED: same file — admin POST to `admin_user_revoke_remember_me` rotates the target's security signature and does NOT invalidate an already-active session (no session-store call made).
- [x] 3.4 GREEN: `src/Controller/UserController.php` — add `revokeRememberMeAction`, route `admin_user_revoke_remember_me`, `#[IsGranted('password', 'userToUpdate')]`, wraps `resetSecuritySignature()`.
- [x] 3.5 RED: extend `tests/EventSubscriber/Actions/UserSubscriberTest.php` — `onActions()` adds a `security` submenu with both actions when `isGranted('password', $user)` is true.
- [x] 3.6 GREEN: `src/EventSubscriber/Actions/UserSubscriber.php::onActions()` — add `$event->addActionToSubmenu('security', ...)` entries for both routes, gated by `$this->isGranted('password', $user)` (reuses existing `UserVoter` `password` attribute — no new voter), placed alongside the other submenu wiring (~lines 60-76). Do NOT touch `templates/user/actions.html.twig` directly.
- [x] 3.7 RED: same test files — non-admin (no `password` grant) POSTing either route gets 403; submenu entries absent from `onActions()` payload for a non-admin viewer.
- [x] 3.8 Run `phpunit tests/Controller/UserControllerTest.php tests/EventSubscriber/Actions/UserSubscriberTest.php tests/Voter/UserVoterTest.php`, `phpstan analyse`; open PR3 (base: main, after PR2 merges).

## Phase 4: Login Audit Trail (PR4, base: main, after PR3 merges)

- [x] 4.1 RED: `tests/Entity/LoginAttemptTest.php` — getters/setters; `user` nullable; `outcome` `success`|`failure`; `createdAt` immutable.
- [x] 4.2 GREEN: `src/Entity/LoginAttempt.php` — `id`, `attemptedUsername` (string 180, not null), `user` (`ManyToOne(User::class)`, nullable, `onDelete: 'SET NULL'`), `ipAddress` (string 45, nullable), `userAgent` (string 255, nullable), `outcome` (string 10, not null), `failureReason` (string 120, nullable), `createdAt` (`datetime_immutable`, not null). Table `gppro_login_attempts`.
- [x] 4.3 `migrations/Version20260814130000.php` — `up()` creates `gppro_login_attempts` with FK `user_id` (`SET NULL`), index `IDX_..._CREATED_AT` on `created_at`, composite index `IDX_..._USER` on `(user_id, created_at)`; `down()` drops the table (via `$schema->dropTable()`, not raw `addSql('DROP TABLE ...')` which the project's `AbstractMigration` forbids).
- [x] 4.4 `src/Repository/LoginAttemptRepository.php` + `src/Repository/Query/LoginAttemptQuery.php` — user/date-range/outcome filters, `getPagerfantaForQuery()`, mirrors `FxRateRepository`/`FxRateQuery` shape (registered in `config/services.yaml` with the `getRepository` factory, same as `FxRateRepository`).
- [x] 4.5 RED: `tests/EventSubscriber/LoginAuditSubscriberTest.php` — `LoginSuccessEvent` persists a `LoginAttempt` with `user` set, `ip`/`userAgent` from `getRequest()`, `outcome=success`. Mirrors `LastLoginSubscriberTest`'s event-construction pattern.
- [x] 4.6 RED: same file — `LoginFailureEvent` for a known username (`BadCredentialsException`) persists `user` set, `attemptedUsername` matching, `outcome=failure`, `failureReason` = exception's SHORT class name via `(new \ReflectionClass($exception))->getShortName()` — NOT `$exception->getMessage()` (avoids leaking sensitive detail, per design's flagged risk). Extra dedicated test `testFailureReasonNeverContainsRawExceptionMessage` proves a sensitive raw message never leaks into `failureReason`.
- [x] 4.7 RED: same file — `LoginFailureEvent` for an unknown username (`UserNotFoundException`) persists `user=null`, `attemptedUsername` set, `outcome=failure`, `failureReason` short class name.
- [x] 4.8 GREEN: `src/EventSubscriber/LoginAuditSubscriber.php` — new subscriber (sibling to `LastLoginSubscriber`, which stays unchanged), subscribes `LoginSuccessEvent` + `LoginFailureEvent`, captures via `getRequest()->getClientIp()` / `User-Agent` header, persists via `LoginAttemptRepository`. Ran 4.5-4.7 green (5/5 tests, 31 assertions).
- [x] 4.9 RED: `tests/Controller/LoginAuditControllerTest.php` — `ROLE_SUPER_ADMIN` GETs the list, applies user/date/outcome filter, renders scoped results.
- [x] 4.10 RED: same file — `ROLE_ADMIN` (non-super) is denied 403 (explicit regression guard per locked decision #5).
- [x] 4.11 RED: same file — records older than any arbitrary age remain queryable without a date filter (no auto-purge).
- [x] 4.12 GREEN: `src/Controller/LoginAuditController.php` — `#[IsGranted('ROLE_SUPER_ADMIN')]` class-level (native `RoleVoter`, not `RolePermissionManager` — non-reassignable to `ROLE_ADMIN`). **Deviation from design**: `indexAction` mirrors `LoginAttemptRepository::getPagerfantaForQuery()` (Pagerfanta-based, task 4.4) but builds the `LoginAttemptQuery` directly from `Request` query params (`ExpenseController`'s simpler idiom) instead of the full `DataTable` + `ToolbarFormTrait` UI framework design suggested as closer to `UserController::indexAction`. Chosen to keep PR4 within the review budget the tasks/design docs already flagged as Medium-High risk — `ToolbarFormTrait::addUsersChoice()` alone pulls in the `UserType` autocomplete stack, which is disproportionate for a read-only audit screen. Every locked spec requirement (filterable by user/date/outcome, `ROLE_SUPER_ADMIN`-only, indefinite retention) is still met and tested; pagination reuses the existing `pagination()` Twig function (`PaginationExtension`/`Pagination` class), so the app's real Pagerfanta pagination widget is not reinvented.
- [x] 4.13 `templates/login_audit/index.html.twig` — filterable list (plain GET form + table), mirrors `templates/expense_approval_level/index.html.twig`'s simpler card+table structure rather than the full `datatable.html.twig` macro system (see 4.12 deviation note).
- [x] 4.14 Ran `phpunit tests/Entity/LoginAttemptTest.php tests/EventSubscriber/LoginAuditSubscriberTest.php tests/Controller/LoginAuditControllerTest.php` — 15/15 tests, 68 assertions, all green. Confirmed migration `Version20260814130000` applies and `down()` reverses cleanly on the test DB via `bin/console doctrine:migrations:migrate` both directions (found and fixed a bug: combining an explicit `addSql('ALTER TABLE ... DROP FOREIGN KEY ...')` with `$schema->dropTable()` in `down()` double-drops the FK and fails — `$schema->dropTable()` alone is schema-diff-based and already emits the FK drop).
- [x] 4.15 `phpstan analyse -c tests/phpstan.neon --no-progress` — exactly 1 pre-existing unrelated error (`QuotationControllerTest::decodeJsonResponse()`), matches baseline. `lint:twig templates/login_audit/index.html.twig` — OK. `lint:xliff` on `translations/messages.en.xlf` + `messages.es.xlf` (new `login_audit.*` keys) — OK. PR4 not opened (per apply-phase instructions: no push/PR from this session).

## Phase 5: Full Regression Sweep (after all 4 PRs merged)

- [ ] 5.1 Run `phpunit tests/Controller/Security/ tests/EventSubscriber/ tests/EventSubscriber/Actions/UserSubscriberTest.php tests/Voter/UserVoterTest.php tests/Entity/UserTest.php tests/Entity/LoginAttemptTest.php tests/Controller/UserControllerTest.php tests/Controller/LoginAuditControllerTest.php` together.
- [ ] 5.2 Confirm `tests/EventSubscriber/LastLoginSubscriberTest.php` is untouched and green — zero regression on existing last-login tracking (`LoginAuditSubscriber` is a new sibling, not a replacement).
- [ ] 5.3 `phpstan analyse -c tests/phpstan.neon --no-progress` — expect exactly 1 pre-existing, unrelated error (`Controller/QuotationControllerTest.php::decodeJsonResponse()`, per the `expense-approval-by-person` verify-report baseline).
- [ ] 5.4 `lint:twig` on `templates/security/login.html.twig` + `templates/login_audit/index.html.twig`; `lint:xliff` on all touched translation files.
- [ ] 5.5 Confirm the migration applies cleanly in sequence on a fresh test DB (`doctrine:migrations:migrate`) and its `down()` reverses cleanly.
