```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:e833192e37c40be56e0b1027766d7a6480c43847
verdict: pass
blockers: 0
critical_findings: 0
requirements: 8/8
scenarios: 11/11
test_command: vendor/bin/phpunit tests/Entity/ActivityCommentTest.php tests/Repository/ActivityCommentRepositoryTest.php tests/Form/ActivityCommentFormTest.php tests/Controller/ActivityWorkspaceControllerTest.php tests/Controller/ProjectControllerTest.php tests/EventSubscriber/MenuSubscriberTest.php tests/Controller/ActivityControllerTest.php tests/Controller/ActivityBoardControllerTest.php
test_exit_code: 0
test_output_hash: sha256:01e0fe49625f39a3c2d05c32c471f9923746fcfb5b98a9309335ad62e03b47c5
build_command: vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
build_exit_code: 1
build_output_hash: sha256:ad463a2b8edea24cd06ae48c9fd8a57aa84bda36a4851fc80d94359de1b6819d
```

## Verification Report (Re-Verify Pass — Follow-Up to CRITICAL Fix)

**Change**: activity-workspace
**Verified on branch**: `activity-workspace-tracker-pr3-nav-translations`
**Previous tip / this tip**: `3a83162` → `e833192` (+1 commit: `test(activity): cover visible/billable/budget/timeBudget render branches in workspace panel 2`)
**Date**: 2026-08-13
**Prior verdict**: FAIL (1 CRITICAL — untested render branches in `templates/activity_workspace/index.html.twig` panel 2)

### What changed since the last verify pass

Single follow-up commit `e833192`, diffstat `2 files changed, 45 insertions(+), 0 deletions(-)`:
- `templates/activity_workspace/index.html.twig`: **+1 line** — added `{% import "macros/widgets.html.twig" as widgets %}` inside the `{% embed %}` block (line 11).
- `tests/Controller/ActivityWorkspaceControllerTest.php`: **+44 lines** — new test `testIndexActionRendersNonVisibleNonBillableBudgetAndTimeBudgetFields`.

Confirmed scope is exactly as reported by the apply agent: one production line, one new test, nothing else touched.

### 1. Template fix confirmed

Read `templates/activity_workspace/index.html.twig` directly. Line 11 now reads:
```twig
{% embed '@theme/embeds/card.html.twig' with {fullsize: true} %}
    {% import "macros/widgets.html.twig" as widgets %}
    {% block box_attributes %}id="activity_workspace_detail_box"{% endblock %}
    ...
```
This is the correct fix for the underlying bug: the file-level `{% import ... as widgets %}` at line 2 is invisible inside a `{% block %}` override nested inside `{% embed %}` — a documented Twig scoping limitation. The re-import is placed inside the embed block, at the same nesting level used successfully in `templates/customer/details.html.twig` and `templates/project/details.html.twig`. Verified those two files use the identical local-reimport pattern (grep-confirmed both already carry `{% import "macros/widgets.html.twig" as widgets %}` inside their own `{% embed %}` blocks), so this is not a novel workaround — it matches established, working codebase precedent exactly.

Panel 1's `embed_activities.html.twig` already had its own correctly-scoped `{% import %}` inside its own `{% embed %}` block (line 13) — that file was never affected by this bug; only panel 2's detail card was missing its local re-import.

### 2. New test — content-assertion quality review

Read the new test in full. Assessment per the 4 previously-uncovered branches, plus the disclosed Name/Project blanking bug:

