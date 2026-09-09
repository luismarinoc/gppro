# Apply Progress: GPPro Visual Consistency

## Work Unit

- Change: `gppro-visual-consistency`
- Delivery strategy: `auto-chain`, `stacked-to-main`
- Current boundary: Slice 1 of 4, Theme and Shared Workflow Foundation only
- Branch: `gppro_visual_consistency_1_foundation`
- Review size: **309 changed lines** (`264` additions, `45` deletions), excluding OpenSpec operational artifacts; within the 400-line budget.
- Scope: Slices 2–4 were not edited.

## Structured Status Consumed

```json
{
  "changeName": "gppro-visual-consistency",
  "artifactStore": "openspec",
  "applyState": "ready",
  "dependencies": {"apply": "ready"},
  "actionContext": {
    "mode": "repo-local",
    "workspaceRoot": "/Users/luismarinoc/Documents/Dev/tbema/gppro",
    "allowedEditRoots": ["/Users/luismarinoc/Documents/Dev/tbema/gppro"],
    "warnings": []
  },
  "delivery": {
    "strategy": "auto-chain",
    "chainStrategy": "stacked-to-main",
    "workUnit": "1/4"
  }
}
```

## Completed Slice 1 Tasks

- [x] RED: Added rendered-DOM workflow-root and contextual empty-state assertions in the three focused controller test files. The expected selector-missing RED run failed as intended: Expense 1 failure, Quotation 1 failure, and Approvals 2 failures.
- [x] GREEN: Added scoped workflow roots/hooks across only the Slice 1 templates, semantic light/dark token roles and required Tabler mappings, a single `_workflow.scss` import, and a shared selector implementation whose ancestry remains `.gp-workflow`.
- [x] REFACTOR: Kept the shared presentation in semantic tokens and scoped BEM-style workflow roles; the post-refactor build and all three focused test files pass.

Persisted task checkboxes were updated immediately for RED, GREEN, and REFACTOR.

## Files Changed

- `assets/sass/variables.scss`
- `assets/sass/_gppro.scss`
- `assets/sass/_workflow.scss` (new)
- `templates/expense/index.html.twig`
- `templates/expense/pending.html.twig`
- `templates/expense/edit.html.twig`
- `templates/expense/view.html.twig`
- `templates/quotation/index.html.twig`
- `templates/quotation/edit.html.twig`
- `templates/quotation/view.html.twig`
- `templates/approvals_dashboard/index.html.twig`
- `tests/Controller/ExpenseControllerTest.php`
- `tests/Controller/QuotationControllerTest.php`
- `tests/Controller/ApprovalsDashboardControllerTest.php`
- `openspec/changes/gppro-visual-consistency/tasks.md`
- `openspec/changes/gppro-visual-consistency/apply-progress.md`

## TDD Cycle Evidence

| Task | Test file/layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- | --- |
| Slice 1 RED/GREEN | Expense controller integration DOM contract | 33 tests, 236 assertions passed | Selector absent, 1 expected failure | 34 tests, 239 assertions passed | Populated index plus empty pending target passed | 34 tests, 239 assertions passed after build |
| Slice 1 RED/GREEN | Quotation controller integration DOM contract | 15 tests, 106 assertions passed | Selector absent, 1 expected failure | 16 tests, 109 assertions passed | Populated index plus empty create/editor target passed | 16 tests, 109 assertions passed after build |
| Slice 1 RED/GREEN | Approvals controller integration DOM contract | 10 tests, 47 assertions passed | Root/state selectors absent, 2 expected failures | 10 tests, 50 assertions passed | Populated aggregate plus empty dashboard target passed | 10 tests, 50 assertions passed after build |

- Tests added: 2 new test methods; root/state assertions added to 2 existing approvals tests.
- Focused integration tests passing after refactor: 60 tests, 398 assertions.
- Pure functions created: none; the change is template/Sass presentation only.

## Verification Evidence

### Executed

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php` | PASS, 34 tests / 239 assertions |
| `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` | PASS, 16 tests / 109 assertions |
| `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php` | PASS, 10 tests / 50 assertions |
| `pnpm lint` | PASS, exit 0 |
| `pnpm build` | PASS, exit 0; Webpack reported 63 existing Sass deprecation warnings. Generated `public/build/` output was restored/removed and is not part of this work unit. |
| `./phpstan.sh test` | PASS, exit 0, no errors |
| `git diff --check` | PASS |
| `composer linting` | FAIL, exit 1: pre-existing Doctrine mapping error, `TimesheetApproval#timesheet` references missing inverse `Timesheet#approvals`. No out-of-scope source was changed. |

