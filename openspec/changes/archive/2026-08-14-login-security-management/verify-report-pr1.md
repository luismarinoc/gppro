# Verify Report: Login Security Management — PR1 (Password Policy)

**Change**: `login-security-management`
**Scope**: PR1 / Phase 1 only (password-policy domain — 1 of 4 chained PRs)
**Branch**: `login-security-management` (worktree: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/login-security-management`), tip `dbc79f9`, 1 commit ahead of `main`@`c194c8b`
**Mode**: Full artifact verification (proposal + spec + design + tasks all present, retrieved from Engram)
**Verdict**: **PASS WITH WARNINGS**

## Completeness Table

| Task | Status | Evidence |
|---|---|---|
| 1.1 RED — 4 new tests in `UserTest.php` | [x] Complete | `testPasswordAcceptsLetterAndDigit`, `testPasswordRejectsLettersOnly`, `testPasswordRejectsDigitsOnly`, `testPasswordBelowMinimumLengthIsStillRejected` present at lines 468-513 |
| 1.2 GREEN — `Assert\Regex` on `User.php:216` | [x] Complete | verified in source, byte-for-byte matches design decision #3 |
| 1.3 Confirm 5 groups identical | [x] Complete | structural confirmation, single shared attribute, same `groups` array as adjacent `Assert\Length` |
| 1.4 Run phpunit + phpstan, open PR1 | [x] Complete | independently re-executed below, matches apply report |

4/4 Phase 1 tasks genuinely complete — verified against source, not just checkbox trust.

## Build/Test Evidence (independently re-executed in the worktree)

- `BOOTSTRAP_RESET_DATABASE=0 vendor/bin/phpunit tests/Entity/UserTest.php` → **OK (34 tests, 328 assertions)**. Exit 0. Matches apply-progress report exactly.
- `vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress` → **exactly 1 pre-existing unrelated error** (`QuotationControllerTest::decodeJsonResponse()` — `return.type`), matching the documented baseline from prior verify-reports in this repo (e.g. `expense-access-scoping`). Exit 1 (phpstan's own documented, accepted baseline state — not introduced by this PR).
- Worktree integrity check: `vendor` is a real directory (`file vendor` → "directory"), not a symlink — confirms the fix for the documented worktree-vendor-symlink autoload gotcha was applied correctly.
- Reflection check: `(new ReflectionClass('App\Entity\User'))->getFileName()` resolves to `.../gppro-worktrees/login-security-management/src/Entity/User.php` — exact match to the worktree's own file, confirming tests exercise this worktree's own edited code, not a stale copy.

## Spec Compliance Matrix (password-policy domain — PR1 scope: 3 requirements / 5 scenarios)

| Requirement | Scenario | Status | Evidence |
|---|---|---|---|
| Password must contain a letter and a digit | Meets length+letter+digit (`Passw0rd`) | PASS | `testPasswordAcceptsLetterAndDigit` — 0 violations |
| Password must contain a letter and a digit | Letters only, no digit (`Password`) | PASS | `testPasswordRejectsLettersOnly` — 1 violation on `plainPassword` (length constraint met at exactly 8 chars, so the violation is attributable only to the new Regex constraint) |
| Password must contain a letter and a digit | Digits only, no letter (`12345678`) | PASS | `testPasswordRejectsDigitsOnly` — 1 violation on `plainPassword` (same isolation logic) |
| Minimum length rule still applies | Below minimum length (`Pa1`) | PASS | `testPasswordBelowMinimumLengthIsStillRejected` — 1 violation on `plainPassword` (regression guard for pre-existing `Assert\Length` rule) |
| Rule applies uniformly across all password-set paths | Constraint enforced identically via any of the 3 entry points | WARNING | Only the `ChangePassword` validation group is exercised at runtime; `Registration`/`UserCreate`/`ResetPassword`/`PasswordUpdate` groups are verified structurally (identical `groups` array on the single shared attribute), not via a direct runtime assertion against each group |

## Correctness (verified from actual assertion bodies, not pass/fail counts alone)

- `Password` (8 chars, letters-only) and `12345678` (8 digits) both satisfy `Length(min:8,max:60)`, so the single violation asserted in each rejecting test is attributable only to the new `Assert\Regex` constraint — correctly isolates the new rule from the pre-existing length rule.
- `Passw0rd` produces zero violations — correctly accepts a compliant letter+digit password.
- `Pa1` (3 chars) still produces exactly 1 violation — regression guard for the pre-existing length rule holds independent of the new regex.
- `src/Entity/User.php:216`: `#[Assert\Regex(pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/', message: 'Password must contain at least one letter and one digit.', groups: ['Registration', 'PasswordUpdate', 'UserCreate', 'ResetPassword', 'ChangePassword'])]` — matches design decision #3 exactly, same 5 groups as the adjacent `Assert\Length`.

## Design Coherence

Design decision #3 ("Password regex — one constraint, one field") fully matched: same pattern, same field (`$plainPassword`), same 5 validation groups, placed adjacent to `Assert\Length`. No deviation from design.

## Git Safety

- `main` unchanged at `c194c8b` in the worktree; `git log --oneline main..HEAD` shows only `dbc79f9` on the feature branch. No accidental commits landed on local `main`.
- Primary checkout (`/Users/luismarinoc/Documents/Dev/tbema/gppro`) was not touched or modified during this verification — it was mid-work on an unrelated branch (`approval-workflows-expansion-pr1-invoice-foundation`) with untracked files; only read-only inspection was performed there, plus this one new report file.

## Issues

### CRITICAL
None.

### WARNING
1. **Partial runtime coverage for the "uniform across all paths" requirement.** Only the `ChangePassword` validation group is exercised at runtime in `UserTest.php`; the other 4 groups (`Registration`, `UserCreate`, `ResetPassword`, `PasswordUpdate`) rely on structural inference (identical `groups` array on one shared declarative attribute) rather than a direct runtime assertion. Residual risk is low given the single-field/single-constraint architecture (no per-path code duplication), but this is not fully runtime-proven per a strict reading.
2. **Stray OpenSpec mirror files.** `proposal.md`, `design.md`, `tasks.md`, and `specs/*/spec.md` for this change exist only as untracked, uncommitted files in the primary checkout, on an unrelated branch. They are not lost (Engram holds the canonical copy) but should be committed to the correct location before archive.
3. **`gentle-ai sdd-verify-validate` gate could not admit a fully-clean-exit-code envelope.** The validator requires `build_exit_code: 0` for a `pass`/`pass_with_warnings` verdict, but the project's own documented convention accepts exactly 1 pre-existing, unrelated phpstan finding as the expected baseline (confirmed identical to prior verify-reports in this repo, e.g. `expense-access-scoping`). Per the sdd-verify hard rule, when the validator denies admission the machine-readable envelope is not claimed as "validator-admitted" — this report is persisted as a human-readable finding using the repo's established precedent instead. This is a tooling/schema gap, not a defect in PR1's implementation.

### SUGGESTION
1. Add a lightweight functional/integration test asserting the regex rejects a non-compliant password through at least one non-`ChangePassword` entry point (e.g., admin `UserCreate` form) to close WARNING #1 without relying on structural inference alone.

## Verdict

**PASS WITH WARNINGS**

PR1 is safe to push and merge. All 4 Phase 1 tasks are genuinely complete, the implementation matches design decision #3 exactly, tests were independently re-run (34/34, 328 assertions) and confirmed to exercise the worktree's own code (not a stale copy), phpstan shows only the documented pre-existing unrelated error, and no accidental commits landed on local `main`. Three non-blocking WARNINGs are recorded above for the record; none block merge.