| Field/branch | Assertion style | Verdict |
|---|---|---|
| `not activity.visible` | `$crawler->filter('#activity_workspace_detail_box span.badge.bg-default:contains("No")')` scoped count == 2 (shared with billable) | Real, scoped, structural assertion — not a loose substring check |
| `not activity.billable` | Same scoped crawler count assertion (2 badges total: one per flag) | Real, scoped, structural assertion |
| `activity.hasBudget()` | Page-wide `assertStringContainsString('Budget', $content)` (label only, no value assertion) | Executes the branch and proves the row renders without exception; weaker than an ideal value-level check, but adequate — this branch is independent of the widgets-import bug (uses `|money` filter, not the `widgets` macro object), so a label-presence check is sufficient to prove the branch text renders |
| `activity.hasTimeBudget()` | Page-wide `assertStringContainsString('Hourly quota', $content)` (label only) | Same as above — independent of the widgets bug, label check is adequate |
| Name cell (panel 2, `widgets.label_dot(...)`) | Page-wide `assertStringContainsString($this->nameOf($activity), $content)` | **Confounded**: this activity is also listed by name in panel 1 (`embed_activities.html.twig` always lists the project's activities, including the selected one), so this assertion would pass even if panel 2's Name cell were still blank. It does not independently prove panel 2's Name cell renders. |
| Project cell (panel 2, `widgets.label_project(project)`) | None | No assertion anywhere in the test file checks panel 2's Project row content specifically. |

**However**, this gap is not blocking, for a specific structural reason verified by direct code reading: Name, Visible, Billable, and Project cells in panel 2 all read the *same* `widgets` Twig variable in the *same* `{% block box_body %}` scope (confirmed lines 26, 31, 37, 49 of `index.html.twig` — no per-row scoping exists that could let one succeed while another silently fails). The bug was 100% a variable-scoping bug (`widgets` undefined → all `widgets.*` calls in that block silently return null), not a per-field logic bug. The test's scoped, structural assertion that Visible/Billable badges render correctly (`bg-default` badge with "No" text, from `widgets.label_boolean()`) is therefore direct proof that the `widgets` variable resolves correctly inside that block — which by the same mechanism proves Name (`widgets.label_dot()`) and Project (`widgets.label_project()`) also resolve, since there is no code path by which `widgets` could be defined for one macro call and undefined for another in the same block scope. Combined with the direct template-fix inspection above (matching a proven working pattern elsewhere), this is sufficient evidence the fix is correct and the scenario is genuinely satisfied end-to-end, not just by coincidence of test wording.

Recorded as a SUGGESTION below (test-hygiene tightening), not a CRITICAL/WARNING — it does not reopen the FAIL verdict.

### 3. Full test suite — re-run

```
$ vendor/bin/phpunit tests/Entity/ActivityCommentTest.php tests/Repository/ActivityCommentRepositoryTest.php \
    tests/Form/ActivityCommentFormTest.php tests/Controller/ActivityWorkspaceControllerTest.php \
    tests/Controller/ProjectControllerTest.php tests/EventSubscriber/MenuSubscriberTest.php \
    tests/Controller/ActivityControllerTest.php tests/Controller/ActivityBoardControllerTest.php
...
OK (97 tests, 642 assertions)
```
97/97 passed (96 from the prior pass + 1 new test), matching the fix agent's reported count exactly. Independently re-run, not trusted from any prior report.

### 4. PHPStan — re-run

```
$ vendor/bin/phpstan analyse -c tests/phpstan.neon --no-progress
Line 296  Controller/QuotationControllerTest.php
  Method App\Tests\Controller\QuotationControllerTest::decodeJsonResponse()
  should return array<string, mixed> but returns array<mixed, mixed>.  return.type
[ERROR] Found 1 error
```
Exactly 1 error — same pre-existing, unrelated `QuotationControllerTest::decodeJsonResponse()` error. Output hash (`ad463a2...`) is byte-identical to the previous verify pass's `build_output_hash`, corroborating no new errors were introduced.

### 5. Twig / XLIFF lint — re-run

```
$ bin/console lint:twig templates/
[OK] All 203 Twig files contain valid syntax.
$ bin/console lint:xliff translations/messages.en.xlf translations/messages.es.xlf
[OK] All 2 XLIFF files contain valid syntax.
```
Both clean.

### 6. Kanban / global-list non-regression — re-confirmed

```
$ git diff main -- src/Controller/ActivityController.php templates/project/board.html.twig \
    src/Controller/ActivityBoardController.php src/Entity/ActivityBoardState.php
(empty — 0 lines)
```
Still byte-identical to `main`. The fix touched only `templates/activity_workspace/index.html.twig` and its own test file — zero Kanban/global-list surface touched.

### 7. `main` untouched — re-confirmed

```
$ git log -1 main --format='%H %ci'
590981cacffde8ee98677ede346581bda4bfd98 2026-08-13 19:47:31 -0400
```
Identical hash to the previous verify pass. Local `main` has not moved.

### 8. Tasks re-checked

```
$ grep -c '^- \[x\]' openspec/changes/activity-workspace/tasks.md   → 32
$ grep -c '^- \[ \]' openspec/changes/activity-workspace/tasks.md   → 0
```
All 32/32 tasks remain checked; no task text changed (the fix was a gap-closure on top of already-complete task 3.8/4.2, not a new task).

### Spec Compliance Matrix (8 requirements / 11 scenarios) — updated

All 10 previously-COMPLIANT scenarios remain COMPLIANT (re-confirmed via the full re-run above; nothing regressed). The one previously-PARTIAL scenario is now upgraded:

| # | Requirement | Scenario | Covering test | Result |
|---|---|---|---|---|
| 4 | Panel 2 shows base activity fields only | Selected activity renders base fields | `testIndexActionRendersBaseActivityFieldsAndNoBoardState` (name/comment/number/milestone/no-board-state) **+** `testIndexActionRendersNonVisibleNonBillableBudgetAndTimeBudgetFields` (visible=false, billable=false, budget, timeBudget) | **COMPLIANT** (was PARTIAL) |

All other 10 scenarios unchanged from the prior pass — re-verified as still passing via the full suite re-run, not assumed from the prior report.

**Compliance summary: 11/11 scenarios COMPLIANT.**

### Disclosure note (per instructions)

The Name/Visible/Billable/Project blank-cell production bug was **found and disclosed by the apply agent** during this gap-closure fix, not discovered fresh in this verify pass. This verify pass independently re-confirmed: (a) the bug's root cause (Twig `{% embed %}` block scoping) is real and matches a known, already-solved pattern elsewhere in this codebase; (b) the fix is exactly scoped (1 template line + 1 test, nothing else); (c) the fix is correctly placed and the test's passing scoped assertions on Visible/Billable structurally prove the same `widgets`-resolution mechanism also fixes Name/Project, even though those two cells lack their own dedicated scoped assertions (noted as a SUGGESTION, non-blocking).

### Issues Found

CRITICAL: None. (Previous CRITICAL closed — see Spec Compliance Matrix above.)

WARNING: None.

SUGGESTION:
1. Tighten `testIndexActionRendersNonVisibleNonBillableBudgetAndTimeBudgetFields` (or add a follow-up assertion) to scope the Name and Project cell checks to `#activity_workspace_detail_box` specifically (e.g., via crawler `filter('#activity_workspace_detail_box')->text()` or an explicit `label_project`-rendered project name/link check), so the test does not rely on panel 1's incidental name duplication or on the transitive-scoping argument in this report to prove those two specific cells render. Non-blocking — the underlying fix is independently verified correct by direct code inspection and by proven working precedent elsewhere in the codebase.
2. (Carried over, unchanged) Update `sdd/activity-workspace/apply-progress` in Engram to reflect the true cumulative state (PR1+PR2+PR3+this fix) before archive, for traceability — this verify pass, like the last, independently re-derived all evidence from source/tests rather than relying on that stale artifact.
3. (Carried over, unchanged) `openspec/changes/activity-workspace/design.md` Open Questions still has one unchecked item about the unused `pinned` column — non-blocking design note for reviewer sign-off, not a spec/task gap.

### Verdict

**PASS**

All 32/32 tasks complete, 97/97 targeted tests pass (up from 96/96, +1 new test), PHPStan shows only the confirmed pre-existing unrelated error (byte-identical output to the prior pass), `lint:twig`/`lint:xliff` clean, Kanban/global-list files confirmed byte-identical to `main`, local `main` untouched, and the fix is a minimal, correctly-scoped, disclosed gap-closure (1 template line + 1 test) matching an established working Twig pattern already proven elsewhere in this codebase. The previously-blocking CRITICAL (untested render branches in Requirement 4's scenario) is closed: 11/11 spec scenarios are now COMPLIANT with real, independently re-run passing test evidence.

This is the final verify pass for `activity-workspace`. **Recommend proceeding to `sdd-archive`.**