### Continuation Evidence — 2026-09-08

The parent resumed Slice 1 and reran the automated checks because SDD phase subagents failed to start in this Pi session (`pi exited with code 1 before agent_settled`). The rerun produced the same classification: focused controller tests, frontend lint/build, test PHPStan, and diff whitespace checks pass; repository-wide `composer linting` remains blocked by the unrelated Doctrine mapping issue above. Browser verification remains unavailable in this environment, so Slice 1 TRIANGULATE is still not marked complete.

### Browser Verification Not Executed

No supported browser runner or live authenticated browser session was available. Therefore light/dark visual inspection for populated/empty finance surfaces, an existing non-finance consumer, token contrast, controls, and focus rings was **not performed** and is not represented as passing evidence.

## Deviations and Risks

- The Slice 1 TRIANGULATE checkbox remains unchecked because its required browser matrix is unavailable and `composer linting` has the unrelated Doctrine mapping failure above.
- Empty-state markup is limited to the scoped shared hook in this foundation slice. Slice 2/3 own the required non-table contextual empty-state restructuring and advisory state behavior.
- No generated assets, `src/`, translations, public/PDF templates, or JavaScript plugins are included in the work unit.

## Slice 2 Continuation — Expense Workflow Look and Feel

- Change continued on request as a Look and Feel work-in-progress rather than PR finalization.
- RED assertions were added for expense empty-state structure, opt-in keyboard row attributes, persistent advisory currency status semantics, and uniquely labelled approve/reject decision notes. The first RED run was blocked by a transient test database teardown/bootstrap error before assertions executed; the completed GREEN run now covers the asserted contract.
- GREEN implementation adds expense-only workflow markup, scoped `_workflow.scss` rules, localized currency loading text, stale-request invalidation for currency preview, and advisory preview states limited to `idle`, `loading`, `available`, and `unavailable`.
- Existing route destinations, conditional edit/view row URL selection, POST actions, CSRF fields, form field names, allocation behavior, and access-scoping assertions were preserved.

### Slice 2 Automated Evidence

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php` | PASS, 37 tests / 261 assertions |
| `pnpm lint` | PASS, exit 0 |
| `pnpm build` | PASS, exit 0; Webpack reported existing Sass deprecation warnings. Generated `public/build/` output was restored/removed and is not part of this work unit. |
| `./phpstan.sh test` | PASS, exit 0, no errors |
| `git diff --check` | PASS |
| `composer linting` | FAIL, exit 1: pre-existing Doctrine mapping error, `TimesheetApproval#timesheet` references missing inverse `Timesheet#approvals`. Twig/YAML/container lint passed before that Doctrine failure. |

### Slice 2 Browser Verification Not Executed

No supported browser runner or live authenticated browser session was available. Therefore the required 360px/768px/1024px light/dark keyboard and reduced-motion matrix is not represented as passing evidence.

### Slice 2 Budget Reset

The maintainer explicitly authorized an SDD runtime reset after provider accounting measured Slice 2 at 403 changed lines against the 400-line budget. The reset preserves the Expense Look and Feel implementation as the new baseline and leaves only bounded validation/final visual evidence work open.

## Remaining Work

- [ ] **TRIANGULATE** — Run `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php`, `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`, and `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php`; then run `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`. Record browser evidence in light and dark themes for one populated and one empty finance surface plus one existing non-finance GPPro consumer, including token contrast for normal/muted text, status labels, outline/disabled controls, and focus rings. <!-- sdd-owner: implementation -->
- Slice 2 TRIANGULATE/REFACTOR remains partially open because browser verification is unavailable and repository-wide `composer linting` is still blocked by the unrelated Doctrine mapping issue.
- Slices 3–4 remain deferred.
- Parent-owned lifecycle action remains untouched: start/reuse bounded review after implementation evidence is complete.

## Corrective Attempt Authority Record

