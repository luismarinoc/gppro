# Apply progress: gppro-invoice-visual-consistency

## Slice

- **Delivery:** auto-chain / stacked-to-main; PR 1 only (`invoice-slice-1-archive`).
- **Review boundary:** archive/listing/history only; application diff is 57 changed lines (under the 360-line safety margin and 400-line limit).
- **Structured status consumed:** OpenSpec-backed, apply-ready, repo-local action context rooted at `/Users/luismarinoc/Documents/Dev/tbema/gppro`, with that root as the allowed edit root. No action-context warnings.
- **Deferred lifecycle actions:** all five parent-owned deployment/review/browser/archive gates remain byte-for-byte unchecked in `tasks.md`; no lifecycle action was attempted.

## Completed implementation tasks

The following persisted task checkboxes were updated to `- [x]` in `tasks.md`:

1. PR 1 RED
2. PR 1 GREEN
3. PR 1 TRIANGULATE
4. PR 1 REFACTOR

## Files changed

- `assets/sass/_workflow.scss` — added archive-only rules under `.gp-workflow--invoice` for the existing invoice datatable surface and local horizontal scrollport.
- `templates/invoice/listing.html.twig` — added the page workflow root and history surface/table-scroll/table hooks without altering rows, macros, URLs, or data attributes.
- `tests/Controller/InvoiceControllerTest.php` — extended populated archive/history fixtures to protect the workflow hierarchy, archive search/footer, modal edit `data-href`, badges, status actions, view/download URLs, history totals, and partial-warning tooltip.
- `openspec/changes/gppro-invoice-visual-consistency/tasks.md` — recorded PR 1 task completion.
- `openspec/changes/gppro-invoice-visual-consistency/apply-progress.md` — this evidence.

## TDD Cycle Evidence

| Task | Test file | Layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- | --- | --- |
| PR 1 archive/listing/history | `tests/Controller/InvoiceControllerTest.php` | Symfony controller integration | 30 tests / 316 assertions passed | Tests were written before presentation changes. The first RED execution was blocked by a transient test-schema foreign-key cleanup failure; after recovery, controlled removal of the root produced 2 expected missing-root failures (30 tests, 299 assertions). | Focused suite passed: 30 tests / 327 assertions. | Added populated-history partial-tooltip and archive footer/status-action coverage; focused suite passed: 30 tests / 329 assertions. | Archive declarations remain solely under `.gp-workflow--invoice`; final focused suite passed: 30 tests / 329 assertions. |

## Verification

- `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` — passed, 30 tests / 329 assertions (final).
- `APP_DEBUG=1 composer linting` — passed.
- `./phpstan.sh test` — passed, no errors.
- `./php-cs-fixer.sh core` — completed with its pre-existing PHP 8.5-versus-8.2 warning and reported an unrelated whitespace finding in `tests/Controller/QuotationControllerTest.php`; that out-of-slice formatter change was reverted.
- `pnpm build` — completed with existing Sass deprecation warnings. Generated `public/build/` output was restored/removed and is clean.
- `git diff --check` — passed.

## Design conformance and deviations

No design deviation. The shared datatable macro was not changed: the page root scopes its existing `.datatable_invoices` structure, while the history card receives the additive shared hooks. No unscoped selector, new copy, JavaScript, data attribute, route, badge macro, action macro, filter, pagination, export, or modal contract was added or changed.

## Remaining tasks

The next exact unchecked implementation slice is PR 2; it must not start until parent lifecycle accepts and deploys PR 1:

