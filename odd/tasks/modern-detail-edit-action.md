# Prefer the modern detail edit action

## Intent and authorization
User rejected retaining legacy top Edit over the approved contextual card pencil and instructed continuation. Local correction restores the new card control and removes equivalent top action from customer/project details only. No commit/push requested anew in this correction turn; no production changes.

## Tasks
- [x] A1: Restore can_edit-gated contextual card pencil and filter stable edit key before legacy page-actions rendering.
- [x] A2: Independently verify editable/view-only and desktop/mobile action behavior; correct regression selector defect; final recheck passed.
- [x] A3: Report local validation with explicit full-application limits.

## Files and behavior
Changed templates/customer/details.html.twig, templates/project/details.html.twig, tests/Controller/CustomerControllerTest.php, tests/Controller/ProjectControllerTest.php. Diff52 insertions. No Sass, controllers, subscribers, voters, shared macros or permission configuration modified. Existing can_edit expression controls contextual pencil. Existing action event list is locally filtered by stable edit key before shared macro, removing legacy edit from desktop and mobile while retaining permissions/board/etc. Test assertions use locale/base-path-preserving href destinations, not desktop-only CSS classes.

## Validation evidence
Writer: php-l both tests, git diff-check and standalone Twig syntax pass. Independent verifier first ran current complete templates and installed card/widgets/desktop/mobile/button/attribute macros under deterministic app stubs:12 combinations for two entities x editable/noneditable x full/edit-only/empty actions pass; editable card exactly1 correct modal edit, noneditable0, top desktop/mobile entity edit0, other action links preserved, original collections unchanged;4 absent/null page_setup guards pass. This caught mobile selector defect in initial tests because mobile macros lack desktop action-key classes. Tests corrected to href checks.
Final independent recheck: current4-file diff52 insertions, diff-check and both php-l pass; real-Twig harness4 cases across root and /gppro base paths pass: card edit1, desktop/mobile legacy edit0 each, permissions/board1 each. URL derivation preserves base path and locale and strips terminal /details only. No remaining concrete defect found.

## Limits
No full PHPUnit/PHPStan/fixer because configured database reset, protected cache and mutating behavior conflict with constraints. Actual router/voters/event dispatch, browser layout and modal JavaScript not run against unpublished correction. Harness uses real macros/templates but simulated shell/services/entities. Native assessment unavailable package-local-binary-missing; independent verifier substituted, not native approval.

## Next step
Local correction validated for intended presentation/permission wiring. Await explicit publication instruction before commit/push. After deployment, read-only browser-check actual customer/project details as editable admin and one view-only role if available.