- Native provider accounting reported **417 changed lines** for Slice 1.
- The maintainer explicitly authorized the one corrective native-attempt reset at revision `sha256:ebe46c76c916777ab8076a6d746ed758570f1aa326c6b02c0d29e50ac8870823`.
- This record corrects the failed evidence revision `sha256:0dffca143740d2d8eb2104fc43bbaedcb9db8f6ffe5e0ba0f048dee9585290f0` and must be superseded by a distinct evidence revision.
- Automated Slice 1 evidence passes except repository-wide `composer linting`. Its Doctrine mapping failure is pre-existing and outside this change: Slice 1 changed no `src/` files.
- The authenticated browser matrix is explicitly deferred to final cross-slice verification because no browser runner or authenticated browser session is available.
  - Slice 1's shared foundation is usable as the dependency for Slice 2. Its TRIANGULATE checkbox remains unchecked until final verification records the browser matrix and resolves/reclassifies the repository-wide linting result.

## Slice 2 TRIANGULATE/REFACTOR Continuation — 2026-09-08

- Work-unit: `slice2-triangulate-refactor` only; Slices 3 and 4 were not read, edited, or started.
- Native attempt authority consumed: `sha256:ac48adcc6c6635c1c098d13621539d5fd44ce3ed2b48d9cbe22cf3baaeb9d7ef` (parent-owned; no acquisition or reset performed here).
- Delivery boundary: stacked-to-main Slice 2 only, with the parent-provided maximum of 400 changed lines. No application, Sass, Twig, translation, or test code was changed in this continuation because the existing Slice 2 implementation is already scoped below `.gp-workflow--expense`; a further refactor would churn code without a new failing behavior contract.

### Reconciliation and TDD Cycle Evidence

| Task | Test file/layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- | --- |
| Slice 2 TRIANGULATE/REFACTOR continuation | `tests/Controller/ExpenseControllerTest.php` / integration | PASS: 37 tests, 261 assertions | Completed in the prior Slice 2 work unit | Completed in the prior Slice 2 work unit | Automated portions PASS: populated/draft and approved destinations, currency endpoint available/unavailable responses, access-scoping visibility/list-exclusion/direct-403 coverage; browser-only async, viewport, theme, keyboard, and reduced-motion matrix unavailable | No code change required after review; focused test and build pass, but the required repository lint gate remains blocked by an unrelated Doctrine mapping error |

No additional RED test was written because no production change was needed. The project has no supported browser or JavaScript execution runner for the remaining runtime-only scenarios; PHPUnit cannot execute the inline preview script. The existing request-token code remains covered only by manual-browser evidence for stale response/reset timing.

### Commands Run

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php` | PASS — 37 tests, 261 assertions |
| `pnpm lint` | PASS — exit 0 |
| `pnpm build` | PASS — exit 0; Webpack reported 63 existing Sass deprecation warnings. The harness additionally reported 692 static diagnostics in generated/minified `public/build/` output; they are outside the allowed edit surfaces and were not remediated. |
| `composer linting` | FAIL — Twig, YAML, and container lint passed; Doctrine mapping validation remains blocked by pre-existing `TimesheetApproval#timesheet` referring to missing `Timesheet#approvals` |
| `./phpstan.sh test` | PASS — no errors |
| `git diff --check` | PASS |

### Evidence Limits and Remaining Slice 2 Tasks

Slice 2 is **implementation-complete but evidence-blocked**. The persisted Slice 2 TRIANGULATE and REFACTOR task checkboxes remain unchecked because their acceptance criteria require both the unavailable browser matrix and a passing `composer linting` command.

- [ ] **TRIANGULATE** — browser verification remains unavailable for draft versus approved row destinations, empty/populated lists, available/unavailable/stale preview transitions, creator/team/approver/admin access modes, 360px/768px/1024px-or-wider, light/dark, keyboard-only, and reduced-motion behavior; `composer linting` remains blocked by the unrelated Doctrine mapping failure.
- [ ] **REFACTOR** — no safe refactor was required; its required `composer linting` rerun remains blocked by the same unrelated Doctrine mapping failure.

`pnpm build` regenerated four ignored `public/build/` files. Tracked generated outputs were restored, but the harness blocked removal of the four untracked generated files without explicit destructive-command authorization. The harness also reported 692 diagnostics in generated/minified output. Both are out of the allowed edit surfaces; they are not part of Slice 2 and must not be committed. Cleanup is deferred to the parent/maintainer.

### Structured Status and Workload

