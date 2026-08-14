# Proposal: Self-Registration Admin Approval

## Intent

gppro ships a fully-built but off-by-default self-registration flow (`SelfRegistrationController`, gated by `SystemConfiguration::isSelfRegistrationActive()`). Today, email confirmation alone (`GET /register/confirm/{token}`) immediately sets `User::enabled = true` and auto-logs the user in via `LoginManager`. If self-registration is ever turned on, any person who can receive email at an arbitrary address gets a live, working account with zero human review — unacceptable for an internal time-tracking tool. This change adds a mandatory admin-approval gate between "email confirmed" and "account usable," matching the PO's explicit decision to keep BOTH steps.

## Scope

### In Scope
- Change `registration_confirm`: clear token, mark email confirmed, do NOT enable, do NOT auto-login.
- New "pending admin approval" informational page shown after confirmation (mirrors `check_email.html.twig`/`confirmed.html.twig`).
- Distinguish "never confirmed" vs "confirmed, awaiting admin" state on `User`.
- Admin-facing pending-approval visibility + approve/reject actions, reusing the `UserSubscriber::onActions()` quick-actions convention (same wiring pattern documented in sibling change `login-security-management`'s design, topic `sdd/login-security-management/design` — pattern only, no code dependency; that convention already exists in `UserController`/`UserSubscriber` today, independent of whether the sibling change ships).
- Approval-notification email on approve (mirrors `EmailSelfRegistrationEvent` pattern).

### Out of Scope
- `SystemConfiguration::isSelfRegistrationActive()` toggle itself — untouched.
- Token generation/expiry security properties of email confirmation — untouched.
- LDAP/SAML provisioning (`src/Ldap/*`, `src/Saml/*`) — confirmed no shared code with `SelfRegistrationController`; JIT auto-provisioning there is a separate mechanism.
- Sibling change `login-security-management`'s four capabilities (audit trail, password policy, quick-actions impl, remember-me) — separate SDD change; not implemented yet either.

## Capabilities

### New Capabilities
- `self-registration-admin-approval`: post-email-confirmation admin review gate — pending-state tracking, admin approve/reject actions, notification email.

### Modified Capabilities
None (no pre-existing self-registration spec in `openspec/specs/`).

## Locked Decisions

1. **State model**: add `User::emailConfirmedAt` (nullable `datetime_immutable`). `enabled=false` alone cannot distinguish "awaiting email" from "awaiting admin" — both look identical today. Minimal addition: one nullable column, no new entity.
2. **Post-confirmation UX**: `confirmAction()` sets `emailConfirmedAt`, clears token, leaves `enabled=false`, does NOT call `LoginManager::logInUser()`. Redirect target renders a static "pending admin approval" message (no personalized/logged-in state required).
3. **Admin surface**: reuse existing `templates/user/index.html.twig` admin list + `UserSubscriber::onActions()` quick-actions convention (approve/reject buttons gated by a `UserVoter` attribute, POST-only CSRF routes on `UserController` mirroring `deleteAction`), plus a "pending approval" `UserQuery` filter/badge. Rejected: a wholly separate admin screen — more effort, no reuse of an already-established repo convention.
4. **Rejection semantics — OPEN QUESTION for PO**: default proposal is a soft `rejectedAt` (nullable datetime) state, row kept, not hard-deleted — preserves audit trail (who rejected, when) and blocks silent immediate re-registration with the same email. Tradeoff: retains a rejected applicant's PII; hard-delete avoids that but loses the audit trail. Needs explicit PO confirmation.
5. **Notification — flagged for PO reaction**: default proposal sends an approval-notification email on approve (mirrors the existing confirmation-email pattern). Not proposing a rejection email by default (avoids confirming account existence to a rejected applicant) — PO should confirm.
6. **Self-registration toggle**: this entire flow (existing self-registration + new approval gate) only activates when `isSelfRegistrationActive()` is on. This change does not touch that toggle.

## Approach

Minimal-diff extension of the existing controller/entity/service trio: one new nullable column, one behavior change in `confirmAction()`, one new template, two new admin quick-actions wired through the existing `UserSubscriber`/`UserController` convention, one new notification email. No new infrastructure.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Feature is currently OFF; low prod exposure until enabled | Low | N/A — change is safe to ship dormant |
| Confirms account existence to unauthorized parties via approve email | Low | No reject-email by default (see Decision 5) |
| Rejection semantics ambiguity ships wrong default | Medium | Flagged as open question above; PO sign-off required before `sdd-spec` |

## Rollback Plan

Revert the `confirmAction()` behavior change and drop `emailConfirmedAt` column via migration `down()`; admin quick-actions and email are additive and can be disabled independently without touching core flow.

## Success Criteria

- [ ] Email-confirmed-but-unapproved accounts cannot log in.
- [ ] Admin can see and approve/reject pending accounts from the existing user admin list.
- [ ] Approved accounts behave exactly as today's confirmed accounts once enabled.

## Locked Decisions — Round 2 (PO-confirmed, resolves prior open questions)

7. **Rejection semantics**: CONFIRMED as the default proposal — soft `rejectedAt`
   (nullable datetime) state, row kept, not hard-deleted. Preserves audit
   trail (who rejected, when) and blocks silent immediate re-registration
   with the same email.
8. **Rejection notification**: CONFIRMED — no email sent on reject. Avoids
   confirming account existence/rejection to an unauthorized applicant.
   Approve still sends a notification email (per Decision 5, unchanged).
