# Remember-Me Policy Specification

## Purpose

Replace the blanket `always_remember_me: true` behavior with an opt-in
"Remember me" checkbox on the login form (native Symfony `remember_me`
support), unchecked by default. Narrows persistent-cookie exposure on shared
machines and restores explicit user choice.

## Requirements

### Requirement: Remember-me cookie requires explicit opt-in

The system MUST NOT issue a persistent remember-me cookie unless the user
explicitly checks "Remember me" at login. `security.yaml`
`always_remember_me` MUST be `false`.

#### Scenario: User logs in without checking "Remember me"

- GIVEN the login form with the "Remember me" checkbox unchecked
- WHEN the user submits valid credentials
- THEN the session is established without a persistent remember-me cookie
  (session-only)

#### Scenario: User logs in with "Remember me" checked

- GIVEN the login form with the "Remember me" checkbox checked
- WHEN the user submits valid credentials
- THEN a persistent remember-me cookie is issued, with the same
  lifetime/security properties (e.g. secure, httpOnly) as before this change

### Requirement: Checkbox defaults to unchecked

The system MUST render the "Remember me" checkbox unchecked by default on
the login form.

#### Scenario: Login form initial state

- GIVEN a user navigating to the login page
- WHEN the login form is rendered
- THEN the "Remember me" checkbox is unchecked