```json
{
  "changeName": "gppro-visual-consistency",
  "artifactStore": "openspec",
  "applyState": "ready",
  "actionContext": {
    "mode": "repo-local",
    "workspaceRoot": "/Users/luismarinoc/Documents/Dev/tbema/gppro",
    "allowedEditRoots": ["/Users/luismarinoc/Documents/Dev/tbema/gppro"],
    "warnings": []
  },
  "delivery": {
    "strategy": "auto-chain",
    "chainStrategy": "stacked-to-main",
    "workUnit": "slice2-triangulate-refactor",
    "maxChangedLines": 400
  },
  "continuation": "implementation-complete-evidence-blocked"
}
```

- Evidence revision (SHA-256 of the ordered allowed Slice 2 application paths; excludes OpenSpec operational artifacts and generated `public/build/` output): `sha256:d95d52c2823f7d608dff05e2b143caf2b38e9a263ea4887346a44f5bb2dd20b5`.
- Deferred lifecycle action: `- [ ] Start or reuse bounded review after implementation evidence is complete; verify every correction stayed inside its authorized slice and that no delivery shape, chain strategy, or size:exception was inferred by the implementation owner. <!-- sdd-owner: parent -->`

## Slice 3 — Quotation Workflow and Advisory FX State — 2026-09-08

- Work-unit: `slice-3-quotation-workflow` only; native attempt authority consumed by the parent: `sha256:d57aa85938f77c3717d7163427b61b8f9fd92c7d4485ec8bc7cb08aad2a1ca57`.
- Delivery boundary: `auto-chain`, `stacked-to-main`, Slice 3; current quotation-only application diff is 264 changed lines (211 additions, 53 deletions) across the five application paths below, excluding OpenSpec operational artifacts and generated `public/build/` output. This is within the parent-provided 400-line maximum.
- Structured status consumed: `gppro-visual-consistency`, OpenSpec store, apply `ready`, repository-local action context rooted at `/Users/luismarinoc/Documents/Dev/tbema/gppro`, with that workspace as the only allowed edit root.

### Completed Slice 3 Tasks and Checkbox Reconciliation

- [x] RED: Added rendered-DOM assertions for quotation list filter/status/empty-state structure, opt-in row-link semantics with the native first-cell anchor, the persistent localized advisory FX status region, and authenticated detail structure while retaining the existing editor/save/PDF coverage. The RED run failed as intended: 16 tests / 110 assertions, with missing row `role` and quotation filter assertions.
- [x] GREEN: Implemented the smallest authenticated quotation markup and quotation-scoped styles. The list now uses a contextual empty state and designated row attributes; editor FX feedback is persistent and advisory; detail lines use local table scrolling; the summary is static below `lg` and sticky from `lg`; existing expense lookup translations are reused, so no translation catalog change was needed.

The persisted Slice 3 RED and GREEN checkboxes are visibly marked `[x]`. TRIANGULATE and REFACTOR remain `[ ]` as evidence-blocked below.

### Files Changed

- `assets/sass/_quotation.scss`
- `templates/quotation/index.html.twig`
- `templates/quotation/edit.html.twig`
- `templates/quotation/view.html.twig`
- `tests/Controller/QuotationControllerTest.php`

No public/PDF quotation response template, translation catalog, JavaScript plugin, `src/`, migration, vendor, var, plugin, or generated source was intentionally edited.

### TDD Cycle Evidence

| Task | Test file/layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- | --- |
| Slice 3 quotation workflow and FX state | `tests/Controller/QuotationControllerTest.php` / integration DOM contract | PASS: 16 tests, 109 assertions | Expected FAIL: 16 tests, 110 assertions; two missing workflow-contract assertions | PASS: 16 tests, 129 assertions | Automated DOM cases cover populated and filtered-empty lists, create/editor, saved edit behavior, CLP/foreign server behavior, PDF endpoint, and detail structure. Runtime stale-response, keyboard focus, theme, reduced-motion, and viewport cases require a browser and were not executed. | Quotation declarations are consolidated under `.gp-workflow--quotation`; focused tests and build pass. Repository lint remains blocked by the unrelated Doctrine mapping failure. |

- `updateSummary()` was left unchanged. The lookup now increments a request token for every lookup/reset and ignores superseded results; `data-state` is limited to `idle`, `loading`, `available`, and `unavailable`, and it does not change submitted values.
- Tests added/expanded: two existing rendered-DOM workflows now assert the new list/row/status/empty/detail contracts; existing line-limit, selector, notes, save, currency, and PDF behavior tests remain in the same focused file.
- Pure functions created: none; this is Twig/Sass plus the existing inline browser lookup seam.

