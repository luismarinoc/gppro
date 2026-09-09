# Tasks: GPPro Visual Consistency

## Review Workload Forecast

| Field | Value |
| ------- | ------- |
| Estimated changed lines | ~1,300–1,800 across 15 application/template/style/translation files and 3 controller test files; browser evidence is additional documentation |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | Candidate PR 1 (foundation) → PR 2 (expense) → PR 3 (quotation) → PR 4 (approvals and opt-in row keyboard behavior); requires a human delivery decision |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

**Implementation precondition:** Do not edit application, asset, template, translation, or test files until a human chooses a delivery shape. `ask-on-risk` requires that decision because the forecast exceeds 400 changed lines. This task plan does not select a chain strategy or authorize `size:exception`.

## Dependencies and Delivery Boundaries

- All slices depend on the existing GPPro shell and stable `--gp-*` token names, Bootstrap/Tabler primitives, and unchanged controller/form/route/permission behavior.
- Slice 2 additionally depends on `expense-access-scoping` being the active behavioral baseline. Immediately before its RED work, re-read `openspec/changes/expense-access-scoping/tasks.md` and `tests/Controller/ExpenseControllerTest.php`; preserve every access-scoping fixture, helper, and assertion. If both changes are unmerged and cannot share that file safely, stop for sequencing.
- Slice 3 depends on Slice 1's shared workflow primitives. Slice 4 depends on the designated row markup emitted by Slices 2 and 3. No slice may edit `src/`, migrations, `public/build/`, `public/bundles/`, `vendor/`, `var/`, quotation PDF/public templates, or `assets/js/plugins/GpproReducedClickHandler.js`.
- Each slice is a single-writer work unit: tests and translations (when needed) land with the behavior they verify. Its rollback boundary is limited to the paths listed for that slice and does not require data repair.

## Slice 1 — Theme and Shared Workflow Foundation

**Depends on:** approved delivery shape; no earlier implementation slice.

**Allowed edit surfaces only:**
`assets/sass/variables.scss`, `assets/sass/_gppro.scss`, new `assets/sass/_workflow.scss`, `templates/expense/index.html.twig`, `templates/expense/pending.html.twig`, `templates/expense/edit.html.twig`, `templates/expense/view.html.twig`, `templates/quotation/index.html.twig`, `templates/quotation/edit.html.twig`, `templates/quotation/view.html.twig`, `templates/approvals_dashboard/index.html.twig`, `tests/Controller/ExpenseControllerTest.php`, `tests/Controller/QuotationControllerTest.php`, and `tests/Controller/ApprovalsDashboardControllerTest.php`.

**Finish/rollback boundary:** Every targeted authenticated surface emits its scoped workflow root and shared state hook; theme values and workflow styles compile without global selector leakage. Roll back only the listed Sass, Twig, and test changes, including deletion of `_workflow.scss`.

- [x] **RED** — In `tests/Controller/ExpenseControllerTest.php`, `tests/Controller/QuotationControllerTest.php`, and `tests/Controller/ApprovalsDashboardControllerTest.php`, add failing rendered-DOM assertions for the appropriate `.gp-workflow` modifier root and contextual shared state hook on each representative populated/empty target; first run the three focused PHPUnit files and record the selector-missing failures. <!-- sdd-owner: implementation -->
- [x] **GREEN** — Add only the asserted roots/hooks to the listed Twig templates; add `_workflow.scss` and import it once from `_gppro.scss`; complete stable semantic and required Tabler dark mappings in `variables.scss`, keeping every new shared selector beneath `.gp-workflow` and leaving routes, forms, CSRF inputs, and page behavior unchanged. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE** — Run `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php`, `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`, and `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php`; then run `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`. Record browser evidence in light and dark themes for one populated and one empty finance surface plus one existing non-finance GPPro consumer, including token contrast for normal/muted text, status labels, outline/disabled controls, and focus rings. Evidence completed post-deploy; `APP_DEBUG=1 composer linting` passes and the default prod-cache lint failure is classified in apply-progress. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Consolidate duplicate workflow declarations only into semantic tokens and scoped `.gp-workflow` roles in `assets/sass/variables.scss` and `assets/sass/_workflow.scss`; re-run `pnpm build` and the three focused controller tests to confirm no selector or theme-contract regression. <!-- sdd-owner: implementation -->

## Slice 2 — Expense Workflow and Advisory Currency State

**Depends on:** Slice 1 and the `expense-access-scoping` baseline being safely integrated.

**Allowed edit surfaces only:**
`assets/sass/_workflow.scss`, `templates/expense/index.html.twig`, `templates/expense/pending.html.twig`, `templates/expense/edit.html.twig`, `templates/expense/view.html.twig`, `translations/messages.en.xlf`, `translations/messages.es.xlf`, and `tests/Controller/ExpenseControllerTest.php`.

