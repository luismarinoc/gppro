```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:50310ef0a66e9723a5c89d505c98a41d9aa76e21e9fb1591b9fd09c68239cb06
verdict: fail
blockers: 1
critical_findings: 1
requirements: 4/5
scenarios: 9/10
test_command: php -d memory_limit=1G vendor/bin/phpunit tests/
test_exit_code: 0
test_output_hash: sha256:50310ef0a66e9723a5c89d505c98a41d9aa76e21e9fb1591b9fd09c68239cb06
build_command: bash -n tools/codebase-health-baseline/assess.sh tools/codebase-health-baseline/test-assessment.sh
build_exit_code: 0
build_output_hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

## Verification Report

**Change**: `gppro-codebase-analysis`  
**Version**: `codebase-health-baseline/v1`  
**Mode**: Strict TDD  
**Native runtime**: Ordinal 19 already begun; no sdd-attempt command, begin/reset/finish action, commit, or push was performed.

### Executive Summary

The complete PHPUnit suite and every required focused check passed against PHP 8.3.6 with LDAP and a disposable MariaDB on host port 3306. The verification is nevertheless **FAIL** because the current worktree contains a production template change, which violates the baseline-only scope requirement; the separate baseline report and Dokploy/production deployment files were not modified.

### Completeness

| Metric | Value |
| --- | ---: |
| Requirements total / compliant | 5 / 4 |
| Scenarios total / compliant | 10 / 9 |
| Tasks total / complete | 10 / 10 |
| Tasks incomplete | 0 |
| Apply evidence | Engram `sdd/gppro-codebase-analysis/apply-progress`; TDD Cycle Evidence reported in prior apply artifact |

### Build, Tests, Coverage, and Runtime Evidence

| Check | Command/procedure | Exit | Result / output hash |
| --- | --- | ---: | --- |
| Complete PHPUnit suite | `php -d memory_limit=1G vendor/bin/phpunit tests/` | 0 | 4,286 tests; 59,769 assertions; 1 warning; 4 skipped; `sha256:50310ef0a66e9723a5c89d505c98a41d9aa76e21e9fb1591b9fd09c68239cb06` |
| Focused harness | `bash tools/codebase-health-baseline/test-assessment.sh` | 0 | `Assessment contract tests passed.` / `sha256:da15fa1c91d145b320495d139bb3414599fb08f54283224d0764dfae457c1b57` |
| Bash syntax | `bash -n tools/codebase-health-baseline/assess.sh tools/codebase-health-baseline/test-assessment.sh` | 0 | empty / `sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| Ledger JSON | `python3 -m json.tool docs/runbooks/codebase-health-baseline/ledger.json` | 0 | valid JSON / `sha256:745235ed047f2c2c9856f8afe00d808b5f1a0629e28f4c72c79c926d1de14fad` |
| Git diff check | `git diff --check` | 0 | empty / `sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| PHP runtime | `php -v`; LDAP probe | 0 | PHP 8.3.6; LDAP extension and `ldap_escape()` available |
| Disposable database | MariaDB 10.11 disposable container, host port 3306 | 0 | ready; removed after verification; unrelated containers untouched |
| Baseline separation | `git diff --name-only`; report hashes | 0 | tracked diff only `templates/project/_board_card.html.twig`; `docs/runbooks/codebase-health-baseline/report.md` unchanged; no Dokploy files changed |

PHPUnit emitted one non-failing warning from `src/Utils/ReleaseVersion.php:40`: GitHub API returned HTTP 403 rate-limit exceeded during `DoctorControllerTest::testIndexAction`. It also emitted 4 skips and deprecation notices; no test failures occurred.

Coverage analysis skipped — no coverage tool was detected. Composer lint, PHP-CS-Fixer, PHPStan, and frontend checks were not part of the explicitly required execution set and remain unavailable/not rerun here.

### Spec Compliance Matrix

| Requirement | Scenario | Covering evidence | Result |
| --- | --- | --- | --- |
| Evidence-classified baseline inventory | Finding is supported by repository evidence | Focused harness ledger/source/class assertions | ✅ COMPLIANT |
| Evidence-classified baseline inventory | Finding cannot yet be reproduced | Focused harness unavailable/class assertions | ✅ COMPLIANT |
| Reproducible exact-checkout validation | Validation succeeds reproducibly | Focused harness and full PHPUnit runtime evidence | ✅ COMPLIANT |
| Reproducible exact-checkout validation | Environment prevents execution | Ledger unavailable checks and assertions | ✅ COMPLIANT |
| Measured readiness and compatibility signals | Signal is measurable | Ledger signal/source assertions | ✅ COMPLIANT |
| Measured readiness and compatibility signals | Signal lacks an authoritative source | `authoritative_sources` discrepancy assertions | ✅ COMPLIANT |
| Evidence-gated reliability prioritization | Confirmed finding is prioritized | Backlog P1/P2 and verified-fact assertions | ✅ COMPLIANT |
| Evidence-gated reliability prioritization | Hypothesis lacks proof | Hypothesis/no-priority assertions | ✅ COMPLIANT |
| Baseline-only scope protection | Assessment produces no remediation change | Harness self-immutability check passes, but current worktree has production template diff | ❌ FAILING |
| Baseline-only scope protection | Proposed remediation is discovered | Bounded backlog/no-priority assertions | ✅ COMPLIANT |

**Compliance summary**: 9/10 scenarios have passing executable/static coverage; the scope-protection scenario is contradicted by the current production template diff.

### Correctness

| Requirement | Status | Notes |
| --- | --- | --- |
| Evidence-classified baseline inventory | ✅ Implemented | Required ledger fields and classifications pass the harness. |
| Reproducible exact-checkout validation | ✅ Implemented | Full PHPUnit and required checks passed; ledger preserves unavailable contract checks. |
| Measured readiness and compatibility signals | ✅ Implemented | Measurements, sources, discrepancy, interpretation, and impact are represented. |
| Evidence-gated reliability prioritization | ✅ Implemented | Supported findings receive priorities; hypotheses remain informational. |
| Baseline-only scope protection | ❌ Violated | `templates/project/_board_card.html.twig` changes application behavior by rendering/searching assigned users. |

### Design Coherence

| Decision | Followed? | Notes |
| --- | --- | --- |
| Structured evidence records | ✅ Yes | Ledger and harness assert the evidence contract. |
| Exact checkout/Docker as authority | ✅ Yes | Disposable MariaDB supplied the required test database; no production service was used. |
| Unavailable checks recorded | ✅ Yes | Ledger retains explicit unavailable checks and no passing baseline claim. |
| Evidence-gated prioritization | ✅ Yes | Unsupported findings remain follow-up/informational. |
| No application/configuration remediation in this slice | ❌ No | Current ActivityBoard template fix is a production behavior change outside the declared baseline scope. |

### TDD Compliance

| Check | Result | Details |
| --- | --- | --- |
| TDD Evidence reported | ✅ | Apply-progress artifact contains the TDD Cycle Evidence record. |
| All tasks have tests | ✅ | 4/4 task groups have the assessment harness. |
| RED confirmed | ✅ | Apply evidence reports tests written before implementation. |
| GREEN confirmed | ✅ | Focused harness and complete PHPUnit suite pass. |
| Triangulation adequate | ✅ | All 10 baseline scenarios have executable/static coverage; one is invalidated by current scope evidence. |
| Safety net | ⚠️ | Full suite passed, but no changed-template-specific regression test was identified in the baseline task artifact. |

**TDD Compliance**: 5/6 checks fully passed; safety-net limitation is informational/warning.

### Test Layer Distribution

| Layer | Tests | Files | Tools |
| --- | ---: | ---: | --- |
| Unit | 0 | 0 | PHPUnit |
| Integration | 4,286 | 758 | PHPUnit + disposable MariaDB |
| E2E/runtime | 0 | 0 | Not used |
| Shell/document contract | 1 harness | 1 | Bash + Python 3 |
| **Total** | **4,286 tests + 1 harness** | **759** | |

### Changed File Coverage

Coverage analysis skipped — no coverage tool detected. The changed file is `templates/project/_board_card.html.twig`; the complete suite passed, but no dedicated coverage report was available.

### Assertion Quality

✅ The assessment harness assertions invoke production assessment code, inspect ledger/report/backlog content, compare repository status before/after, and validate authoritative source references. No tautologies, ghost loops, or empty-only assertion defects were found.

### Quality Metrics

- **Linter**: ➖ Not run/available in the required verification set.
- **Type checker**: ➖ Not run/available in the required verification set.

### Issues

**CRITICAL**

1. `templates/project/_board_card.html.twig` is a production application change. Requirement `Baseline-only scope protection` requires no application behavior change; this directly contradicts that requirement even though the ActivityBoard regression fix is behaviorally correct and the full suite passes.

**WARNING**

1. PHPUnit reported one non-failing GitHub API rate-limit warning and four skipped tests.
2. PHPUnit emitted 255 deprecation notices (6 direct, 7 indirect, 12 legacy, 237 other).
3. No dedicated ActivityBoard template regression test appears in the apply-progress artifact; the full suite provides broad runtime safety-net evidence.
4. Coverage, PHP-CS-Fixer, PHPStan, Composer lint, and frontend checks were not rerun as required commands for this ordinal.

**SUGGESTION**

1. Resolve the scope contradiction before archive: either remove/re-home the ActivityBoard production fix from this baseline change, or create a separate proposal/change for that behavior fix and rerun baseline verification with a documentation/measurement-only worktree.

### Verdict

FAIL
The required runtime and focused checks passed, but the current production template modification violates the baseline specification's no-remediation scope guard.

### Historical Context Clarification

The original **FAIL**, evidence hashes, counts, and scope finding above remain unchanged. References to the "current worktree" describe the original verification snapshot, not every later checkout.

The follow-up inspection at HEAD `eacaa26` found no local modification to `templates/project/_board_card.html.twig`. This does not retroactively establish baseline-scope compliance, prove a current application defect, or replace the original verdict with PASS.

The archived `expense-access-scoping` results are separate historical evidence. Its PHPUnit checks were not rerun during this follow-up; authenticated browser smoke and the expense-layout regression check do not validate the local non-admin access correction. Formal closure of this baseline change still requires scope-correct verification before archive.

## Publication supplement — historical evidence, not a new verification

The entire preceding report is the verbatim historical record from `1f8c0dd1d0ae87ad5aaaa20590d69f6ee88c072c:openspec/changes/gppro-codebase-analysis/verify-report.md`. Its original FAIL, counts, hashes, and snapshot-relative statements are retained, not endorsed as current results.

The separate envelope below records later verification at `35580da4a18e6ce5445c542bef6242d52ba73132`: four blockers, four critical findings, and FAIL. It is not current native approval or a new result on the publication branch or main. The earlier mixed-checkout epoch is recorded separately in checks.txt; neither epoch has recorded execution timestamps. Native/process assertions below are historical narrative, not newly queried authority.

Redaction policy: only this supplement and the two evidence files replace personal checkout roots with `<CHECKOUT_ROOT>` / `<LEDGER_CHECKOUT_ROOT>` and temporary paths with `<TEMP_PATH>`. The preceding committed record is untouched. Evidence-file and affected output hashes are recomputed over sanitized UTF-8 bytes; pre-redaction hashes in evidence headers are provenance only, not checksums of published bytes. No recorded command was executed during publication repair.

Sanitized later evidence: [current-checks.txt](verification-evidence/current-checks.txt), SHA-256 `94b6cb58aa885e6e0b2e4c50c490d032c134689b4d7b09dcb67ddbd40a55e1fe`. The later envelope references this file; its test output hash covers the sanitized assertion-output block only.

```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:94b6cb58aa885e6e0b2e4c50c490d032c134689b4d7b09dcb67ddbd40a55e1fe
verdict: fail
blockers: 4
critical_findings: 4
requirements: 0/5
scenarios: 2/10
test_command: python3 -
test_exit_code: 1
test_output_hash: sha256:8daf78f5503db91c9fe89b856deadccf52a438c4ef4209d718b7cc25a7a0d665
build_command: bash -n tools/codebase-health-baseline/assess.sh tools/codebase-health-baseline/test-assessment.sh
build_exit_code: 0
build_output_hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