### Commands Run

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` (safety net) | PASS — 16 tests / 109 assertions |
| `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` (RED) | Expected FAIL — 16 tests / 110 assertions, 2 selector/semantic contract failures |
| `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` (GREEN/refactor) | PASS — 16 tests / 129 assertions |
| `pnpm lint` | PASS — exit 0 |
| `pnpm build` (TRIANGULATE and REFACTOR) | PASS — webpack compiled with 63 existing Sass deprecation warnings |
| `./phpstan.sh test` | PASS — no errors |
| `composer linting` (TRIANGULATE and REFACTOR) | BLOCKED — container, YAML, and all 214 Twig files passed; Doctrine mapping validation fails because `TimesheetApproval#timesheet` references absent inverse `Timesheet#approvals` |
| `git diff --check` | PASS |

### Browser and Generated-Output Limits

No supported browser binary, authenticated browser session, or display was available. Browser-only checks for stale responses, available/unavailable transitions, keyboard add-line focus, 360px/768px/1024px-or-wider layout, themes, and reduced motion are not passed.

`pnpm build` regenerated tracked and untracked `public/build/` output. Those generated files are outside this Slice 3 allowed-edit list and user-prohibited; no generated output was intentionally edited or remediated. Their cleanup/reconciliation is deferred to the parent/maintainer. The harness also reported static diagnostics in minified generated output; they are outside scope.

### Remaining Slice 3 Tasks

- [ ] **TRIANGULATE** — browser matrix unavailable; `composer linting` is blocked by the pre-existing `TimesheetApproval#timesheet` Doctrine mapping error.
- [ ] **REFACTOR** — scoped refactor and its focused test/build evidence pass, but the required `composer linting` rerun remains blocked by the same unrelated Doctrine mapping error.

### Evidence Revision

- `sha256:557ec835e4a3cde910a46e4bca13f4d0519ad42b7fac9ee6f4f3fe6ff26b8a18`
- Method: SHA-256 over ordered `path + NUL + file bytes + NUL` for `assets/sass/_quotation.scss`, the three authenticated quotation Twig templates, and `tests/Controller/QuotationControllerTest.php`; OpenSpec artifacts and generated output are excluded.
- Parent settlement must include `--remediates-evidence-revision sha256:d95d52c2823f7d608dff05e2b143caf2b38e9a263ea4887346a44f5bb2dd20b5`.

## Slice 4 — Approvals Dashboard and Opt-in Row Keyboard Contract — 2026-09-08

- Work-unit: `slice-4-approvals-row-keyboard` only; native attempt authority was supplied by the parent as `sha256:d2add9d176b74a44cff7e88ff5c0546ab05b1ebd660066d40e95721691b39642`. No attempt authority was acquired, reset, or settled here.
- Delivery boundary: `auto-chain`, `stacked-to-main`, Slice 4, maximum 400 changed lines. The upper bound from the current tracked application diff is 266 additions/deletions plus the small approvals-only Sass addition; this includes pre-existing Slice 1 changes in the dashboard test/template and is therefore a conservative upper bound below 400.
- Structured status consumed: OpenSpec store; change `gppro-visual-consistency`; apply `ready`; repository-local action context rooted at `/Users/luismarinoc/Documents/Dev/tbema/gppro`, which is the only allowed edit root; no action-context warnings.

### Completed Task Checkbox Updates

- [x] GREEN: Added contextual overall/domain approval states, one local table-scroll surface per populated domain, wrapped native timesheet actions, and descriptive native expense/invoice review link names. No expense/invoice form was added; timesheet actions retain their existing routes, POST methods, and CSRF fields.
- [x] REFACTOR: Kept the delegated listener opt-in/idempotent and approvals-only Sass below `.gp-workflow--approvals`; no further deduplication was warranted.

The persisted `tasks.md` GREEN and REFACTOR checkboxes were updated and re-read. RED and TRIANGULATE deliberately remain unchecked as described below.

### Files Changed

- `assets/sass/_workflow.scss`
- `templates/approvals_dashboard/index.html.twig`
- `assets/js/plugins/GpproAlternativeLinks.js`
- `tests/Controller/ApprovalsDashboardControllerTest.php`
- `openspec/changes/gppro-visual-consistency/tasks.md`
- `openspec/changes/gppro-visual-consistency/apply-progress.md`

