# Archive Report: Activity Workspace

**Status**: COMPLETE
**Archived**: 2026-08-14
**Archive Location**: `openspec/changes/archive/2026-08-14-activity-workspace/`
**Mode**: hybrid (Engram + OpenSpec)

## Summary

SDD change "activity-workspace" fully implemented across a 3-PR feature-branch
chain, verified (PASS, 0 CRITICAL, 0 WARNING after a follow-up fix), and
archived. All 32/32 implementation tasks complete. New capability spec merged
into `openspec/specs/activity-workspace/spec.md` (this change created that
capability from scratch; there was no pre-existing spec to delta against).
Kanban board and the global cross-project activity list are confirmed
byte-identical to `main` and remain fully functional and reachable.

## Artifact Observation IDs (Engram)

| Artifact | Observation ID | Status |
|----------|---|---|
| proposal | present in `openspec/changes/archive/2026-08-14-activity-workspace/proposal.md` (not separately persisted to Engram under a distinct topic; read from filesystem for this archive) | Retrieved |
| spec | `openspec/changes/archive/2026-08-14-activity-workspace/specs/activity-workspace/spec.md` (filesystem; new capability spec) | Retrieved |
| design | `openspec/changes/archive/2026-08-14-activity-workspace/design.md` (filesystem) | Retrieved |
| tasks | `openspec/changes/archive/2026-08-14-activity-workspace/tasks.md` (filesystem, 32/32 checked) | Retrieved |
| apply-progress | #622 `sdd/activity-workspace/apply-progress` | Retrieved (intermediate snapshot only — see Final-State Authority note below) |
| verify-report (final PASS) | #624 `sdd/activity-workspace/verify-report` | Retrieved |

Note: this session's `sdd-apply`/`sdd-spec`/`sdd-design`/`sdd-proposal` phases for
this change were persisted primarily to the filesystem under
`openspec/changes/activity-workspace/` (hybrid mode); Engram searches for
`sdd/activity-workspace/proposal`, `/spec`, and `/design` topics returned no
distinct observations beyond apply-progress and verify-report, so those three
artifacts are cited from their filesystem paths, now archived above.

## Final-State Authority Note (apply-progress is stale — do not read it as current)

