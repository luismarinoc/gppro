```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:a3358d1-post-rebase-onto-c52cc25
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 12/12
scenarios: 17/17
test_command: vendor/bin/phpunit tests/Controller/Security/ tests/EventSubscriber/ tests/EventSubscriber/Actions/UserSubscriberTest.php tests/Voter/UserVoterTest.php tests/Entity/UserTest.php tests/Entity/LoginAttemptTest.php tests/Controller/UserControllerTest.php tests/Controller/LoginAuditControllerTest.php
test_exit_code: 0
test_output_hash: sha256:327-tests-1556-assertions-ok
build_command: vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
build_exit_code: 1
build_output_hash: sha256:1-pre-existing-quotationcontrollertest-return-type-error
```

## Verification Report

**Change**: login-security-management — PR4 (Login Audit Trail, last of 4 chained PRs)
**Branch**: `login-security-management-pr4-audit-trail`
**Tip commit**: `a3358d1` (rebased; was `bf25830` before rebase)
**Worktree**: `/Users/luismarinoc/Documents/Dev/tbema/gppro-worktrees/login-security-management-pr4-audit-trail`
**Mode**: Full artifact verification (proposal + 4 specs + design + tasks, all retrieved from Engram + openspec mirrors)
**Verdict**: **PASS WITH WARNINGS**

### Rebase Notice (mandatory pre-check)

`origin/main` had advanced past this branch's recorded base (`675bcc2`) during this verification session: `approval-workflows-expansion-pr3-invoice-voter` (PR #129) merged to `origin/main` mid-session, producing new tip `c52cc25` (+ a `chore(release)` version bump commit `675bcc2` -> `521861b` merge -> `c52cc25`).

- **File-disjointness confirmed directly** before rebasing: PR4 touches `config/services.yaml`, `migrations/Version20260814130000.php`, `src/Controller/LoginAuditController.php`, `src/Entity/LoginAttempt.php`, `src/EventSubscriber/LoginAuditSubscriber.php`, `src/Repository/LoginAttemptRepository.php`, `src/Repository/Query/LoginAttemptQuery.php`, `templates/login_audit/index.html.twig`, 3 new test files, 2 translation files, `tasks.md`. The invoice-voter PR touches `src/Voter/InvoiceVoter.php`, `tests/Controller/InvoiceControllerTest.php`, `tests/Voter/InvoiceVoterTest.php`, its own `tasks.md`/`verify-report-pr3.md`. **Zero file overlap.**
- **Rebase performed**: `git rebase origin/main` — 3 commits replayed (`a1b9621`→`5173ab1`, `fbd46f4`→`663ae97`, `bf25830`→`a3358d1`), **0 conflicts**, matching the disjointness prediction exactly.
- **Conflict risk**: confirmed **zero** (not just low) — the two PRs share no touched file.
- Re-ran the entire independent verification suite (targeted PR4 tests, full 327-test regression sweep, phpstan, twig/xliff lint, migration up/down/up) on the rebased tip `a3358d1`; all results identical to the pre-rebase run. Rebase was mechanically safe and did not require re-review of any logic.

### Completeness

| Metric | Value |
|--------|-------|
| Phase 4 tasks total | 15 |
| Phase 4 tasks complete | 15 (4.1–4.15, all `[x]`) |
| Phase 4 tasks incomplete | 0 |

All 15 tasks independently confirmed genuine (not just checkbox trust) via direct source/test reads: entity fields, migration `up`/`down`, repository/query registration, subscriber capture logic, controller gate, template, and the dedicated privacy-regression test.

### Build & Tests Execution (independently re-executed post-rebase, tip `a3358d1`)

**PR4-scoped suite**:
```
vendor/bin/phpunit tests/Entity/LoginAttemptTest.php tests/EventSubscriber/LoginAuditSubscriberTest.php tests/Controller/LoginAuditControllerTest.php
→ OK (15 tests, 69 assertions). Exit 0.
```