## Later verification record (historical)

### Later-snapshot evidence completion

Pinned HEAD `35580da4a18e6ce5445c542bef6242d52ba73132` and Git status were identical before/after both assertion runs. Existing report/evidence changes were present before execution; this was not a clean-worktree claim.
`verification-evidence/current-checks.txt` contains the exact `python3 -` stdin, complete output, exits, repetition hashes, and later harness/syntax checks. Its SHA-256 is the envelope evidence revision; test_output_hash covers only complete assertion output.
The later record reports two identical assertion outputs (matching recorded hashes): checkout identity, eight incomplete check records, four incomplete signal records, and unresolved owner-review gate with a P1 ranking. The existing harness and syntax checks each have one passing execution recorded in current-checks.txt.
Identity compares assessment output to ledger identity; fields derive directly from the design's MUST contract; priority checks the recorded unresolved prerequisite against the actual backlog row. These are verification diagnostics, not recovered historical TDD or new implementation regression tests.
Docker absence remains an allowed unavailable outcome, not a reproduced implementation defect. The failures below are evidence-contract violations; unsafe Composer/bootstrap and mutating formatter/build commands remain unexecuted.
Current spec mapping: exact-checkout requirement FAILING (identity); inventory/readiness design coherence FAILING (missing fields); confirmed-finding prioritization FAILING (unresolved gate). The remaining partial/untested coverage and missing strict-TDD history remain unresolved; totals stay 0/5 requirements and 2/10 scenarios supported by the later harness.
The later verification narrative reported changes only to this report and current-checks.txt. No implementation, Git branch/index, historical checks.txt, service, or environment changes were made. All checks exited; no background processes were started.

