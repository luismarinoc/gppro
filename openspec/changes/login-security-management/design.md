# Design: Login Security Management

## Technical Approach

Four independent, additive capabilities on existing Symfony 6.4/Doctrine primitives — no new infra, no shared code path between them (confirmed while reading `LastLoginSubscriber`, `security.yaml`, `UserVoter`, `RolePermissionManager`, `UserController`, `UserSubscriber`, and the login template chain). Each capability reuses an existing convention rather than inventing one: event-subscriber pattern for the audit trail, `UserVoter` attribute for quick-actions, native Symfony `RoleVoter` for the super-admin gate, and a single template-block flip for remember-me.

## Architecture Decisions

### Decision: Audit hook — event subscriber, not `UserChecker`
**Choice**: New `LoginAuditSubscriber`, sibling to `LastLoginSubscriber`, subscribed to `Symfony\Component\Security\Http\Event\LoginSuccessEvent` and `Symfony\Component\Security\Http\Event\LoginFailureEvent` (verified FQCN in `vendor/symfony/security-http/Event/LoginFailureEvent.php`).
**Alternatives considered**: `UserChecker::checkPreAuth/checkPostAuth` — rejected: fires only on user *load*, never on bad-credentials or unknown-username failures (the majority of audit-worthy failures), and only after a `User` was already resolved.
**Rationale**: both events expose `getRequest(): Request` directly (no `RequestStack` needed) for IP (`getClientIp()`) and user-agent. `LoginFailureEvent::getException()` gives the failure class for `failureReason`. Zero existing failure listener confirmed via repo-wide grep.

