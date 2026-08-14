# Login Audit Trail Specification

## Purpose

Persist every login attempt (success and failure) via a new `LoginAttempt`
entity, hooked into the existing security event pipeline through a new
`LoginAuditSubscriber`. Records are kept indefinitely (no purge job in this
change) and are queryable only by `ROLE_SUPER_ADMIN`.

## Requirements

### Requirement: Successful login is recorded

The system MUST persist a `LoginAttempt` record for every successful login,
capturing the authenticated user, IP address, user-agent, timestamp, and
`outcome=success`.

#### Scenario: User logs in successfully

- GIVEN a registered user with valid credentials
- WHEN the user submits a successful login
- THEN a `LoginAttempt` row is created with `user` set to that user,
  `ip`, `userAgent`, `createdAt` populated, and `outcome=success`

### Requirement: Failed login attempt is recorded

The system MUST persist a `LoginAttempt` record for every failed login,
including attempts against a username that does not exist. The `user`
foreign key MUST be nullable so unknown-username attempts are still logged;
`attemptedUsername` MUST always be captured as submitted.

#### Scenario: Failed login with an existing username

- GIVEN a registered user
- WHEN a login attempt for that user's username fails (wrong password)
- THEN a `LoginAttempt` row is created with `user` set, `attemptedUsername`
  matching the submitted value, `outcome=failure`, and `failureReason`
  populated

#### Scenario: Failed login with an unknown username

- GIVEN no registered user matches the submitted username
- WHEN the login attempt fails
- THEN a `LoginAttempt` row is created with `user=null`, `attemptedUsername`
  set to the submitted value, `outcome=failure`, and `failureReason`
  populated

### Requirement: Audit list is restricted to ROLE_SUPER_ADMIN

The system MUST expose an admin-only login audit list view, accessible only
to users holding `ROLE_SUPER_ADMIN`, filterable by user, date, and outcome.
`ROLE_ADMIN` alone MUST NOT grant access.

#### Scenario: Super-admin views the audit list

- GIVEN an authenticated user with `ROLE_SUPER_ADMIN`
- WHEN they open the login audit list and apply a filter (user, date range,
  or outcome)
- THEN the list renders, scoped to the applied filter

#### Scenario: Non-super admin is denied access

- GIVEN an authenticated user with `ROLE_ADMIN` but not `ROLE_SUPER_ADMIN`
- WHEN they attempt to open the login audit list
- THEN access is denied (403)

### Requirement: Audit records are retained indefinitely

The system MUST NOT automatically delete or expire `LoginAttempt` records.
No retention/purge mechanism is part of this change.

#### Scenario: Old records remain queryable

- GIVEN `LoginAttempt` records older than any arbitrary age
- WHEN a super-admin queries the audit list without a date filter
- THEN those older records are still returned (no automatic deletion has
  occurred)