### Historical verification and mixed-checkout clarification

Change: `gppro-codebase-analysis`. Mode: Strict TDD. Verdict: **FAIL; archive not ready**.
Repository: `<CHECKOUT_ROOT>`. The initial checkout was `90a359c301c79333cc8394a22b7b86c1095a8d46`, but `verification-evidence/checks.txt` records assessment execution at `35580da4a18e6ce5445c542bef6242d52ba73132` after a concurrent branch switch.
This bounded retry produced mixed-checkout evidence and cannot certify either exact checkout. The earlier record is retained with portable redactions and explicitly labeled pre-redaction hashes; this clarification is not a new test run or a passing verification. Historical PHPUnit results are not current execution evidence.
The original report is preserved verbatim above from immutable commit `1f8c0dd1d0ae87ad5aaaa20590d69f6ee88c072c:openspec/changes/gppro-codebase-analysis/verify-report.md`; its template-diff finding was not reproduced by the later checks.

### Historical structured status and ownership (not current authority)

The earlier narrative reported that native `gentle-ai sdd-status --contract gentle-ai.sdd-status/v2 --cwd <CHECKOUT_ROOT> gppro-codebase-analysis` returned verify ready, tasks 10/10, archive blocked, and applyProgress missing.
Store: openspec; planningHome: repo-local `openspec/`; actionContext: repo-local, workspaceRoot and allowedEditRoots resolve to this repository.
All task-defined harness/report paths are inside that root. Only this report and `verification-evidence/` are edited by this retry.
The earlier narrative reported attempt ordinal 2 running by `gentle-ai sdd-attempt status`; continuation with the supplied token returned proceed, without starting another attempt.