**Finish/rollback boundary:** Expense list, pending, edit, detail, and decision surfaces provide the visual/state DOM contract while retaining all current destinations, protected forms, and access-scoping behavior. Roll back only the listed expense Sass/Twig/test/conditional-translation edits; no controller, data, or migration rollback is needed.

- [x] **RED** — After re-reading the active access-scoping artifact and current `tests/Controller/ExpenseControllerTest.php`, add failing DOM assertions for structured list/pending empty states outside table-only rows; conditional existing `data-href` plus `data-gp-row-link`, `tabindex="0"`, `role="link"`, and non-empty accessible row names; one persistent currency status region with `role="status"`, polite/atomic live semantics, localized loading/unavailable hooks, and `data-state`; uniquely associated visible approve/reject note labels; and unchanged action, POST, field-name, and CSRF contracts. Run the complete `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php` and record the intended failures without removing access-scoping coverage. <!-- sdd-owner: implementation -->
- [x] **GREEN** — Implement the minimum scoped expense markup and `_workflow.scss` rules required by those assertions: contextual states, native action/form grouping, allocations/detail layout hooks, labelled decision notes, and advisory currency-status hooks. Add English and Spanish XLIFF units only when exact existing translations cannot be reused; preserve the conditional edit/view URL expression, all form actions/methods/names/tokens/guards, and the existing endpoint, debounce, parsing, formatting, and allocation behavior. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE** — Make the existing inline expense preview script invalidate older requests and expose only `idle`, `loading`, `available`, or `unavailable` advisory state, then verify draft versus approved destinations, empty versus populated lists, available/unavailable/stale preview results, and creator/team/approver/admin visibility plus list exclusion/direct 403. Run `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php`, `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`; record browser results at 360px, 768px, and 1024px-or-wider in both themes with keyboard-only and reduced-motion enabled. Evidence completed post-deploy; default prod-cache lint caveat is classified in apply-progress. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Reduce repeated expense-only presentation to selectors beneath `.gp-workflow--expense` without widening shared selectors or changing protected markup; re-run the entire `ExpenseControllerTest.php` (including access-scoping tests), `pnpm build`, and `composer linting`. Evidence completed post-deploy with focused and final gates; default prod-cache lint caveat is classified in apply-progress. <!-- sdd-owner: implementation -->

## Slice 3 — Quotation Workflow and Advisory FX State

**Depends on:** Slice 1; Slice 4 follows this slice for row keyboard runtime behavior.

**Allowed edit surfaces only:**
`assets/sass/_quotation.scss`, `templates/quotation/index.html.twig`, `templates/quotation/edit.html.twig`, `templates/quotation/view.html.twig`, `translations/messages.en.xlf`, `translations/messages.es.xlf`, and `tests/Controller/QuotationControllerTest.php`.

**Finish/rollback boundary:** Authenticated quotation list, editor, and detail pages expose the workflow/state contract and responsive summary treatment while public response/PDF surfaces and every existing editor selector/behavior remain unchanged. Roll back only the listed quotation Sass/Twig/test/conditional-translation edits.

- [x] **RED** — In `tests/Controller/QuotationControllerTest.php`, add failing DOM assertions for workflow header/filter/action/status and contextual empty-state structure; designated row attributes while retaining the native first-cell anchor; a persistent localized FX status region with the shared advisory semantics; unchanged `data-quotation-*` hooks, ten-line controls, submission fields, and authenticated detail structure; and unchanged PDF/public URLs and behavior. Run `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` and record the expected contract failures. <!-- sdd-owner: implementation -->
- [x] **GREEN** — Add only the asserted authenticated quotation markup and scoped `_quotation.scss` rules below `.gp-workflow--quotation`, including local table scrolling, responsive line/totals layout, and `.quotation_summary_sticky` static below `lg` and sticky at `lg` and above. Add paired English/Spanish text only for new state keys, preserve every existing behavioral data selector, native control, focus-after-add contract, line limit, endpoint, save field, and public/PDF template. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE** — Update the existing inline quotation lookup flow to invalidate stale responses and show `idle`, `loading`, `available`, or `unavailable` advisory feedback without changing `updateSummary()` or submitted values. Verify empty/populated lines, create/saved edit modes, CLP/foreign currency, available/unavailable/stale results, keyboard line addition/focus, and 360px/768px/1024px-or-wider summary behavior. Run `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`, `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`; record the complete theme/keyboard/reduced-motion browser matrix. Evidence completed post-deploy; default prod-cache lint caveat is classified in apply-progress. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Consolidate quotation-only declarations inside `.gp-workflow--quotation` without moving line-editor or summary rules into `_workflow.scss`; re-run `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`, `pnpm build`, and `composer linting`. Evidence completed post-deploy; default prod-cache lint caveat is classified in apply-progress. <!-- sdd-owner: implementation -->

