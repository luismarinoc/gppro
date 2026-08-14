# Self-Registration Admin Approval Specification

## Purpose

Adds a mandatory admin-review gate between "email confirmed" and "account
usable" for the existing (off-by-default) self-registration flow. Email
confirmation alone no longer enables an account or auto-logs the user in;
an admin must explicitly approve (or soft-reject) the account first.

## Requirements

### Requirement: Email confirmation marks pending-approval state without enabling the account

The system MUST, on a valid confirmation-token visit, set
`User::emailConfirmedAt` to the current time and clear the confirmation
token, and MUST NOT set `enabled = true` or call `LoginManager::logInUser()`.

#### Scenario: User confirms email token

- GIVEN a self-registered user with an unconfirmed email and a valid confirmation token
- WHEN the user visits `GET /register/confirm/{token}`
- THEN `emailConfirmedAt` is set to the current time and the token is cleared
- AND `enabled` remains `false`
- AND the user is NOT authenticated/logged in automatically

#### Scenario: Confirmed user sees a static pending-approval page

- GIVEN a user whose `emailConfirmedAt` was just set by confirmation
- WHEN the confirmation redirect renders
- THEN a static informational "pending admin approval" page is shown
- AND the page is NOT a personalized or logged-in view

### Requirement: Pending accounts cannot authenticate

The system MUST deny login for any user with `enabled = false`, regardless
of `emailConfirmedAt`.

#### Scenario: Confirmed-but-unapproved user attempts login

- GIVEN a user with `emailConfirmedAt` set, `enabled = false`, `rejectedAt` null
- WHEN the user attempts login with correct credentials
- THEN authentication is denied

### Requirement: Admin can view pending-approval accounts distinctly

The system MUST let `ROLE_ADMIN`/`ROLE_SUPER_ADMIN` see accounts that are
email-confirmed but neither approved nor rejected, visually distinguishable
from other users in the admin user list.

#### Scenario: Admin views user list with pending accounts

- GIVEN one or more users with `emailConfirmedAt` set, `enabled = false`, `rejectedAt` null
- WHEN a `ROLE_ADMIN` views the user admin list
- THEN those users are flagged/filterable as "pending approval"
- AND they are visually distinct from enabled and never-confirmed users

### Requirement: Never-confirmed users are excluded from the pending-approval list

The system MUST NOT include users with `emailConfirmedAt` null in the
pending-approval list or filter, even though their `enabled` is also `false`.

#### Scenario: Unconfirmed registrant is not shown as pending

- GIVEN a user who registered but never confirmed their email (`emailConfirmedAt` null)
- WHEN an admin views the pending-approval list/filter
- THEN that user does NOT appear in it

### Requirement: Admin approve action enables the account and notifies the user

The system MUST let an admin approve a pending account, setting
`enabled = true`, and MUST send an approval-notification email to the user.

#### Scenario: Admin approves a pending account

- GIVEN a pending account (`emailConfirmedAt` set, `enabled = false`, `rejectedAt` null)
- WHEN the admin triggers the approve action
- THEN `enabled` becomes `true`
- AND an approval-notification email is sent to the user

### Requirement: Admin reject action sets a soft rejected state without enabling or emailing

The system MUST let an admin reject a pending account by setting
`rejectedAt` to the current time, MUST NOT delete the user row, MUST leave
`enabled = false`, and MUST NOT send any email.

#### Scenario: Admin rejects a pending account

- GIVEN a pending account (`emailConfirmedAt` set, `enabled = false`, `rejectedAt` null)
- WHEN the admin triggers the reject action
- THEN `rejectedAt` is set to the current time and the row is NOT deleted
- AND `enabled` remains `false`
- AND no email is sent

### Requirement: A rejected email cannot silently re-register to bypass rejection

The system MUST check, in `registerAction()`, for an existing user with the
submitted email who has `rejectedAt` set, and MUST handle that case
distinctly from a fresh registration (block or route to a distinct outcome),
rather than silently issuing a new confirmation flow.

#### Scenario: Rejected applicant attempts re-registration with the same email

- GIVEN a user record exists with a given email and `rejectedAt` set
- WHEN a registration attempt submits that same email
- THEN registration is blocked/handled distinctly from a fresh registration
- AND no new confirmation token silently bypasses the prior rejection

### Requirement: Non-admin users cannot reach approve/reject actions

The system MUST deny access to the approve and reject actions for any user
without admin privileges.

#### Scenario: Non-admin attempts approve/reject

- GIVEN an authenticated user without admin role
- WHEN they attempt to trigger approve or reject on a pending account
- THEN the action is denied (403)