### Completeness

10/10 implementation checkboxes are checked; **no unchecked `- [ ]` implementation task lines remain**.
Checkbox completion does not prove acceptance: tasks 1.2, 2.1, 3.3, and 4.1 have evidence limitations below.
Required `openspec/changes/gppro-codebase-analysis/apply-progress.md` read returned ENOENT. No alternate backend was substituted.

### Test and validation commands

Sanitized earlier evidence is linked in [checks.txt](verification-evidence/checks.txt) at SHA-256 `105101a0dcd80539f197686ebf6dd94084a5d85c87cfc653358518333e971141`; the later historical envelope references current-checks.txt instead.

| Command | Exit/status | Finding |
| --- | --- | --- |
| `bash tools/codebase-health-baseline/test-assessment.sh` | 0, once in checks.txt | One passing harness execution in each evidence file. |
| `bash -n tools/codebase-health-baseline/assess.sh tools/codebase-health-baseline/test-assessment.sh` | 0, once in checks.txt | Shell syntax valid; not an application build. |
| `python3 -m json.tool docs/runbooks/codebase-health-baseline/ledger.json >/dev/null` | 0, once in checks.txt | JSON syntax valid, not full schema compliance. |
| `bash tools/codebase-health-baseline/assess.sh <CHECKOUT_ROOT>` | 0, once in checks.txt | Assessment root/commit printed at the earlier epoch; baseline remains unavailable. |
| `git diff --check` | 0, once in checks.txt before report | No whitespace errors. |
| `command -v docker` | 1 | Docker CLI unavailable on PATH. |
| `git status --short` | 0, empty before report | Empty status recorded in checks.txt only; later current-checks.txt records dirty but unchanged status. |
| `composer tests-unit` | NOT RUN | PHPUnit config enables database reset against localhost; approved disposable database isolation is not established. |
| `composer tests` / `vendor/bin/phpunit tests/` | NOT RUN | Same database prerequisite; prior suite counts were not reused. |
| `pnpm build` | NOT RUN | Writes prohibited generated frontend assets; no frontend implementation changes. |
| `./php-cs-fixer.sh core` | NOT RUN | Wrapper invokes mutating `fix`; prohibited source edits. |
| `./phpstan.sh core`, `./phpstan.sh test`, `composer linting`, `pnpm lint` | NOT RUN | No PHP/test/frontend changes in retry; runtime/cache-bearing validation not authorized here. |