`sdd/activity-workspace/apply-progress` (#622, written 2026-08-13 21:57:00)
covers **only Phase 1 + Phase 2 (PR 1 / data layer, 9 of 32 tasks)**. It
explicitly lists Phases 3–7 as "Remaining Tasks" at the time it was written.
This is stale by the time of archive:

- The persisted tasks artifact (`tasks.md`, both pre-archive and now in the
  archived copy) shows **all 32/32 tasks checked** across all 7 phases.
- `git log --oneline` on `main` confirms 3 merged PRs (#116, #117, #118)
  containing the full commit sequence: entity/migration/repository/form (PR1,
  matches apply-progress #622) → controller + templates (PR2) → navigation +
  translations (PR3) → a full-regression commit → a follow-up CRITICAL-fix
  commit (`e833192`).
- The final `verify-report` (#624, PASS) independently re-ran the full test
  suite across all 3 PRs' worth of code and confirms 97/97 tests passing.

Per the Final-State Authority hierarchy, the persisted tasks artifact (rank 2)
and the final verify-report (rank 1 evidence within it) both outrank the
apply-progress snapshot (rank 4, lowest). This archive report records the
**final state**: fully implemented, all 3 PRs merged to `main`, not the
partial PR1-only state apply-progress describes. No fresh `sdd-apply` run was
needed or performed — the code for PR2 and PR3 already existed, merged, and
verified; the gap was only that apply-progress (an intermediate snapshot) was
never updated after PR2/PR3, which the final verify-report (#624) itself
flagged as a non-blocking SUGGESTION ("Update `sdd/activity-workspace/apply-progress`
in Engram to reflect the true cumulative state before archive, for
traceability") rather than a defect requiring rework.

## Gates Passed

### Task Completion Gate ✓
All 32 implementation tasks checked in the persisted
`openspec/changes/activity-workspace/tasks.md` (now archived):
- Phase 1 (Entity + Migration, PR1): 4/4 complete
- Phase 2 (Repository + Form, PR1): 3/3 complete
- Phase 3 (Controller, PR2, security-relevant RED tests first): 8/8 complete
- Phase 4 (Templates, PR2): 3/3 complete
- Phase 5 (Navigation — Picker Routes + Menu, PR3): 6/6 complete
- Phase 6 (Translations, PR3): 3/3 complete
- Phase 7 (Full Regression + Verification): 5/5 complete

No unchecked implementation tasks remain.

### Native Review Receipt Gate ✓
No native review authority artifact was discovered for this candidate.
`reviewGate` is structurally absent. Archive proceeds under ordinary
repository policy.

## Verification Report Status (Final PASS)

Per observation #624 (`sdd/activity-workspace/verify-report`, revision 2, the
terminal re-verify pass):

| Item | Status |
|------|--------|
| Verdict | **PASS** |
| CRITICAL issues | 0 (previously 1 — see "CRITICAL Finding and Fix" below) |
| WARNING issues | 0 |
| SUGGESTION issues | 3 (all non-blocking, informational) |
| Requirements | 8/8 COMPLIANT |
| Scenarios | 11/11 COMPLIANT |
| Test suites passing | 97/97 (642 assertions) |
| Test command | `phpunit tests/Entity/ActivityCommentTest.php tests/Repository/ActivityCommentRepositoryTest.php tests/Form/ActivityCommentFormTest.php tests/Controller/ActivityWorkspaceControllerTest.php tests/Controller/ProjectControllerTest.php tests/EventSubscriber/MenuSubscriberTest.php tests/Controller/ActivityControllerTest.php tests/Controller/ActivityBoardControllerTest.php` |
| PHPStan | Exactly 1 error — pre-existing, unrelated `QuotationControllerTest::decodeJsonResponse()` return-type error; output hash byte-identical to the pre-fix verify pass, confirming no new errors |
| Twig lint | `[OK] All 203 Twig files contain valid syntax.` |
| XLIFF lint | `[OK] All 2 XLIFF files contain valid syntax.` |
| Kanban/global-list non-regression | `git diff main -- src/Controller/ActivityController.php templates/project/board.html.twig src/Controller/ActivityBoardController.php src/Entity/ActivityBoardState.php` → empty (0 lines) |

## CRITICAL Finding and Fix (disclosed transparently)

The first verify pass (prior to observation #624's revision) returned **FAIL**
with 1 CRITICAL: `templates/activity_workspace/index.html.twig` panel 2 had
untested Twig render branches. Investigating the gap uncovered a **real
pre-existing production bug**, not just a test-coverage gap:

- **Root cause**: the file-level `{% import "macros/widgets.html.twig" as
  widgets %}` at the top of the template is invisible inside a `{% block %}`
  override nested inside `{% embed %}` — a documented Twig scoping
  limitation. Panel 2's detail card block called `widgets.*` macros without
  its own local re-import, so `widgets` resolved to `null` inside that block
  and every `widgets.*` call in it silently rendered blank — Name, Visible,
  Billable, and Project cells in panel 2 were blank in production.
- **Fix**: a single production line — `{% import "macros/widgets.html.twig"
  as widgets %}` re-added inside the `{% embed %}` block, at the same nesting
  level as two other files already using this exact pattern
  (`templates/customer/details.html.twig`, `templates/project/details.html.twig`)
  — confirmed as established, working codebase precedent, not a novel
  workaround.
- **Test added**: `testIndexActionRendersNonVisibleNonBillableBudgetAndTimeBudgetFields`
  in `tests/Controller/ActivityWorkspaceControllerTest.php` (+44 lines),
  scoped-crawler assertions for the visible/billable badges plus page-wide
  assertions for budget/timeBudget labels.
- **Fix commit**: `e833192` — `test(activity): cover visible/billable/budget/timeBudget render branches in workspace panel 2` (2 files changed, 45 insertions, 0 deletions).
- **Re-verify**: independently re-ran the full test suite (97/97 passing),
  re-ran PHPStan (byte-identical pre-existing-only error), re-confirmed the
  Kanban/global-list diff was still empty, and re-confirmed `main` was
  untouched. Verdict upgraded to PASS, 11/11 scenarios COMPLIANT.
- **Panel 1 was never affected**: `embed_activities.html.twig` already had
  its own correctly-scoped local `{% import %}`.

This is disclosed as found and fixed during this SDD cycle's verify→apply
gap-closure loop, per the Final-State Authority rule against silently
resolving or omitting defect history.

## PR Chain Summary (3 chained PRs, feature-branch-chain strategy)

Tracker branch: `activity-workspace-tracker` (off `main`). All 3 PRs merged
to `main`.

| PR | Branch | Merge commit | Contents |
|----|--------|---------------|----------|
| **#116** | `activity-workspace-tracker-pr1-data-layer` | `ae8ac99` | Data layer: `ActivityComment` entity + `Version20260813170000` migration (`gppro_activities_comments` table, indexes, `ON DELETE CASCADE` FKs) + `ActivityCommentRepository` (`getComments`, `saveComment`) + `ActivityCommentForm` (mirrors `ProjectCommentForm`). ~176 lines production + tests. |
| **#117** | `activity-workspace-tracker-pr2-controller-templates` | `66480ba` | `ActivityWorkspaceController` (`indexAction` + `addCommentAction`, both starting with the cross-project 404 guard) + `templates/activity_workspace/embed_activities.html.twig` + `templates/activity_workspace/index.html.twig` (3-panel layout). Security-relevant RED tests written first (cross-project 404, `comments`-gate visibility). |
| **#118** | `activity-workspace-tracker-pr3-nav-translations` | `9bee4ca` | Navigation: `ProjectController` picker routes (`admin_project_activity_workspace_picker`/`_paginated`) + `workspaceMode` flag, `widgets.html.twig`/`project/index.html.twig` row-link wiring, `MenuSubscriber` repointing (`activities` → picker, new `activities_all` sibling → unchanged global list) + `messages.en.xlf`/`messages.es.xlf` translations + full regression sweep + the CRITICAL-fix commit (`e833192`) as a follow-up on the same branch. |

Full linear commit sequence on `main` (chronological): `bc59055` (SDD
artifacts) → `04077ae` (entity+migration) → `7103d7d` (repo+form) → `ae8ac99`
(PR1 merge) → `81c8aa7` (controller) → `1109319` (templates) → `66480ba` (PR2
merge) → `0bef992` (picker routes) → `0baeb5d` (menu repoint) → `f6ba217`
(translations) → `3a83162` (regression sweep) → `e833192` (CRITICAL fix +
test) → `9bee4ca` (PR3 merge).

## Spec Merge (New Capability — no delta, full copy)

**Target**: `openspec/specs/activity-workspace/spec.md` (did not previously
exist — this is a genuinely new capability, matching the pattern already used
for `expense-allocation` and `activity-board-assigned-user-display`).

**Action**: mechanical `cp` of
`openspec/changes/activity-workspace/specs/activity-workspace/spec.md` into
`openspec/specs/activity-workspace/spec.md`, verified via `diff -r` (empty,
exit 0) between source and copy before the move.

**Content**: 8 requirements / 11 scenarios covering:
1. "Actividades" menu opens a project picker
2. Global activity list remains unchanged and reachable
3. Panel 1 lists the project's non-global activities
4. Panel 2 shows base activity fields only (2 scenarios)
5. Activity selection is a bookmarkable URL segment
6. Cross-project activity requests are rejected
7. Comment thread is visible only to authorized users (2 scenarios)
8. Posting a comment persists it and reloads the thread (2 scenarios)

No pre-existing requirements were touched, modified, or removed — `expense-allocation`
and `activity-board-assigned-user-display` specs are untouched by this change.

## Archive Folder Move

Mechanical archive folder move via `git mv`:
- Source: `openspec/changes/activity-workspace/`
- Destination: `openspec/changes/archive/2026-08-14-activity-workspace/`
- Move command: `git mv openspec/changes/activity-workspace openspec/changes/archive/2026-08-14-activity-workspace`
- Pre-move: the previously-untracked `openspec/changes/activity-workspace/verify-report.md`
  (final PASS re-verify report) was staged with `git add` before the move so
  it moved with the rest of the tracked change folder.
- Status: Successful
- Pre-move snapshot: created via `cp -R` to a `mktemp -d` directory, retained
  for verification
- Post-move verification: `diff -r` between the pre-move snapshot and the
  archived folder
- **Diff result: EMPTY (exit 0)** — byte-identity confirmed, no truncation or
  alteration
- Source directory post-move: confirmed deleted (no orphaned symlinks)
- This `archive-report.md` file is additive (did not exist in the pre-move
  source) and is therefore excluded from the diff comparison, per the
  Mechanical Copy Contract.

## Final Test/Regression Numbers

- **97/97 PHPUnit tests passing, 642 assertions** (final re-verify run,
  independently executed, not trusted from any prior report)
- **PHPStan**: 1 pre-existing, unrelated error only (`QuotationControllerTest::decodeJsonResponse()`),
  byte-identical output hash to the pre-fix pass — no new errors introduced
  by this change
- **Twig lint**: `[OK] All 203 Twig files contain valid syntax.`
- **XLIFF lint**: `[OK] All 2 XLIFF files contain valid syntax.`

## Kanban Board + Global Activity List — Confirmed Untouched

Both non-regression guarantees from the proposal (Rule 9 / A1) are
independently confirmed in the final verify report:

- `git diff main -- src/Controller/ActivityController.php templates/project/board.html.twig src/Controller/ActivityBoardController.php src/Entity/ActivityBoardState.php` → **empty (0 lines)**, confirming byte-identity to `main`.
- `tests/Controller/ActivityBoardControllerTest.php` and `tests/Controller/ActivityControllerTest.php` both re-run and passing as part of the 97/97 total.
- The global cross-project activity list (`admin_activity`) gained a new
  sibling menu entry ("Todas las actividades") per the proposal's explicit
  design — its route, permissions, and CRUD child routes are unchanged.

## Full Cycle Status

| Phase | Status |
|-------|--------|
| Explore | ✅ (`exploration.md` present in archive) |
| Propose | ✅ (`proposal.md`, 10 business rules, 8 working assumptions, risk table) |
| Spec | ✅ (8 requirements / 11 scenarios, new capability) |
| Design | ✅ (`design.md` present in archive) |
| Tasks | ✅ (32/32 tasks, 7 phases, chained-PR forecast) |
| Apply | ✅ (3 PRs merged to `main`: #116, #117, #118 — apply-progress #622 is a stale PR1-only intermediate snapshot; see Final-State Authority note above) |
| Verify | ✅ PASS (0 CRITICAL, 0 WARNING, 3 SUGGESTION non-blocking; 1 CRITICAL found and fixed mid-cycle, disclosed above) |
| Archive | ✅ (this report) |

## Open Non-Blocking Items (carried into archive, not fixed here)

Per verify-report #624's SUGGESTIONs (informational only, do not block
archive):
1. The new render-branch test could be tightened to scope Name/Project cell
   assertions to `#activity_workspace_detail_box` rather than relying on
   transitive-scoping reasoning.
2. `sdd/activity-workspace/apply-progress` in Engram was never updated past
   PR1 — addressed by this archive report's Final-State Authority note above,
   which supersedes it as the authoritative closing record.
3. `design.md` Open Questions has one unchecked item about the unused
   `pinned` column on `ActivityComment` — non-blocking, future cleanup
   candidate.

## Acceptance Criteria Met

- [x] Proposal, spec, design, tasks retrieved and archived
- [x] Spec merged into main specs as a new capability (`openspec/specs/activity-workspace/spec.md`)
- [x] All 32 tasks complete and persisted (Task Completion Gate passed)
- [x] Verify report PASS, 0 CRITICAL, 0 WARNING (Native review gate: absent, ordinary policy)
- [x] Kanban board and global activity list confirmed byte-identical to `main`
- [x] Change folder moved to archive with verified byte-identity (`diff -r` empty, twice: spec copy + folder move)
- [x] Archive report persisted to Engram and filesystem
- [x] Stale `apply-progress` snapshot explicitly superseded, not silently echoed

---

**Observation IDs for Traceability**:
- apply-progress (stale, PR1-only): #622
- verify-report (final PASS): #624
- archive-report: this document (saved to Engram topic `sdd/activity-workspace/archive-report`)

**Archived Location**: `/Users/luismarinoc/Documents/Dev/tbema/gppro/openspec/changes/archive/2026-08-14-activity-workspace/`

**Main Spec Created**: `/Users/luismarinoc/Documents/Dev/tbema/gppro/openspec/specs/activity-workspace/spec.md`
