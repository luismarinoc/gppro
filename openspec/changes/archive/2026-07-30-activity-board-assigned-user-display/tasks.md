# Tasks: ActivityBoard Assigned User Display

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 60–120 authored lines |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR: template, focused regression coverage, validation |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Render/search assigned users and prove the nullable-user matrix | PR 1 | `vendor/bin/phpunit tests/Controller/ActivityBoardControllerTest.php` | Integration test boots Symfony and renders `/admin/project/{id}/board`; no separate browser harness needed | Revert `templates/project/_board_card.html.twig` and its added tests |

## Phase 1: Regression Tests (RED)

- [x] 1.1 Extend `tests/Controller/ActivityBoardControllerTest.php` with failing assertions for assigned-only visibility/search metadata, safe escaping, role-avatar coexistence, role-only cards, and all-null `Unassigned` behavior.

## Phase 2: Twig Implementation (GREEN)

- [x] 2.1 Update `templates/project/_board_card.html.twig` to append the assigned display name to `data-search`, render accessible assigned-user text/avatar with autoescaping, preserve technical/functional avatar markup, and show `Unassigned` only when all three user slots are null.
- [x] 2.2 Keep `assets/js/widgets/GpproActivityBoard.js` unchanged after verifying its existing case-insensitive `data-search` substring contract; do not alter `ActivityBoardCard` or server layers.

## Phase 3: Verification / Cleanup

- [x] 3.1 Run `vendor/bin/phpunit tests/Controller/ActivityBoardControllerTest.php` and confirm assigned-only, role-only, mixed, all-null, and markup-sensitive names pass.
- [x] 3.2 Run `./php-cs-fixer.sh core` and `./phpstan.sh test`; inspect the diff to confirm only the template and focused test file changed.