### Decision: `LoginAttempt` — nullable user FK, indexed for filters
**Choice**: `id`, `attemptedUsername` (string 180, not null — captures the raw `_username` POST field, no username-parameter override in `security.yaml`), `user` (`ManyToOne(User::class)`, nullable, `onDelete: SET NULL`), `ipAddress` (string 45, nullable — IPv6-safe), `userAgent` (string 255, nullable), `outcome` (string 10, not null: `success`|`failure`), `failureReason` (string 120, nullable), `createdAt` (`datetime_immutable`, not null). Table `gppro_login_attempts` (matches `gppro_users`/`gppro_activities` convention). Indexes: `IDX_..._CREATED_AT` and `IDX_..._USER` (composite `(user_id, created_at)` covers the filterable list's common query shape).
**Rationale**: mirrors `Version20260813170000`'s DDL style (`AbstractMigration`, explicit named FKs/indexes, reversible `down()`).

### Decision: Password regex — one constraint, one field
**Choice**: Add `#[Assert\Regex(pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/', message: '...')]` on `User::$plainPassword` (line ~215), same validation groups as the existing `Assert\Length(min:8, max:60, ...)`.
**Rationale**: confirmed `plainPassword` is the single password-input field across all groups (`Registration`, `PasswordUpdate`, `UserCreate`, `ResetPassword`, `ChangePassword`) — proposal's "one field, one constraint" framing is accurate, no `Password.php`/DTO exists.

### Decision: Quick-actions wired via `UserSubscriber`, not `actions.html.twig` directly
**Choice**: `templates/user/actions.html.twig` is a thin macro that dispatches `actions.user`; real wiring is `src/EventSubscriber/Actions/UserSubscriber::onActions()`. Add two submenu entries there (new `security` submenu, pattern at lines 53-58), gated by `$this->isGranted('password', $user)` — reuses `UserVoter`'s existing `password` attribute (no new voter). New POST-only, CSRF-protected routes on `UserController` (mirrors `deleteAction`'s single-subject `#[IsGranted('password', 'user')]` pattern): `admin_user_force_password_reset` (wraps `setRequiresPasswordReset(true)`) and `admin_user_revoke_remember_me` (wraps `resetSecuritySignature()`).
**Alternatives considered**: editing `actions.html.twig` directly (proposal's literal Affected-Areas line) — rejected, contradicts the codebase's own action-menu convention; new dedicated controller — rejected, both actions are simple single-entity mutations matching `UserController::deleteAction`'s shape.

### Decision: Audit-list gate — native `ROLE_SUPER_ADMIN`, not `RolePermissionManager`
**Choice**: `#[IsGranted('ROLE_SUPER_ADMIN')]` on `LoginAuditController` (Symfony's built-in `RoleVoter`, same mechanism as `switch_user: { role: ROLE_ALLOWED_TO_SWITCH }`).
**Alternatives considered**: adding a `login_audit` entry to `RolePermissionManager::SUPER_ADMIN_PERMISSIONS` (the `role_permissions`/`view_all_data` pattern) — rejected: registering it as a `permissionName` makes it reassignable to `ROLE_ADMIN` via `PermissionController`'s UI, directly contradicting the PO's locked "not `ROLE_ADMIN`" decision. A raw role check is non-configurable.
**List pattern**: mirrors `UserController::indexAction` (`UserQuery`+`DataTable`+Pagerfanta+toolbar filter form) — closer precedent than `ExpenseApprovalLevelController` (no filters). New `LoginAttemptQuery`/`LoginAttemptRepository::getPagerfantaForQuery()`.

### Decision: Remember-me — one-line template fix, not new markup
**Choice**: `security.yaml`: `always_remember_me: true` → `false`. `templates/security/login.html.twig:101` currently reads `{% block remember_me %}{% endblock %}` — an explicit override that BLANKS the base theme block. The theme (`vendor/kevinpapst/tabler-bundle/templates/security.html.twig:121-128`) already renders a working `name="_remember_me"` checkbox matching Symfony's default `remember_me` form-field convention (the firewall's `name: GPPRO_REMEMBER` key is the *cookie* name, unrelated). Fix: change line 101 to `{% block remember_me %}{{ parent() }}{% endblock %}`.
**Rationale**: zero new markup risk; restores an already-built, already-styled checkbox.

## Data Flow

    LoginSuccessEvent/LoginFailureEvent ──→ LoginAuditSubscriber ──→ LoginAttempt (Doctrine)
                                                                             │
    LoginAuditController (ROLE_SUPER_ADMIN) ──→ LoginAttemptRepository ─────┘

    UserController quick-action route ──→ UserVoter('password') ──→ User::setRequiresPasswordReset()
                                                                 └──→ User::resetSecuritySignature()

## File Changes

| File | Action | Description |
|------|--------|--------------|
| `src/Entity/LoginAttempt.php` | Create | Audit entity |
| `migrations/VersionXXXX.php` | Create | `gppro_login_attempts` table, reversible |
| `src/EventSubscriber/LoginAuditSubscriber.php` | Create | Success+failure event capture |
| `src/Repository/LoginAttemptRepository.php` | Create | Pagerfanta query support |
| `src/Repository/Query/LoginAttemptQuery.php` | Create | user/date/outcome filters |
| `src/Controller/LoginAuditController.php` | Create | `ROLE_SUPER_ADMIN`-only list |
| `templates/login_audit/index.html.twig` | Create | Filterable list |
| `src/Entity/User.php:~215` | Modify | Add `Assert\Regex` |
| `src/Controller/UserController.php` | Modify | 2 new POST actions |
| `src/EventSubscriber/Actions/UserSubscriber.php` | Modify | Wire 2 submenu buttons |
| `config/packages/security.yaml` | Modify | `always_remember_me: false` |
| `templates/security/login.html.twig` | Modify | 1-line block fix |

## Testing Strategy (Strict TDD — RED first)

| Layer | What | Approach |
|-------|------|----------|
| Unit | `LoginAuditSubscriber` captures success+failure | Mock repository, assert IP/UA/outcome, per `LastLoginSubscriberTest` convention |
| Unit | `LoginAttempt` entity/repository | Entity getters/setters, repository query filters |
| Unit | Password regex accept/reject | Validator component, table-driven cases |
| Functional | Audit list `ROLE_SUPER_ADMIN`-only | Assert 200 for super-admin, 403 for `ROLE_ADMIN` (regression) |
| Functional | Force-reset / revoke-remember-me | Flag flips / signature changes; 403 for non-admin |
| Functional | Remember-me unchecked-by-default | Assert session-only cookie when `_remember_me` absent |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Migration / Rollout

New `gppro_login_attempts` table via reversible migration `down()`. No schema change for password policy (validation-only). All four capabilities independently revertible per proposal's Rollback Plan.

## PR Sequencing Assessment

Not single-PR. Login-audit-trail alone (entity + migration + subscriber + repository + query + controller + template + its RED tests) will likely approach or exceed the 400-line review budget by itself. The other three capabilities are each small (1-3 files, no shared dependency) and safely stackable/parallel. Recommend chained PRs, smallest-first: (1) `password-policy`, (2) `remember-me-policy`, (3) `admin-user-quick-actions`, (4) `login-audit-trail`. No cross-capability dependency exists — final ordering and slicing is `sdd-tasks`' call.

## Open Questions

None blocking — all four capabilities have concrete, precedent-backed implementation paths.
