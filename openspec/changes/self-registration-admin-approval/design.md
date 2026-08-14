# Design: Self-Registration Admin Approval

## Technical Approach

Minimal-diff extension of the existing `SelfRegistrationController`/`User`/`UserService` trio (verified via full read of all 4 controller actions, `User.php`, `UserQuery.php`, `UserSubscriber.php`, `UserVoter.php`, `RolePermissionManager` config). Two nullable `datetime_immutable` columns on `gppro_users` (verified table name via `Version20260814100000`), one behavior change in `confirmAction()`, one new template + route, two new admin quick-actions via the confirmed `UserSubscriber::onActions()` convention (same mechanism as sibling `login-security-management`, topic `sdd/login-security-management/design` — independent implementation, no shared code), one new mailer event mirroring `EmailSelfRegistrationEvent`.

## Architecture Decisions

| Decision | Choice | Alternatives rejected | Rationale |
|---|---|---|---|
| State model | `User::$emailConfirmedAt` + `$rejectedAt`, both nullable `datetime_immutable` (mirrors `passwordRequestedAt`'s type, not `lastLogin`'s legacy `DATETIME_MUTABLE`). Add `User::isPendingApproval(): bool` = `emailConfirmedAt !== null && !enabled && rejectedAt === null`. | New `status` enum column | Enum duplicates state already derivable from 2 timestamps + `enabled`; helper method centralizes the 3-way logic (query/controller/template) instead of repeating the boolean expression in 3 places |
| `confirmAction()` | Set `emailConfirmedAt = now`, clear `confirmationToken`, leave `enabled=false`, remove `LoginManager::logInUser()` call entirely, redirect to new route `registration_pending_approval` | Keep `registration_confirmed` route, branch template on enabled | New route is clearer intent; old `registration_confirmed`/`confirmedAction` stay unused-but-untouched (out of scope — still reachable only if ever manually linked, harmless) |
| Reject-then-reregister guard | In `registerAction()`, if `findUserByEmail($email)` returns a user with `rejectedAt !== null`, clear `rejectedAt`/`confirmationToken`/`emailConfirmedAt` on the **same row** and restart the confirmation flow (send new token) instead of creating a duplicate row | (a) hard-block with error message, (b) silently create a second row with the same email | Proposal explicitly wants to block *silent immediate* re-registration — reusing the row still requires a fresh email confirmation + fresh admin approval each time, so it isn't silent; blocking outright would permanently lock out a legitimately-corrected re-application, and `email` is unique so a second row is impossible anyway (`findUserByEmail` uses `findOneBy(['email' => ...])` against a presumably-unique column — confirmed no duplicate-email path exists) |
| Admin actions | New `UserVoter` attributes `approve`, `reject`, gated via `hasRolePermission($user, 'create_user')` (existing `USER` permission set, `ROLE_SUPER_ADMIN`-only in `gppro.yaml` maps). New POST-only CSRF routes `admin_user_approve`/`admin_user_reject` on `UserController`, mirroring `deleteAction`'s form pattern | New registered `approve_user`/`reject_user` permissions | `gppro.yaml:129-134` documents an explicit repo convention: subject-scoped approval actions (expense/timesheet/invoice-payment approvals) are deliberately kept OFF the registered-permission list because `RolePermissionManager` grants a registered permission **globally** when checked without a subject, bypassing per-instance policy. That precedent applies to *level-varying* approvals; user-approval has no per-instance policy — it's a flat trust decision equivalent to `create_user` (both mint a new live account), so reusing the existing `create_user` permission (already `ROLE_SUPER_ADMIN`-only) is the smaller, precedent-consistent diff over inventing new config entries |
| Notification | New `EmailUserApprovedEvent extends UserEmailEvent` (sibling of `EmailSelfRegistrationEvent`), dispatched from `UserController::approveAction()`, same `TemplatedEmail` + `EmailEvent` dispatch pattern as `generateConfirmationEmail()`. No reject email (Locked Decision 8) | — | Direct mirror, zero new abstraction |