- [ ] **RED:** In `tests/Controller/InvoiceControllerTest.php`, add assertions for the invoice workflow root, filter/form surface, preview sections, contextual existing-copy empty-state wrappers, local table containment, and wrapping actions; protect `#invoice-print-form`, `invoice_search_form_row_*`, `#invoice_customer_forms`, token nodes, customer form IDs, preview/save link `onclick`/`target`/`data-customer`/`data-href`, collapse hook, `invoice_create`, and `invoice_milestone`; run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` and record RED. <!-- sdd-owner: implementation -->
- [ ] **GREEN:** In `templates/invoice/index.html.twig` and the slice-local `.gp-workflow--invoice` Sass, structure the existing filter, milestone and timesheet preview cards, nested tables, totals, action groups, and existing empty output with shared workflow classes/local scroll wrappers; do not alter `singleInvoice()`, generated query URLs, form markup ownership, limits, warnings, or selector/data contracts; rerun `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [ ] **TRIANGULATE:** Exercise unsearched and empty searched states, populated timesheet previews, milestone mode with and without a customer, preview/create behavior, 100-entry limit, and unauthorized-customer IDOR in `InvoiceControllerTest`; verify protected hook/value assertions and run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [ ] **REFACTOR:** Deduplicate only invoice-scoped layout declarations, retain dense information and native keyboard order, then run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`; measure the PR and split before 360 changed lines rather than exceed 400. <!-- sdd-owner: implementation -->

PR 3–5 and the two cross-slice implementation gates remain unchecked in `tasks.md` and are outside this accepted PR 1 boundary.

## Slice 1 Post-Deploy Browser Evidence — 2026-09-09

After pushing Slice 1 to `origin/main`, the parent waited for Dokploy and ran authenticated read-only Chrome/CDP evidence against `https://gppro.tbema.net` using `.env.local` credentials without printing secrets.

| Route | Widths | Themes | Result |
| --- | --- | --- | --- |
| `/es/invoice/show` | 360px, 768px, 1024px | light, forced dark | PASS — exactly one `.gp-workflow.gp-workflow--invoice` root, `#invoice_history_summary_box` present, local `.gp-workflow__table-scroll` present, modal edit links `.modal-ajax-form.open-edit` preserved, and no document-level horizontal overflow detected. |

The route served the deployed Slice 1 archive/listing/history candidate. This evidence is read-only and did not invoke invoice save/status/payment actions.

## Slice 2 — Creation/preview workspace

- **Delivery:** auto-chain / stacked-to-main; PR 2 only (`invoice-slice-2-creation-preview`). Parent supplied settlement authority token `sha256:400b28fde503528bed83d069fc6b25656c6b50e793d57e369cb8168269f2177d`; no attempt lifecycle operation was performed here.
- **Review boundary:** creation/preview workspace only. Application diff: **147 changed lines** (119 additions, 28 deletions), below the 360-line safety margin and 400-line hard limit.
- **Structured status consumed:** the native OpenSpec status was stale and reported ambiguous selection, but the parent explicitly selected `gppro-invoice-visual-consistency`, Slice 2, with repo-local action context rooted at `/Users/luismarinoc/Documents/Dev/tbema/gppro`. All edits remained inside the parent-provided allowed surfaces. No action-context warnings.
- **Evidence revision hash (application patch):** `sha256:43434b2ad910a4f4fa9886c03bb9acb7d07f4024bddc6173b72501f5cfa3f05b`.

### Completed implementation tasks

The following persisted task checkboxes were updated to `- [x]` in `tasks.md`:

1. PR 2 RED
2. PR 2 GREEN
3. PR 2 TRIANGULATE
4. PR 2 REFACTOR

### Files changed

- `templates/invoice/index.html.twig` — added the invoice workflow root, filter surface/form class, preview sections, contextual existing-copy empty wrappers, local scrollports, and wrapping action group while retaining all existing form, token, URL, table, warning, and JavaScript contracts.
- `assets/sass/_workflow.scss` — added only invoice-scoped sizing, local-scroll, and narrow action-group layout rules; also normalized the accepted Slice 1 invoice declaration indentation without changing selectors or values.
- `tests/Controller/InvoiceControllerTest.php` — added DOM-contract coverage for filter/form rows, empty states, timesheet and milestone previews, table containment, action attributes, token nodes, customer field IDs, collapse hook, and invoice datatable contracts.
- `openspec/changes/gppro-invoice-visual-consistency/tasks.md` — marked PR 2 implementation tasks complete.
- `openspec/changes/gppro-invoice-visual-consistency/apply-progress.md` — this cumulative Slice 2 evidence.

