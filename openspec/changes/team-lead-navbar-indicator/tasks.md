# Tasks: Team Lead Navbar Indicator

**Artifact file**: `openspec/changes/team-lead-navbar-indicator/tasks.md` (hybrid store)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~150-190 (new template ~70 + test file ~90-120) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | RED tests + template GREEN, single deliverable | PR 1 | `bin/phpunit tests/Controller/LayoutControllerTest.php --group integration` | N/A — functional test suite is the runtime harness (no browser/e2e needed for HTML-payload assertions) | Delete `templates/bundles/TablerBundle/includes/navbar_user.html.twig` and revert `LayoutControllerTest.php`; Symfony falls back to vendor template immediately |

## Phase 1: RED Tests (tests/Controller/LayoutControllerTest.php)

- [x] 1.1 Add `use App\Entity\Team;` and `use App\Entity\TeamMember;` imports.
- [x] 1.2 Add private `makeTeamlead(User $user): void` helper (persist Team + TeamMember::setTeamlead(true), $em->refresh($user)) per design.md.
- [x] 1.3 RED test: testTeamleadIndicatorPresentForMembershipTeamlead() — ROLE_USER + makeTeamlead(), request /dashboard/, assert both `data-teamlead-indicator="text">Teamlead</span>` and `data-teamlead-indicator="avatar"` present.
- [x] 1.4 RED test: testTeamleadIndicatorAbsentForPlainUser() — ROLE_USER, no membership, assert `data-teamlead-indicator` absent.
- [x] 1.5 RED test: testTeamleadIndicatorAbsentForGlobalRoleWithoutMembership() — ROLE_TEAMLEAD (tony_teamlead fixture, no TeamMember), assert `data-teamlead-indicator` absent (locks Success Criterion 3, D1 membership-based gating).
- [x] 1.6 Regression test: testUserTitleStillRendersForTeamlead() — ROLE_TEAMLEAD (tony_teamlead, title "Head of Development" — see Deviations), assert title still renders. Should already pass pre-change; guards D4/no-regression.
- [x] 1.7 Run `vendor/bin/phpunit tests/Controller/LayoutControllerTest.php --group integration`. Confirmed 1.3 FAILED RED (only the membership-teamlead scenario needs the new template); 1.4/1.5/1.6 passed against vendor baseline.

## Phase 2: GREEN Implementation (templates/bundles/TablerBundle/includes/navbar_user.html.twig)

- [x] 2.1 Create file as byte-verbatim copy of vendor/kevinpapst/tabler-bundle/templates/includes/navbar_user.html.twig.
- [x] 2.2 Hunk 1: insert `{% set isTeamlead = user.isTeamlead is defined and user.isTeamlead %}` guard + `teamleadBadge` map (A2/A5/A6).
- [x] 2.3 Hunk 2: pass `|merge(teamleadBadge)` into both macro.avatar(...) calls so the native `badge` slot renders the avatar corner dot (A1).
- [x] 2.4 Hunk 3: hand-written `<span class="badge bg-blue text-blue-fg ms-1" data-teamlead-indicator="text">{{ 'teamlead'|trans }}</span>` guarded by isTeamlead (A4) inside the d-none d-xl-block block, next to user.name; user.title div untouched.
- [x] 2.5 Run `vendor/bin/phpunit tests/Controller/LayoutControllerTest.php --group integration`. Confirmed all 6 tests PASS GREEN (4 new/regression + 2 pre-existing navigation tests).

## Phase 3: Verification

- [x] 3.1 `git diff --stat` — confirmed only the new template + LayoutControllerTest.php changed; no vendor/, entity, migration, subscriber, or voter files touched.
- [x] 3.2 Diff new template against vendor original — every non-"gppro:" line byte-identical (3 contiguous hunks only, confirmed via `diff -u`).
- [x] 3.3 Run full affected suite `vendor/bin/phpunit tests/Controller/LayoutControllerTest.php --group integration` (only test file touching navbar/avatar output, confirmed via repo grep for `navbar_user`/`data-teamlead-indicator`).
- [x] 3.4 Confirmed final diff: 66 lines (new template, all additions) + 71 lines (test file additions) = 137 changed lines, within the ~150-190 estimate and under the 400-line review budget.

## Phase 4: Cleanup

- [x] 4.1 No stray whitespace/TODO in new template.
- [x] 4.2 No `proposal.md` Success Criteria checklist exists in the recorded proposal artifact (Engram `sdd/team-lead-navbar-indicator/propose`, `What/Why/Where`-style format, no checkbox section) — nothing to mark; all spec requirements verified passing in Phase 3 instead.