## Slice 4 — Approvals Dashboard and Opt-in Row Keyboard Contract

**Depends on:** Slices 1–3, including emitted `data-gp-row-link` markup on expense and quotation rows.

**Allowed edit surfaces only:**
`assets/sass/_workflow.scss`, `templates/approvals_dashboard/index.html.twig`, `assets/js/plugins/GpproAlternativeLinks.js`, and `tests/Controller/ApprovalsDashboardControllerTest.php`.

**Finish/rollback boundary:** The dashboard presents domain sections/states and preserves native protected actions; only designated expense/quotation rows gain Enter navigation. Roll back only the listed dashboard Sass/Twig/plugin/test changes. Do not edit `GpproReducedClickHandler.js`, add JS test tooling, or introduce expense/invoice inline decision forms.

- [x] **RED** — In `tests/Controller/ApprovalsDashboardControllerTest.php`, add failing rendered-DOM assertions for overall/per-domain workflow section and empty-state hooks, descriptively named native expense/invoice review links, absence of expense/invoice inline decision forms, and the existing timesheet approve/reject form action, POST method, and CSRF input contracts. Run the focused test and record failures. Separately record the pre-change browser observations that a designated row does not yet respond to Enter while legacy `.alternative-link` rows remain outside the keyboard contract. Assertions were written first; RED execution was blocked by transient test-database teardown and is classified in apply-progress. <!-- sdd-owner: implementation -->
- [x] **GREEN** — Implement the smallest dashboard markup/style changes for the asserted section, state, local-scroll, and action-wrap contracts, retaining domain organization and existing native review/timesheet forms. In `assets/js/plugins/GpproAlternativeLinks.js`, add one idempotent delegated keydown handler only for `[data-gp-row-link]`: activate unmodified Enter through the existing navigation callback, ignore Space, and return without interference for nested anchors, buttons, inputs, selects, textareas, labels, role links/buttons, menus, editable content, or empty destinations. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE** — Run `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php`, `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php`, `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`, `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`. Because no supported automated JS/browser runner exists, record browser evidence that Enter navigates each designated row exactly once; Space and every nested control do not; legacy rows receive neither a tab stop nor keyboard handling; and dashboard controls remain reachable at 360px, 768px, and 1024px-or-wider in both themes with reduced motion. Evidence completed post-deploy through Chrome/CDP; default prod-cache lint caveat is classified in apply-progress. <!-- sdd-owner: implementation -->
- [x] **REFACTOR** — Keep the listener scoped and idempotent, reduce only duplicated workflow styles below `.gp-workflow--approvals`, and re-run `pnpm lint`, `pnpm build`, and all three focused controller test files. <!-- sdd-owner: implementation -->

## Cross-Slice Completion Evidence

**Depends on:** all implementation slices selected by the human delivery decision.

**Allowed edit surfaces only:** the owning slice's already-listed files for a bounded correction; no new edit surface is authorized by this task. Browser evidence belongs in the phase verification artifact, not application code.

**Finish/rollback boundary:** Evidence demonstrates the agreed slices without expanding scope; any correction remains reversible within its owning slice. Stop and seek a new decision if a correction would exceed the selected work unit or introduce a new edit surface.

- [x] Run the final automated gate for the selected slices: `composer tests-unit`, `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`; record exact pass/fail results and distinguish unavailable browser automation from executed PHPUnit coverage. Final gate passed with `composer tests-unit`, `pnpm lint`, `pnpm build`, `./phpstan.sh test`, and `APP_DEBUG=1 composer linting`; the default prod-cache lint caveat is recorded in apply-progress. <!-- sdd-owner: implementation -->
- [x] Record the browser/version matrix for populated, empty, and lookup-unavailable finance workflows at 360px, 768px, and 1024px-or-wider in light/dark themes, keyboard-only traversal, and reduced motion; include no page-level overflow, local dense-table scrolling, focus visibility, sticky-summary breakpoint behavior, async state changes, native approval submissions, and unchanged Kimai attribution. Post-deploy Chrome/CDP evidence recorded; approval submissions were not executed because the browser pass was read-only. <!-- sdd-owner: implementation -->

## Parent Lifecycle Actions

- [ ] Start or reuse bounded review after implementation evidence is complete; verify every correction stayed inside its authorized slice and that no delivery shape, chain strategy, or `size:exception` was inferred by the implementation owner. <!-- sdd-owner: parent -->