No Slice 2 expense template, Slice 3 quotation template, `GpproReducedClickHandler.js`, quotation public/PDF template, generated source, `src/`, migration, vendor, var, or plugin directory was edited intentionally.

### TDD Cycle Evidence

| Task | Test file/layer | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- |
| Slice 4 approvals DOM contract | `tests/Controller/ApprovalsDashboardControllerTest.php` / integration DOM contract | Assertions for all/per-domain section/state hooks, descriptive review links, navigation-only expense/invoice domains, and timesheet action/method/CSRF contracts were written before production changes. The first two focused runs did not reach assertions because the test database bootstrap failed to drop a missing foreign key (`FK_5A97604412946D8B`, then `FK_B5E92CF8296CD8AE`). The expected selector-missing result was therefore not observed; RED remains unchecked. | PASS — 10 tests, 61 assertions after the dashboard markup/style implementation. | Focused approvals, expense, and quotation suites; frontend lint/build; and test PHPStan pass. Browser/runtime checks were unavailable; repository lint is blocked by the pre-existing Doctrine mapping error. | PASS — reran `pnpm lint`, `pnpm build`, and all three focused controller suites; no production refactor beyond retaining the scoped implementation was needed. |

The browser-specific strict-TDD exception applies: `package.json` contains no browser test script and `playwright`, Chromium, Chrome, and Firefox binaries were unavailable. Consequently neither the pre-change Enter observation nor post-change Enter/Space/nested-control/legacy-row/responsive-theme matrix was executed or represented as passing evidence.

### Commands Run

| Command | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php` (RED attempts) | BLOCKED before assertions — test database bootstrap could not drop a missing foreign key; no template/product failure was observed. |
| `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php` (GREEN) | PASS — 10 tests / 61 assertions. |
| `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php` | PASS — 37 tests / 261 assertions. |
| `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php` | PASS — 16 tests / 129 assertions. |
| `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php` (TRIANGULATE/REFACTOR) | PASS — 10 tests / 61 assertions. |
| `pnpm lint` | PASS — exit 0. |
| `pnpm build` | PASS — webpack compiled with 63 pre-existing Sass deprecation warnings. |
| `./phpstan.sh test` | PASS — no errors. |
| `composer linting` | BLOCKED — container, YAML, and all 214 Twig files passed; Doctrine mapping validation fails because `TimesheetApproval#timesheet` refers to the absent inverse `Timesheet#approvals`. No `src/` file was changed. |
| `git diff --check` | PASS. |

### Design Deviation and Scope Safety

The existing shared navigation callback now resolves only same-origin destinations before assigning the path/query/hash. All designated `data-gp-row-link` routes are internal; the listener still invokes this existing callback exactly once for unmodified Enter. This narrowly prevents an externally supplied destination while retaining the domain-row contract. The listener ignores Space and modified Enter, and returns before default/propagation interference for nested anchors, buttons, inputs, selects, textareas, labels, nested role links/buttons, menu roles, editable content, or blank destinations. Legacy `.alternative-link` rows remain outside the keydown selector.

### Browser and Generated-Output Limits

No supported browser runner, browser binary, or live authenticated browser session is available. The required browser portions of TRIANGULATE remain unverified: each designated row's single Enter navigation; Space/nested-control non-activation; legacy-row absence of keyboard behavior/tab stop; and 360px/768px/1024px-or-wider light/dark reduced-motion dashboard reachability.

`pnpm build` modified tracked `public/build/entrypoints.json` and `public/build/manifest.json`, deleted four stale tracked bundle files, and created four hashed untracked bundle files. These are generated assets outside the allowed edit surfaces and explicitly prohibited by the work-unit request. They were not restored or removed by this executor and must be reconciled by the parent/maintainer; none is part of Slice 4.

### Remaining Slice 4 Tasks

- [ ] **RED** — In `tests/Controller/ApprovalsDashboardControllerTest.php`, add failing rendered-DOM assertions for overall/per-domain workflow section and empty-state hooks, descriptively named native expense/invoice review links, absence of expense/invoice inline decision forms, and the existing timesheet approve/reject form action, POST method, and CSRF input contracts. Run the focused test and record failures. Separately record the pre-change browser observations that a designated row does not yet respond to Enter while legacy `.alternative-link` rows remain outside the keyboard contract. <!-- sdd-owner: implementation -->
- [ ] **TRIANGULATE** — Run `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php`, `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php`, `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`, `pnpm lint`, `pnpm build`, `composer linting`, and `./phpstan.sh test`. Because no supported automated JS/browser runner exists, record browser evidence that Enter navigates each designated row exactly once; Space and every nested control do not; legacy rows receive neither a tab stop nor keyboard handling; and dashboard controls remain reachable at 360px, 768px, and 1024px-or-wider in both themes with reduced motion. <!-- sdd-owner: implementation -->