**Full regression sweep** (Phase 5.1 scope, run early since PR4 is last in chain):
```
vendor/bin/phpunit tests/Controller/Security/ tests/EventSubscriber/ tests/EventSubscriber/Actions/UserSubscriberTest.php \
  tests/Voter/UserVoterTest.php tests/Entity/UserTest.php tests/Entity/LoginAttemptTest.php \
  tests/Controller/UserControllerTest.php tests/Controller/LoginAuditControllerTest.php
→ OK (327 tests, 1556 assertions). Exit 0.
```

**`LastLoginSubscriberTest` isolation check**:
```
vendor/bin/phpunit tests/EventSubscriber/LastLoginSubscriberTest.php
→ OK (3 tests, 12 assertions). Exit 0. Genuinely untouched — LoginAuditSubscriber is a sibling, not a replacement.
```

**Static analysis**:
```
vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
→ Found 1 error: Controller/QuotationControllerTest.php:296 (return.type). Exit 1.
```
Confirmed pre-existing and unrelated — matches the documented repo-wide baseline (same finding cited in PR1's and PR3's own verify reports, from before this change existed).

**Lint**:
```
php bin/console lint:twig templates/login_audit/index.html.twig templates/security/login.html.twig → OK (2/2)
php bin/console lint:xliff translations/messages.en.xlf translations/messages.es.xlf → OK (2/2)
```

**Migration reversibility** (executed, not just read):
```
php bin/console doctrine:migrations:execute --down DoctrineMigrations\Version20260814130000 --env=test → OK, 2 SQL queries
php bin/console doctrine:migrations:execute --up   DoctrineMigrations\Version20260814130000 --env=test → OK, 2 SQL queries
php bin/console doctrine:migrations:status --env=test → Current: Version20260814130000, 104/104 executed
```
Confirms the apply-phase's self-identified double-drop-FK bug fix is correct: `down()` uses only `$schema->dropTable('gppro_login_attempts')` — no redundant `addSql('ALTER TABLE ... DROP FOREIGN KEY ...')` remains. Down-then-up cycle completes cleanly with no errors on both passes.

