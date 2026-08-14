# Admin User Quick Actions Specification

## Purpose

One-click admin remediation actions from the user list/detail, without the
full edit form: "force password reset" (wraps existing
`requiresPasswordReset()`) and "revoke remember-me & force re-auth" (wraps
existing `resetSecuritySignature()`). The revoke action is honestly scoped:
it invalidates remember-me cookies and login-links, not an already-active
session.

## Requirements

### Requirement: Admin can force a password reset in one action

The system MUST allow an admin to trigger a forced password reset on another
user directly from the user list/detail view, setting that user's
`requiresPasswordReset` flag to true, without navigating the full edit form.

#### Scenario: Admin forces a password reset

- GIVEN an admin viewing the user list/detail
- WHEN the admin triggers "force password reset" on a target user
- THEN the target user's `requiresPasswordReset()` flag becomes true

### Requirement: Admin can revoke remember-me & force re-auth in one action

The system MUST allow an admin to invalidate a target user's remember-me
cookies and login-links in one action from the user list/detail, by rotating
that user's security signature via `resetSecuritySignature()`. This action
MUST NOT terminate an already-active session.

#### Scenario: Admin revokes remember-me for a user

- GIVEN an admin viewing the user list/detail
- WHEN the admin triggers "revoke remember-me & force re-auth" on a target
  user
- THEN the target user's security signature changes, invalidating existing
  remember-me cookies and login-links
- AND an already-active session for that user is not terminated

### Requirement: Non-admin cannot reach either quick-action

The system MUST deny access to both quick-actions for any user without
admin privileges.

#### Scenario: Non-admin attempts a quick-action

- GIVEN an authenticated user without admin role
- WHEN they attempt to trigger "force password reset" or "revoke
  remember-me & force re-auth" on another user
- THEN the action is denied (403)