### TDD Cycle Evidence

| Task | Test file | Layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| --- | --- | --- | --- | --- | --- | --- | --- |
| PR 2 creation/preview workspace | `tests/Controller/InvoiceControllerTest.php` | Symfony controller integration | 30 tests / 329 assertions passed | Added DOM assertions before Twig/Sass changes. Initial runner recovery required recreating the disposable test database after a schema foreign-key cleanup failure; the executed RED then failed in 4 expected missing-workflow-contract assertions (30 tests / 304 assertions). | After the minimum wrappers and scoped hooks, focused suite passed (30 tests / 351 assertions). | Final focused suite passed (30 tests / 358 assertions) across empty search, populated timesheet preview, milestone with/without customer, preview/create, and unauthorized-customer IDOR paths. The existing `limit_preview`, `slice(0, 100)`, and warning branch were retained byte-for-byte; a discarded exploratory 501-fixture test reached the controller's 101-row query cap before that presentation threshold, so no misleading threshold test was kept. | Consolidated only invoice-scoped Sass declarations; final focused suite and the broader unit suite passed. |

### Verification

- `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` — passed, 30 tests / 358 assertions.
- `composer tests-unit` — passed, 3,464 tests / 18,798 assertions; 4 skipped; existing deprecation notices reported.
- `APP_DEBUG=1 composer linting` — passed (container, YAML, Twig, Doctrine mapping, and XLIFF).
- `./phpstan.sh test` — passed, no errors.
- `./php-cs-fixer.sh core` — completed with the project PHP 8.5-versus-8.2 warning; it touched an unrelated quotation test, which was immediately restored. Its invoice-test indentation output was manually reconciled to the pre-existing local style.
- `pnpm build` — passed with pre-existing dependency Sass deprecation warnings. Generated `public/build/` changes were restored/removed; no generated output remains.
- `git diff --check` — passed.

### Design conformance and risks

No design deviation. `singleInvoice()`, generated query URL construction, form ownership, form IDs/names, `invoice_search_form_row_*`, customer field IDs, token nodes, preview/save `onclick`/`target`/`data-customer`/`data-href` attributes, collapse hook, `invoice_create`, `invoice_milestone`, 100-entry slice/warning logic, totals, and permission behavior were retained. No JavaScript, copy, translation, route, controller, or shared macro changed.

Browser/deployment evidence is intentionally deferred to the parent lifecycle gate. Build warnings are upstream Sass deprecations, not Slice 2 failures.

### Remaining tasks

The next exact unchecked implementation boundary is PR 3; it must not start until the parent accepts, merges, deploys, and records Slice 2 evidence:

- [ ] **RED:** In `tests/Controller/InvoiceControllerTest.php`, add direct and modal rendering assertions for the invoice root and absent/present action surface: exactly one submit POST form for eligible unsubmitted/unpaid invoices, approve/reject POST forms only while pending, exact route suffixes and `_token` fields, and no panel/forms/prohibited language for historical PAID invoices; run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` and record RED. <!-- sdd-owner: implementation -->
- [ ] **GREEN:** In `templates/invoice/invoice_edit.html.twig` and invoice-scoped Sass, wrap the unchanged standard/modal form embed and only currently rendered submit/approve/reject forms in a compact shared action surface; retain permission/state guards, form classes/methods/routes/token IDs, button translations, redirects, modal IDs, and no paid-history output; rerun `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [ ] **TRIANGULATE:** Run the focused suite through submit, eligible/ineligible approval, reject, PAID gate, PENDING/CANCELED, historical-paid resave, and direct/modal DOM scenarios; verify the panel remains absent if no current payment action renders. <!-- sdd-owner: implementation -->
- [ ] **REFACTOR:** Restrict all responsive/action/focus rules to `.gp-workflow--invoice`, remove duplicated styling without changing action markup semantics, then run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`; keep the diff below 400 changed lines. <!-- sdd-owner: implementation -->

PR 4–5, the two cross-slice implementation gates, and all parent-owned review/deployment/browser/archive actions remain unchecked and are outside this Slice 2 boundary.
