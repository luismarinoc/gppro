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
|---|---:|
| Requirements total / compliant | 5 / 4 |
| Scenarios total / compliant | 10 / 9 |
| Tasks total / complete | 10 / 10 |
| Tasks incomplete | 0 |
| Apply evidence | Engram `sdd/gppro-codebase-analysis/apply-progress`; TDD Cycle Evidence reported in prior apply artifact |

### Build, Tests, Coverage, and Runtime Evidence
| Check | Command/procedure | Exit | Result / output hash |
|---|---|---:|---|
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
|---|---|---|---|
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
|---|---|---|
| Evidence-classified baseline inventory | ✅ Implemented | Required ledger fields and classifications pass the harness. |
| Reproducible exact-checkout validation | ✅ Implemented | Full PHPUnit and required checks passed; ledger preserves unavailable contract checks. |
| Measured readiness and compatibility signals | ✅ Implemented | Measurements, sources, discrepancy, interpretation, and impact are represented. |
| Evidence-gated reliability prioritization | ✅ Implemented | Supported findings receive priorities; hypotheses remain informational. |
| Baseline-only scope protection | ❌ Violated | `templates/project/_board_card.html.twig` changes application behavior by rendering/searching assigned users. |

### Design Coherence
| Decision | Followed? | Notes |
|---|---|---|
| Structured evidence records | ✅ Yes | Ledger and harness assert the evidence contract. |
| Exact checkout/Docker as authority | ✅ Yes | Disposable MariaDB supplied the required test database; no production service was used. |
| Unavailable checks recorded | ✅ Yes | Ledger retains explicit unavailable checks and no passing baseline claim. |
| Evidence-gated prioritization | ✅ Yes | Unsupported findings remain follow-up/informational. |
| No application/configuration remediation in this slice | ❌ No | Current ActivityBoard template fix is a production behavior change outside the declared baseline scope. |

### TDD Compliance
| Check | Result | Details |
|---|---|---|
| TDD Evidence reported | ✅ | Apply-progress artifact contains the TDD Cycle Evidence record. |
| All tasks have tests | ✅ | 4/4 task groups have the assessment harness. |
| RED confirmed | ✅ | Apply evidence reports tests written before implementation. |
| GREEN confirmed | ✅ | Focused harness and complete PHPUnit suite pass. |
| Triangulation adequate | ✅ | All 10 baseline scenarios have executable/static coverage; one is invalidated by current scope evidence. |
| Safety net | ⚠️ | Full suite passed, but no changed-template-specific regression test was identified in the baseline task artifact. |

**TDD Compliance**: 5/6 checks fully passed; safety-net limitation is informational/warning.

### Test Layer Distribution
| Layer | Tests | Files | Tools |
|---|---:|---:|---|
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