## Data Flow

    registerAction() ──(reject-then-reregister? clear old state)──> save User(enabled=false)
    confirmAction() ──sets emailConfirmedAt, clears token──> redirect registration_pending_approval (static page)
    Admin UserController::approveAction() ──sets enabled=true──> dispatch EmailUserApprovedEvent ──> EmailEvent
    Admin UserController::rejectAction() ──sets rejectedAt──> (no email)
    UserQuery::pendingApproval filter ──> UserRepository qb: enabled=false AND emailConfirmedAt IS NOT NULL AND rejectedAt IS NULL

## File Changes

| File | Action | Description |
|---|---|---|
| `src/Entity/User.php` | Modify | Add `$emailConfirmedAt`, `$rejectedAt` columns/getters/setters, `isPendingApproval()` |
| `migrations/VersionYYYYMMDDHHMMSS.php` | Create | 2 nullable `datetime_immutable` columns on `gppro_users`, reversible `down()` |
| `src/Controller/Security/SelfRegistrationController.php` | Modify | `confirmAction()` behavior change; `registerAction()` reject-then-reregister guard |
| `src/Controller/Security/SelfRegistrationController.php` (route) | Modify | New `registration_pending_approval` route + render call |
| `templates/security/self-registration/pending_approval.html.twig` | Create | Mirrors `check_email.html.twig`/`confirmed.html.twig`; renders static message from session-carried email (no `getUser()` — user is no longer auto-logged-in) |
| `templates/emails/user_approved.html.twig` | Create | Mirrors `emails/confirmation.html.twig` |
| `src/Event/EmailUserApprovedEvent.php` | Create | `extends UserEmailEvent`, mirrors `EmailSelfRegistrationEvent` |
| `src/Repository/Query/UserQuery.php` | Modify | Add `?bool $pendingApproval` filter property |
| `src/Repository/UserRepository.php` | Modify | `getQueryBuilderForQuery()`: pending-approval WHERE clause |
| `src/Controller/UserController.php` | Modify | `approveAction()`, `rejectAction()` (POST-only, CSRF, `#[IsGranted]`) |
| `src/EventSubscriber/Actions/UserSubscriber.php` | Modify | 2 quick-action buttons gated by `approve`/`reject` |
| `src/Voter/UserVoter.php` | Modify | Add `approve`, `reject` to `ALLOWED_ATTRIBUTES`, wire to `create_user` permission |
| `templates/user/index.html.twig` | Modify | `active` column: add "pending approval" badge via `entry.isPendingApproval()`, next to existing `label_visible` (same pattern as `system_account` column) |

## Testing Strategy (Strict TDD — RED first)

| Layer | What | Approach |
|---|---|---|
| Unit | `User::isPendingApproval()` 4-state table (never-confirmed/pending/rejected/approved) | Data-provider test |
| Functional | `confirmAction()`: no `enabled=true`, no session auth token set, redirects to `registration_pending_approval` | `WebTestCase`, assert response + DB state + no security token |
| Functional | Reject-then-reregister: same email row reused, `rejectedAt` cleared, new token issued | `WebTestCase` |
| Functional | `UserQuery` pending-approval filter returns only confirmed+disabled+not-rejected | Repository test |
| Functional | `approveAction()`: enables user + dispatches `EmailUserApprovedEvent`; `rejectAction()`: sets `rejectedAt`, asserts zero emails dispatched | `WebTestCase` + event/mailer spy |
| Functional | 403 regression for `ROLE_ADMIN` (non-super-admin) on both actions | `WebTestCase` |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary changed.

## Migration / Rollout

Two nullable columns added to existing `gppro_users` via reversible migration `down()`. No new table. Feature stays dormant unless `isSelfRegistrationActive()` is on (untouched, out of scope).

## PR Sequencing Assessment

Single PR is appropriate (mirrors `expense-approval-by-person` precedent). Estimated surface: 1 migration (~15 lines), `User.php` (~30 lines), controller changes (~60 lines), 1 new event class (~5 lines), 2 templates (~30 lines), `UserQuery`/`UserRepository` (~20 lines), `UserSubscriber`/`UserVoter` (~20 lines), tests (~150-200 lines) — roughly 330-400 total changed lines, at or just under the 400-line review budget. Final call belongs to `sdd-tasks`; flag as borderline — if RED test scaffolding pushes it over, split as PR1 (entity+migration+confirmAction+guard+template) / PR2 (admin actions+email+filter+badge).

## Open Questions

None blocking — all PO decisions locked (proposal Rounds 1-2).
