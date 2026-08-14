# Password Policy Specification

## Purpose

Extend the existing length-only password rule (`Length(min:8, max:60)`) on
`User::$plainPassword` with a complexity constraint requiring at least one
letter and one digit. No expiration, history, or breach-check is introduced.
The constraint lives on the entity field, so it applies uniformly wherever
that field is validated — user creation, self-service password change, and
admin-initiated reset all share this single entry point.

## Requirements

### Requirement: Password must contain a letter and a digit

The system MUST reject a password that does not contain at least one letter
(`[A-Za-z]`) AND at least one digit (`\d`), via an
`Assert\Regex` constraint (`/(?=.*[A-Za-z])(?=.*\d)/`) applied alongside the
existing `Length(min:8, max:60)` constraint on the password field.

#### Scenario: Password meets length, letter, and digit requirements

- GIVEN a new password of at least 8 characters containing both a letter and
  a digit (e.g. `Passw0rd`)
- WHEN the password is validated on any password-set path
- THEN validation succeeds

#### Scenario: Password has letters only, no digit

- GIVEN a password of at least 8 characters containing only letters (e.g.
  `Password`)
- WHEN the password is validated
- THEN validation fails with a clear complexity error

#### Scenario: Password has digits only, no letter

- GIVEN a password of at least 8 characters containing only digits (e.g.
  `12345678`)
- WHEN the password is validated
- THEN validation fails with a clear complexity error

### Requirement: Minimum length rule still applies

The system MUST continue to reject passwords shorter than 8 characters,
regardless of letter/digit composition (regression guard on the existing
`Length` constraint).

#### Scenario: Password below minimum length

- GIVEN a password shorter than 8 characters, even if it contains both a
  letter and a digit (e.g. `Pa1`)
- WHEN the password is validated
- THEN validation fails

### Requirement: Rule applies uniformly across all password-set paths

The system MUST enforce the same complexity constraint on every path that
sets or changes a password: initial user creation, self-service password
change, and admin-initiated reset. This is a single field-level constraint
on `User::$plainPassword`, not a per-path duplicate rule.

#### Scenario: Constraint enforced regardless of entry point

- GIVEN a password that fails the letter+digit requirement
- WHEN it is submitted through user creation, self-service change, or
  admin-initiated reset
- THEN validation fails identically on every path