PHP and Composer executables and vendor autoload are present. Missing Docker does not imply missing native dependencies.
The specification explicitly permits an unavailable outcome when the environment prevents execution. Docker absence alone is not an implementation defect; it limits runtime evidence and does not establish a passing Docker-success scenario.
No database connection, reset, installation, migration, build, cache removal, or environment change was attempted.

### Spec coverage

Counts come from five `### Requirement:` and ten `#### Scenario:` headings in the authoritative spec.
All covering execution below is shell/document contract integration, not Docker/application integration.

| Requirement | Scenario | Evidence and outcome |
| --- | --- | --- |
| Evidence-classified inventory | Finding supported by repository evidence | PARTIAL: harness asserts check classes and source labels, not every source or finding contract. |
| Evidence-classified inventory | Finding cannot yet be reproduced | PARTIAL: unavailable checks are classified; no generic evidence-promotion test. |
| Exact-checkout validation | Validation succeeds reproducibly | UNTESTED: Docker unavailable; assess.sh validates static files and never executes category procedures. |
| Exact-checkout validation | Environment prevents execution | COMPLIANT: harness passes explicit unavailable/no-passing-claim assertions. |
| Readiness signals | Signal is measurable | PARTIAL: harness checks literal signal/source declarations, not actual source measurements. |
| Readiness signals | Signal lacks authoritative source | PARTIAL: textual discrepancy assertion passes, but backlog already ranks it before owner review. |
| Reliability prioritization | Confirmed finding prioritized | UNTESTED: no executable impact/evidence gate; P1/P2 substring assertions cannot establish agreed criteria. |
| Reliability prioritization | Hypothesis lacks proof | PARTIAL: narrative no-priority assertion, not structured per-finding ranking validation. |
| Baseline-only protection | Assessment produces no remediation | COMPLIANT for this retry: harness compares Git status before/after, status unchanged before/after in current-checks.txt, not clean. Historical PR boundary not inferred. |
| Baseline-only protection | Proposed remediation discovered | PARTIAL: backlog defers changes, but no test of bounded follow-up fields. |