**Coverage**: not separately measured (no coverage tooling configured in this repo's phpunit setup); scenario-level compliance below is evidence-based on named, inspected test bodies.

### Spec Compliance Matrix — `login-audit-trail` (PR4 scope: 4 requirements / 6 scenarios)

| Requirement | Scenario | Test | Result |
|---|---|---|---|
| Successful login is recorded | User logs in successfully | `LoginAuditSubscriberTest::testOnLoginSuccessPersistsLoginAttempt` | ✅ COMPLIANT |
| Failed login attempt is recorded | Failed login, existing username | `LoginAuditSubscriberTest::testOnLoginFailureWithKnownUsernamePersistsLoginAttemptWithUser` | ✅ COMPLIANT |
| Failed login attempt is recorded | Failed login, unknown username | `LoginAuditSubscriberTest::testOnLoginFailureWithUnknownUsernamePersistsLoginAttemptWithNullUser` | ✅ COMPLIANT |
| Audit list restricted to ROLE_SUPER_ADMIN | Super-admin views + filters | `LoginAuditControllerTest::testSuperAdminCanViewAuditList`, `testSuperAdminCanFilterByOutcome`, `testSuperAdminCanFilterByUser` | ✅ COMPLIANT |
| Audit list restricted to ROLE_SUPER_ADMIN | Non-super admin denied (403) | `LoginAuditControllerTest::testAdminIsDeniedAccess` | ✅ COMPLIANT |
| Audit records retained indefinitely | Old records remain queryable | `LoginAuditControllerTest::testOldRecordsRemainQueryableWithoutDateFilter` | ✅ COMPLIANT |

**Compliance summary**: 6/6 PR4-scope scenarios compliant.

**CRITICAL privacy check (item 4)** — `LoginAuditSubscriber::onLoginFailure` sets `failureReason` via `(new \ReflectionClass($event->getException()))->getShortName()`, never `$exception->getMessage()`. The dedicated test `testFailureReasonNeverContainsRawExceptionMessage` is a genuine, non-trivial assertion: it constructs a `BadCredentialsException` with a realistic sensitive message (`"Password hash mismatch for admin@internal.example with stored bcrypt digest $2y$...secret"`), captures the persisted `LoginAttempt` via a mock callback, then asserts (a) `failureReason === 'BadCredentialsException'` exactly, (b) the email substring is absent, (c) the full sensitive message substring is absent, (d) `failureReason !== $exception->getMessage()`. This is a real negative-assertion privacy regression guard, not a pass-always test. **Confirmed correct.**

### Correctness (Static Evidence)

| Requirement | Status | Notes |
|------------|--------|-------|
| `LoginAttempt` entity shape | ✅ Implemented | `user` nullable FK (`onDelete: SET NULL`), `attemptedUsername` (180, not null), `ipAddress` (45, nullable), `userAgent` (255, nullable), `outcome` (10, not null, `Assert\Choice`), `failureReason` (120, nullable), `createdAt` (`datetime_immutable`, not null). Indexes: `IDX_..._CREATED_AT` on `created_at`, `IDX_..._USER` composite `(user_id, created_at)`. Matches design decision #2 exactly. |
| `LoginAuditSubscriber` event hooks | ✅ Implemented | Subscribes `LoginSuccessEvent` + `LoginFailureEvent`; captures IP via `getRequest()->getClientIp()`, UA via `headers->get('User-Agent')` on both paths. |
| `LoginAuditController` gate | ✅ Implemented | Class-level `#[IsGranted('ROLE_SUPER_ADMIN')]` — native `RoleVoter`, confirmed non-reassignable (no `RolePermissionManager` entry), matches design decision #4. `ROLE_ADMIN` explicitly regression-tested as denied. |
| Filters (user/date/outcome) | ✅ Implemented | `LoginAttemptQuery` exposes `user`, `outcome`, `dateFrom`, `dateTo`; controller parses from `Request` query params with type/format guards; all 3 filter dimensions exercised by real functional tests asserting rendered content differs by filter. |
| Migration reversibility | ✅ Implemented | Executed up→down→up on test DB; no double-drop-FK bug. |

### Coherence (Design)

| Decision | Followed? | Notes |
|----------|-----------|-------|
| Audit hook via event subscriber, sibling to `LastLoginSubscriber` | ✅ Yes | `LastLoginSubscriber` untouched, independently confirmed 3/3 green. |
| `LoginAttempt` shape, indexes, table name | ✅ Yes | Byte-for-byte match to design decision #2. |
| Audit-list gate: native `ROLE_SUPER_ADMIN`, not `RolePermissionManager` | ✅ Yes | Exact match, with rationale preserved in code comments. |
| List pattern mirrors `UserController::indexAction` (DataTable+ToolbarFormTrait+UserType-autocomplete) | ⚠️ **Deviation** | See assessment below. |

### Deviation Assessment (item 7)

The apply report flagged that `LoginAuditController::index` does **not** use the full `DataTable`/`ToolbarFormTrait`/`UserType`-autocomplete stack the design suggested as the closer precedent to `UserController::indexAction`. Instead it builds `LoginAttemptQuery` directly from `Request` query parameters (mirrors `ExpenseController`'s lighter idiom), with a plain GET-form template reusing the app's real `pagination()` Twig function.

**Assessment: acceptable, well-justified, does not block merge.**

- Every locked spec requirement is independently verified as met and tested: filterable by user/date/outcome (3 dedicated tests), `ROLE_SUPER_ADMIN`-only (2 dedicated tests including the non-super-admin regression guard), indefinite retention (1 dedicated test proving no auto-purge). Nothing in `specs/login-audit-trail/spec.md` mandates a specific UI framework — the spec is behavior-level ("filterable", "restricted", "retained"), not implementation-level.
- The design document itself flagged PR4 as the highest review-budget risk in the chain (`## PR Sequencing Assessment`: "will likely approach or exceed the 400-line review budget by itself"), and tasks.md independently forecast "Medium-High" risk for PR4 alone. The deviation is a deliberate, documented risk-reduction choice, not an oversight — `ToolbarFormTrait::addUsersChoice()` pulling in the full `UserType` autocomplete stack for a read-only, super-admin-only audit screen would have been disproportionate.
- Measured actual PR4 diff (`git diff --stat c52cc25 HEAD`): **703 source lines** (entity/migration/subscriber/repository/query/controller/template/translations) + **388 test lines** = **1091 total insertions**. This already exceeds the tasks.md's own ~380-500 forecast and the repo's 400-line review-budget guideline on source alone — see WARNING below. Adopting the heavier `DataTable`/`ToolbarFormTrait` pattern as originally suggested would have pushed this meaningfully higher still, for a screen with no behavioral requirement for that machinery.
- No spec requirement, design *rationale* (as opposed to design *suggestion*), or test coverage is weakened by this choice — the "List pattern mirrors `UserController::indexAction`" line in the design is explicitly framed as a precedent citation ("closer precedent than `ExpenseApprovalLevelController`"), not a hard architectural mandate on par with the ROLE_SUPER_ADMIN-vs-RolePermissionManager decision (which *is* followed exactly, because that one has real security consequences if deviated from).

Net: this is the kind of judgment call `sdd-verify` should validate rather than block — spec compliance and design *intent* (native-role gate, event-subscriber hook, indefinite retention) are fully honored; the deviation is on a non-load-bearing implementation-pattern suggestion, made explicitly to control the very risk the design document itself called out.

### Closing Spec-Completeness Check — all 4 capabilities (grand total: 12 requirements / 17 scenarios)

| Capability | Req | Scenarios | Status |
|---|---|---|---|
| `password-policy` (PR1, merged) | 3 | 5 | ✅ All implemented (`User.php:216` `Assert\Regex` byte-for-byte per design) + tested (`UserTest::testPasswordAcceptsLetterAndDigit`, `testPasswordRejectsLettersOnly`, `testPasswordRejectsDigitsOnly`, `testPasswordBelowMinimumLengthIsStillRejected`, single shared field/groups). Independently re-confirmed present in source at rebased tip; re-run in the 327-test sweep. |
| `remember-me-policy` (PR2, merged) | 2 | 3 | ✅ `security.yaml:49 always_remember_me: false` confirmed; `login.html.twig:101` restores `{{ parent() }}`; tests `testLoginPageRendersRememberMeCheckboxUncheckedByDefault`, `testLoginWithoutRememberMeDoesNotIssuePersistentCookie`, `testLoginWithRememberMeCheckedIssuesPersistentCookie` present and included in the regression sweep. |
| `admin-user-quick-actions` (PR3, merged) | 3 | 3 | ✅ `forcePasswordResetAction`/`revokeRememberMeAction` on `UserController`, both `#[IsGranted('password', 'userToUpdate')]`, CSRF-protected; tests `testForcePasswordResetActionSetsRequiresPasswordResetFlag`, `testRevokeRememberMeActionChangesSecuritySignatureWithoutTouchingSession`, `testForcePasswordResetActionIsDeniedForNonSuperAdmin` (explicit 403 status-code assertion + confirms entity state unchanged) present and passing. |
| `login-audit-trail` (PR4, this branch) | 4 | 6 | ✅ See matrix above. |
| **Total** | **12** | **17** | **17/17 scenarios independently confirmed compliant across all 4 PRs.** |

This closes the spec-completeness gate for the entire `login-security-management` change ahead of Phase 5 (final regression) and archive.

### Git Safety

- Primary checkout (`/Users/luismarinoc/Documents/Dev/tbema/gppro`, branch `main`) is clean: `git status --short` shows only one unrelated pre-existing untracked file (`openspec/changes/expense-access-scoping/verify-report.md`, not part of this change). `main` is at `c52cc25`, exactly matching `origin/main`. **No residue found from the apply-phase's self-corrected accidental `tasks.md` edit in the wrong checkout** — confirmed via `git log --oneline --all -- openspec/changes/login-security-management/tasks.md`, which shows only the expected 4 commits (PR1 add, PR2/PR3/PR4 checkoffs), all on the feature branch, none stray on `main`.
- PR4 worktree is clean pre- and post-rebase; rebase was performed only after confirming a clean working tree (`git status --short` empty).

### Issues Found

**CRITICAL**: None.

**WARNING**:
1. **PR4's actual diff (1091 lines / 703 source-only) exceeds both its own tasks.md forecast (~380-500) and the repo's 400-line review-budget guideline.** This was anticipated and flagged in advance (tasks.md: "Medium-High" risk for PR4 alone), and the lighter-pattern deviation (see above) was chosen specifically to *contain* this rather than let it grow further — but the actual number still landed above budget. Not blocking (single well-tested, cohesive capability with clear TDD evidence per file, and it is deliberately the last, most-isolated slice in the chain), but flag for reviewer time-boxing.
2. **`tasks.md` Phase 1 (PR1/password-policy) checkboxes were never marked `[x]` on `main`**, despite PR1 being genuinely merged and its own dedicated `verify-report-pr1.md` confirming 4/4 tasks complete with passing tests. `git log --oneline --all -- .../tasks.md` shows no "check off Phase 1" commit — only PR2/PR3/PR4 have dedicated checkoff commits. Functionally harmless (code and tests are real and verified independently in this report's closing spec-completeness check), but it is a documentation-accuracy gap that should be fixed before archive so the merged `tasks.md` accurately reflects reality for all 4 phases.

**SUGGESTION**:
1. Before archive, add a "Phase 1: check off tasks 1.1–1.4" cleanup commit on `main` (or as part of the archive step) to close WARNING #2, since PR1's branch that would have carried that commit is already merged and gone.
2. Consider a follow-up housekeeping note in the archive step confirming the 400-line-over-budget PR4 diff was reviewed with appropriate extra scrutiny, given it landed above the guard's stated threshold.

### Verdict

**PASS WITH WARNINGS**

All 15 Phase 4 tasks are genuinely complete and verified against source/tests, not checkbox trust. The privacy-critical `failureReason` capture is provably safe (short exception class name only, dedicated negative-assertion test). `ROLE_SUPER_ADMIN`-only gating is correctly implemented and regression-tested against `ROLE_ADMIN`. All three filters work and are tested. The migration is genuinely reversible (executed up/down/up, not just read). `origin/main` had advanced mid-session (sibling `approval-workflows-expansion-pr3-invoice-voter` merged) — the branch was rebased with zero conflicts, exactly as predicted by direct file-disjointness confirmation, and the entire independent verification suite was re-run and re-confirmed identical post-rebase. The design deviation (lighter Request-driven filter pattern instead of the full DataTable/ToolbarFormTrait stack) is judged an acceptable, well-justified, spec-preserving risk-reduction choice, not a defect. `main` is confirmed clean of any residue from the previously self-corrected accidental `tasks.md` edit. All 4 capabilities of the `login-security-management` change (12 requirements / 17 scenarios total) are independently confirmed spec-compliant and tested at the closing check, ahead of Phase 5 regression and archive. Two non-blocking WARNINGs are recorded (over-budget diff size; Phase 1 checkbox bookkeeping gap) — neither blocks push/merge of PR4.

**PR4 is safe to push and merge. The `login-security-management` change is ready for Phase 5 (final regression sweep) and archive once PR4 merges, pending the WARNING #2 tasks.md cleanup.**
