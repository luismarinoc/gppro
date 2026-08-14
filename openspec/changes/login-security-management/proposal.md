# Proposal: Login Security Management

## Intent

The PO asked to make login "easier to manage" and "more secure". Exploration
(`sdd/login-system-improvements/explore`) found four concrete, code-level gaps
independent of the already-solid SSO infrastructure: no persisted login audit
trail, a length-only password policy, no fast admin remediation actions, and a
silent 7-day remember-me cookie on every login. This change closes those four
gaps.

## Scope

### In Scope
1. **Login audit trail** — persist every login attempt (success + failure),
   queryable by admins.
2. **Password complexity policy** — extend the length-only rule.
3. **Admin quick-actions** — one-click force-password-reset and
   remember-me/login-link revocation from the user list/detail, without the
   full edit form.
4. **`always_remember_me` review** — concrete recommendation, not left open.

### Out of Scope (locked, PO-confirmed)
- LDAP/SAML SSO activation — pure ops/DB-config action, no code change.
- Mandatory 2FA for any role — explicitly rejected.
- `PasswordResetController` self-service flow — already solid, untouched.
- True active-session kill (see decision #3 below) — infra follow-up.

## Capabilities

### New Capabilities
- `login-audit-trail`: persisted login attempt log (success/failure, IP,
  user-agent, timestamp) + admin-only list view.
- `password-policy`: complexity constraint on password creation/change.
- `admin-user-quick-actions`: one-click force-reset and revoke-remember-me
  actions on the user list/detail.
- `remember-me-policy`: opt-in remember-me via login-form checkbox.

### Modified Capabilities
None — no existing `openspec/specs/` capability governs auth/login today.

## Locked Decisions (this session)

1. **Audit trail**: new `LoginAttempt` entity (user FK nullable — unknown
   usernames still logged), fields: attemptedUsername, user, ip, userAgent,
   outcome, failureReason, createdAt. Hooks: `LoginSuccessEvent` +
   `AuthenticationFailureEvent`/`LoginFailureEvent` (new
   `LoginAuditSubscriber`, sibling to `LastLoginSubscriber`, which is left
   unchanged). New admin-only list view, filterable by user/date/outcome.
2. **Password complexity**: keep `Length(min:8, max:60)`, add
   `Assert\Regex` requiring at least one letter AND one digit
   (`/(?=.*[A-Za-z])(?=.*\d)/`). No expiration/history/breach-check — not
   requested, adds support burden. Applies to all password-set paths (one
   field, one constraint).
3. **Quick-actions**: "Force password reset" wraps existing
   `requiresPasswordReset()`. "Revoke session" ships as **"revoke
   remember-me & force re-auth"**, wrapping existing
   `resetSecuritySignature()` — honestly labeled, not "kill session now".
   True active-session kill needs a session-storage-backed mechanism
   (e.g. Doctrine/Redis session handler) — flagged as an explicit follow-up,
   not built here.
4. **`always_remember_me`**: flip `security.yaml` `always_remember_me` to
   `false`, add a "Remember me" checkbox to the login form (native Symfony
   `remember_me` form support). Restores user choice, narrows persistent
   -cookie exposure on shared machines, and stops the blanket 2FA-skip for
   users who never opted in. This is a visible UX change riding with the
   security fix — called out for PO reaction.

## Approach

Additive, low-risk changes on existing primitives — no new infra frameworks.
Audit trail: new entity + migration + subscriber + read-only admin
controller/template, mirroring existing admin list patterns. Password
policy: single validation constraint. Quick-actions: new controller actions
wired into the existing event-based action-menu system
(`templates/user/actions.html.twig`). Remember-me: config flip + one
template checkbox.

## Affected Areas

| Area | Impact | Description |
|------|--------|--------------|
| `src/Entity/LoginAttempt.php` | New | Audit trail entity |
| `migrations/VersionXXXX.php` | New | `login_attempt` table (reversible down) |
| `src/EventSubscriber/LoginAuditSubscriber.php` | New | Persists success/failure events |
| `src/Controller/LoginAuditController.php` | New | Admin-only audit list view |
| `templates/login_audit/index.html.twig` | New | Audit list template |
| `src/Entity/User.php:215` | Modified | Add `Assert\Regex` complexity rule |
| `src/Controller/UserController.php` | Modified | Force-reset / revoke-remember-me actions |
| `templates/user/actions.html.twig` | Modified | Wire quick-action buttons |
| `config/packages/security.yaml` | Modified | `always_remember_me: true` → `false` |
| Login form template | Modified | Add remember-me checkbox |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Unbounded audit table growth (no retention policy yet) | Medium | Retention is an open question below — resolve before build |
| "Revoke session" label misread as true session kill | Medium | Honest UI copy + admin docs; follow-up tracked separately |
| `always_remember_me` flip changes visible login UX | Low-Med | Communicate via release notes; checkbox defaults unchecked |
| Complexity rule rejects some existing valid passwords on next change only | Low | Only applies at password-set time, not retroactively |

## Rollback Plan

Each item is independently revertible: drop the audit table via the
migration's `down()`, revert the `User.php` constraint, revert
`security.yaml` + template checkbox, remove quick-action routes/buttons. No
destructive changes to existing `User` data.

## Dependencies

None external — uses existing Doctrine migrations, Symfony security events,
already-installed Scheb 2FA bundle (unaffected).

## Success Criteria

- [ ] Admin can view failed + successful login attempts with user/date/outcome filters
- [ ] New/changed passwords require ≥1 letter + ≥1 digit
- [ ] Admin can force-reset or revoke-remember-me in ≤2 clicks from the user list
- [ ] Login no longer silently issues a 7-day cookie; users see an explicit choice

## Locked Decisions — Round 2 (PO-confirmed, resolves prior question round)

5. **Audit retention & visibility**: rows kept INDEFINITELY (no purge job in
   this change), viewable by `ROLE_SUPER_ADMIN` only (not `ROLE_ADMIN`).
6. **Password rule strength**: CONFIRMED as proposed — `Length(min:8,
   max:60)` + `Assert\Regex` requiring ≥1 letter AND ≥1 digit. No special
   character requirement, no expiration.
7. **`always_remember_me`**: CONFIRMED — flip to `false`, add an opt-in
   "Remember me" checkbox on the login form (unchecked by default).
8. **"Revoke session" framing**: CONFIRMED — ships as "revoke remember-me &
   force re-auth", honestly scoped to existing `resetSecuritySignature()`
   primitives. True active-session kill explicitly deferred as a separate
   future infra change, not built here.

## Open Item (non-blocking, informational only)
2. **Admin-UI gap found during investigation**: `SystemConfigurationController`
   exposes no `ldap.activate`/`saml.activate` toggle today — activating SSO
   currently requires a direct DB/CLI action, not a config-screen click. Out
   of scope for this change; flagged as a possible small future change, not
   pursued here.