Summary: 2/10 fully supported scenarios; 0/5 fully supported requirements. Partial static evidence is not promoted to runtime compliance.

### Correctness and design coherence

Unavailable checks and no-remediation statements match the design. Exact-checkout authority and record completeness do not:
`ledger.json` and report identify `<LEDGER_CHECKOUT_ROOT>` at `f580368`, while the assessment prints current HEAD without checking that evidence belongs to it.
The design requires id/category/source_refs/procedure/inputs/result/reproducibility/availability/evidence_class/reliability_impact/interpretation/priority/follow_up for each check/finding; checks and signals omit multiple fields.
Static procedures exist in the ledger, not executable Docker orchestration in assess.sh. Tool/image versions and a demonstrated repeatable Docker success path remain absent.
Backlog P1/P2 rank observable configuration differences, but product/operations agreement is still an open design question and the runtime signal explicitly requires owner review before priority.

### Strict TDD compliance

| Evidence | Finding |
| --- | --- |
| TDD Cycle Evidence table | CRITICAL: required local apply-progress missing; 0/10 tasks have verifiable complete cycle records. |
| RED | Test file exists; original failing execution/order cannot be confirmed. No RED was fabricated or reenacted. |
| GREEN | Each evidence file records one shell harness pass; configured Composer runner not executed safely. |
| TRIANGULATE | Positive root case and two rejection invocations exist, but no historical case-count evidence. |
| REFACTOR / safety net | Not recorded locally; cannot verify prior refactor or pre-change safety-net execution. |

### Assertion quality and test layers

One shell/Python integration harness exists with three assessment invocations; no application unit/E2E tests changed or executed.
Assertions invoke real assessment behavior; no tautologies, type-only checks alone, CSS assertions, or demonstrably empty ghost loops were identified.
WARNING: final mismatched-root test uses `<TEMP_PATH>`, which can fail at expected-root existence before comparison; it does not establish rejection of a different existing root.
WARNING: P1/P2 and narrative substring assertions do not validate structured evidence-gated ranking or bounded slices.
Coverage analysis skipped: no coverage tool detected for the shell harness; configured coverage command is empty and threshold is zero.
Quality metrics are limited to shell syntax and JSON validity; PHP formatter/static-analysis results are not claimed.

### Review workload / PR boundary

Tasks forecast auto-chain, stacked-to-main, two slices and ~300–380 authored lines. The earlier narrative records a session selecting ask-on-risk with chain deferred; historical task strategy was not adopted as new consent.
Only the assigned verification-refresh slice was performed. No source remediation, commit, PR or archive was created; no `size:exception` was accepted.
Historical PR targets and whole-change authored size cannot be confirmed from the missing apply-progress. This is a WARNING, not permission to expand scope.

### Four recorded blockers

1. CRITICAL: required local apply-progress and strict RED/GREEN/TRIANGULATE/REFACTOR evidence are missing.
2. CRITICAL: later-snapshot evidence is not bound to the ledger/report checkout; successful Docker validation remains untested.
3. CRITICAL: complete required finding/check fields and agreed evidence-gated prioritization are not established by implementation/tests.
4. CRITICAL: eight specification scenarios remain partial or untested; checked tasks and a passing shell harness cannot establish final acceptance.

### Separate runtime limitation

Safe disposable-database isolation was unproven; `composer tests-unit` was not executed because the recorded configuration resets localhost data. This is an execution limitation, not a fifth defect or blocker.

### Historical cleanup and process state

All invoked checks terminated synchronously with captured exits; no child agents, containers, background jobs, or test services were started.
checks.txt records empty Git status; current-checks.txt records existing report/evidence changes with identical before/after status. Only allowed verification artifacts are written; no generated/runtime/production files were touched.
No retry, reset, remediation, commit or archive is recommended automatically. Maintainer must resolve the evidence and safe-runtime prerequisites before further work.