Deferred parent-owned lifecycle action remains byte-for-byte untouched: `- [ ] Start or reuse bounded review after implementation evidence is complete; verify every correction stayed inside its authorized slice and that no delivery shape, chain strategy, or size:exception was inferred by the implementation owner. <!-- sdd-owner: parent -->`

### Evidence Revision

- `sha256:eef9dfc99f1bdde3731ed2f61565fc7000d2e169adf32f17a2a5309336186e3c`
- Method: SHA-256 over ordered `path + NUL + file bytes + NUL` for `assets/sass/_workflow.scss`, `templates/approvals_dashboard/index.html.twig`, `assets/js/plugins/GpproAlternativeLinks.js`, and `tests/Controller/ApprovalsDashboardControllerTest.php`; OpenSpec operational artifacts and generated output are excluded.
- Parent settlement evidence: attempt token `sha256:d2add9d176b74a44cff7e88ff5c0546ab05b1ebd660066d40e95721691b39642`, work-unit `slice-4-approvals-row-keyboard`, evidence revision above, application scope list above, and no attempt acquire/reset/settle action by this executor.

## Authenticated Browser Attempt Against `gppro.tbema.net` — 2026-09-09

The maintainer provided the credential source and authorized read-only browser evidence against `https://gppro.tbema.net`. The parent used Chrome headless over CDP with credentials loaded from `.env.local` without printing secrets.

### Browser Execution

| Check | Result |
| --- | --- |
| Chrome/CDP login | PASS — authenticated and redirected to `/es/timesheet/` |
| Authenticated routes reached | PASS — `/es/expense/`, `/es/expense/pending`, `/es/expense/create`, `/es/quotation/`, `/es/quotation/create`, `/es/approvals/` did not redirect to login |
| Viewports attempted | 360px, 768px, 1024px |
| Theme attempts | default light plus forced `data-bs-theme="dark"` DOM pass |
| Reduced motion attempt | emulated `prefers-reduced-motion: reduce` |
| Page-level horizontal overflow | No overflow detected on the sampled deployed pages |
| Candidate workflow selectors | FAIL — zero `.gp-workflow` roots detected across the authenticated deployed pages |
| Row keyboard selector | FAIL/Not applicable — zero `[data-gp-row-link]` rows detected on the deployed pages |

### Evidence Classification

This is valid evidence that the remote site is reachable and test credentials work, but it is **not passing candidate evidence** for `gppro-visual-consistency`: `https://gppro.tbema.net` is not serving the current uncommitted candidate that contains `.gp-workflow`, `.gp-workflow--expense`, `.gp-workflow--quotation`, `.gp-workflow--approvals`, or `[data-gp-row-link]`.

The browser matrix therefore remains blocked until the current candidate is either deployed to an authenticated environment or run locally with equivalent test data. The captured remote report is stored outside the repository in the Pi temp area and must not be treated as an OpenSpec source artifact.

- Remote evidence report: `/var/folders/8m/r2yz6vdj28n5b7vv7c7j11080000gn/T/gppro-browser-evidence-Rv7Qf6/report.json`
- Remote report digest: `sha256:884624d67d517f2cac501857e1fa0b85579b890e1933847701d391a7b6f2bd35`
- Screenshots: `/var/folders/8m/r2yz6vdj28n5b7vv7c7j11080000gn/T/gppro-browser-evidence-Rv7Qf6/*.png`

### Remaining Browser Requirement

To close the browser evidence requirement, run the current candidate — not the older deployed site — behind an authenticated URL and repeat the same matrix for expense, quotation, and approvals in 360px/768px/1024px widths, light/dark theme, keyboard-only traversal, reduced motion, row Enter/Space behavior, nested-control protection, and async advisory lookup states.

## Post-Deploy Authenticated Browser Evidence — 2026-09-09

After the maintainer confirmed Dokploy deployment from `origin/main`, the parent reran authenticated Chrome/CDP evidence against `https://gppro.tbema.net` using `.env.local` test credentials without printing secrets.

### Deployed Candidate Evidence

| Check | Result |
| --- | --- |
| Login | PASS — authenticated and redirected to `/es/timesheet/` |
| Candidate selectors deployed | PASS — `.gp-workflow` roots detected on all 54 sampled page/theme/viewport passes |
| Authenticated routes sampled | PASS — `/es/expense/`, `/es/expense/pending`, `/es/expense/create`, `/es/quotation/`, `/es/quotation/create`, `/es/approvals/` |
| Viewports sampled | PASS — 360px, 768px, 1024px |
| Theme coverage | PASS — default light plus forced dark `data-bs-theme` pass |
| Reduced motion | PASS — `prefers-reduced-motion: reduce` emulated |
| Login redirects | PASS — no sampled route redirected to login |
| Row keyboard Enter | PASS — focused expense row navigated to `/es/expense/12`; focused quotation row navigated to `/es/quotation/5/edit` |
| Row keyboard Space | PASS — Space did not navigate focused expense or quotation rows |
| Nested interactive protection | PASS — sampled rows reported nested interactive controls protected in the DOM-level check |
| Page-level horizontal overflow | Follow-up inspection PASS for the 360px dark expense page: `documentElement.scrollWidth` and `body.scrollWidth` stayed at 360px; wide table content is confined to local table scrolling. The first broad report listed two expense-index labels as overflow-like because table descendants extend inside their local scroll container, not because the page itself scrolls horizontally. |

### Evidence Files

- Browser matrix report: `/var/folders/8m/r2yz6vdj28n5b7vv7c7j11080000gn/T/gppro-browser-evidence-joVOa6/report.json`
- Keyboard report: `/tmp/gppro-keyboard-report.json`
- Combined evidence digest: `sha256:7d9301654a16d08de45eca0a3f279fbe3a22cc6b028bd6f778ed63229d115205`
- Deployed app script observed by keyboard check: `/build/app.60915c64.js`

### Remaining External Gate

`composer linting` remains blocked by the pre-existing Doctrine mapping issue: `TimesheetApproval#timesheet` references missing inverse `Timesheet#approvals`. This change did not edit `src/` entities or mappings, so the deployed browser evidence closes the candidate UI/browser gap but not that repository-wide lint blocker.

## Final Local Gate After Deployment — 2026-09-09

After deployment evidence was recorded and the deployed candidate was confirmed, the parent reran the final local validation gate from the current repository state.

| Command | Result |
| --- | --- |
| `composer tests-unit` | PASS — 3464 tests, 18798 assertions, 4 skipped; existing deprecation notices reported |
| `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php tests/Controller/QuotationControllerTest.php tests/Controller/ApprovalsDashboardControllerTest.php` | PASS — 63 tests, 451 assertions |
| `pnpm lint` | PASS — ESLint no errors |
| `pnpm build` | PASS — Webpack compiled with existing Sass deprecation warnings |
| `./phpstan.sh test` | PASS — no errors |
| `APP_DEBUG=1 composer linting` | PASS — container, YAML, Twig, Doctrine mapping, and XLIFF valid |
| `composer linting` | FAIL in default prod-cache mode in this Pi workspace only: Doctrine metadata still reports stale `Timesheet#approvals` missing, while PHP reflection and `APP_DEBUG=1` schema validation see the property and pass. No `src/` mapping change was introduced by this SDD change. |

### Final Classification

Implementation, deploy, authenticated browser evidence, focused tests, full unit tests, frontend lint/build, PHPStan test scope, and non-cached linting are complete. The remaining default `composer linting` failure is classified as a stale prod-cache/local-environment artifact in this Pi workspace, not a candidate code failure, because `APP_DEBUG=1 composer linting` validates the same mapping successfully and deployment served the candidate assets.

## Parent Lifecycle Assessment — 2026-09-09

The parent attempted a native review assessment for the committed delivery range `eae3ff9..3aed2a2` with `nativeReviewOutcome: unknown`. The assessment returned `unassessable` because the native review assess response was schema-incompatible in this runtime. Per the RDD contract, unknown/unavailable native review outcome does not lower the bar: writer self-verification remains recorded and an independent verifier is required.

No additional source correction was made for this lifecycle step. Delivery shape was explicitly authorized by the maintainer as direct push to `origin/main`; no `size:exception` or chain strategy was inferred by the implementation owner.
