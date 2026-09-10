# Implementation Tasks: GPPro invoice visual consistency

## Review Workload Forecast

| Field | Value |
| ------- | ------- |
| Estimated changed lines | 930–1,420 across five vertical slices; each slice is forecast at 100–380 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 Archive/listing/history → PR 2 Creation/preview workspace → PR 3 Edit payment action panel → PR 4 Payment approval level administration → PR 5 Milestone invoicing |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

## Delivery Boundaries

Deliver serial direct-to-main/Dokploy slices: each PR targets `main`, is merged and deployed before starting its successor, and stays below 400 additions plus deletions (start splitting at the 360-line safety margin). No feature-branch tracker and no `size:exception` are permitted.

| Slice | Allowed edit surfaces | Start / finish / rollback boundary |
| --- | --- | --- |
| 1. Archive/listing/history | `assets/sass/_workflow.scss`; `templates/invoice/listing.html.twig`; `tests/Controller/InvoiceControllerTest.php` | Start with the existing invoice archive; finish with scoped archive/history hierarchy and preserved DOM contracts; revert only this Sass subsection, Twig hooks, and assertions. |
| 2. Creation/preview workspace | `assets/sass/_workflow.scss`; `templates/invoice/index.html.twig`; `tests/Controller/InvoiceControllerTest.php` | Start after slice 1 is accepted; finish with filter, preview, table, action, and empty-state containment; revert its Sass subsection, wrappers, and assertions. |
| 3. Edit payment action panel | `assets/sass/_workflow.scss`; `templates/invoice/invoice_edit.html.twig`; `tests/Controller/InvoiceControllerTest.php` | Start after slice 2; finish with direct/modal-compatible existing payment actions only; revert its wrapper, Sass subsection, and assertions. |
| 4. Payment approval levels | `assets/sass/_workflow.scss`; `templates/invoice_payment_approval_level/index.html.twig`; `templates/invoice_payment_approval_level/edit.html.twig`; `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` | Start after slice 3; finish with scoped list/form hierarchy and protected DOM unchanged; revert only this slice's hooks, Sass, and assertions. |
| 5. Milestone invoicing | `assets/sass/_workflow.scss`; `templates/milestone-invoice/customers.html.twig`; `templates/milestone-invoice/index.html.twig`; `tests/Controller/MilestoneInvoiceControllerTest.php` | Start after slice 4; finish with chooser/selection hierarchy and existing batch contracts; revert only this slice's hooks, Sass, and assertions. |

Do not edit `src/`, `config/`, migrations, form classes, shared datatable/form macros, `variables.scss`, `_gppro.scss`, JavaScript, translations, renderers, generated assets, plugins, or runtime data. Preserve all current routes, authorization, HTTP methods, token names/IDs, fields, data attributes, JavaScript hooks, calculations, persistence, status/approval policy, output, and translations. Do not add approval progress/status language, historical-paid messaging, an archive payment-pending indicator, or any new user-facing copy.

## Implementation work

### PR 1 — Archive/listing/history

- [x] **RED:** In `tests/Controller/InvoiceControllerTest.php`, extend the existing archive/history fixtures to assert exactly one `.gp-workflow.gp-workflow--invoice` root, scoped archive/history surface and local-scroll hooks, and preservation of `#invoice_history_summary_box`, total-row classes, partial-warning tooltip, `.modal-ajax-form.open-edit`, `data-href`, lifecycle badges, URLs, and summary values; run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` and record the expected missing-workflow-contract failure. <!-- sdd-owner: implementation -->
- [x] **GREEN:** In `templates/invoice/listing.html.twig` and an invoice-scoped subsection of `assets/sass/_workflow.scss`, add only the workflow root/page class and shared surface/table-scroll/table hooks needed for the archive datatable and history card; retain every datatable column, action macro, filter/pagination/export/modal contract, badge macro, and history value; rerun `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE:** Use the archive tests to cover populated history plus archive search/pagination, view/download/status actions, and modal edit DOM; confirm the updated assertions reject a missing root or altered preserved selector while `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` passes. <!-- sdd-owner: implementation -->
- [x] **REFACTOR:** Consolidate only repeated archive declarations beneath `.gp-workflow--invoice`, verify no unscoped legacy-component selector was added, run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`, then confirm the PR diff is under 400 changed lines. <!-- sdd-owner: implementation -->

### PR 2 — Creation/preview workspace

- [x] **RED:** In `tests/Controller/InvoiceControllerTest.php`, add assertions for the invoice workflow root, filter/form surface, preview sections, contextual existing-copy empty-state wrappers, local table containment, and wrapping actions; protect `#invoice-print-form`, `invoice_search_form_row_*`, `#invoice_customer_forms`, token nodes, customer form IDs, preview/save link `onclick`/`target`/`data-customer`/`data-href`, collapse hook, `invoice_create`, and `invoice_milestone`; run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` and record RED. <!-- sdd-owner: implementation -->
- [x] **GREEN:** In `templates/invoice/index.html.twig` and the slice-local `.gp-workflow--invoice` Sass, structure the existing filter, milestone and timesheet preview cards, nested tables, totals, action groups, and existing empty output with shared workflow classes/local scroll wrappers; do not alter `singleInvoice()`, generated query URLs, form markup ownership, limits, warnings, or selector/data contracts; rerun `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE:** Exercise unsearched and empty searched states, populated timesheet previews, milestone mode with and without a customer, preview/create behavior, 100-entry limit, and unauthorized-customer IDOR in `InvoiceControllerTest`; verify protected hook/value assertions and run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [x] **REFACTOR:** Deduplicate only invoice-scoped layout declarations, retain dense information and native keyboard order, then run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`; measure the PR and split before 360 changed lines rather than exceed 400. <!-- sdd-owner: implementation -->

### PR 3 — Edit payment action panel

- [x] **RED:** In `tests/Controller/InvoiceControllerTest.php`, add direct and modal rendering assertions for the invoice root and absent/present action surface: exactly one submit POST form for eligible unsubmitted/unpaid invoices, approve/reject POST forms only while pending, exact route suffixes and `_token` fields, and no panel/forms/prohibited language for historical PAID invoices; run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php` and record RED. <!-- sdd-owner: implementation -->
- [x] **GREEN:** In `templates/invoice/invoice_edit.html.twig` and invoice-scoped Sass, wrap the unchanged standard/modal form embed and only currently rendered submit/approve/reject forms in a compact shared action surface; retain permission/state guards, form classes/methods/routes/token IDs, button translations, redirects, modal IDs, and no paid-history output; rerun `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE:** Run the focused suite through submit, eligible/ineligible approval, reject, PAID gate, PENDING/CANCELED, historical-paid resave, and direct/modal DOM scenarios; verify the panel remains absent if no current payment action renders. <!-- sdd-owner: implementation -->
- [x] **REFACTOR:** Restrict all responsive/action/focus rules to `.gp-workflow--invoice`, remove duplicated styling without changing action markup semantics, then run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`; keep the diff below 400 changed lines. <!-- sdd-owner: implementation -->

### PR 4 — Payment approval level administration

- [x] **RED:** In `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`, add DOM assertions for invoice workflow roots, list/form surfaces, header/action grouping, local table-scroll hook, and structured empty state using the existing translation; protect create/edit targets, `alternative-link` rows, level-one delete absence, non-base POST delete action, `_token`, CSRF ID, Symfony fields/errors, save, and cancel; run `vendor/bin/phpunit tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` and record RED. <!-- sdd-owner: implementation -->
- [x] **GREEN:** In `templates/invoice_payment_approval_level/index.html.twig`, `templates/invoice_payment_approval_level/edit.html.twig`, and invoice-scoped Sass, add only shared workflow root/surface/header/actions/form/table/empty-state structure; retain ordered raw thresholds, role/user labels, em-dash fallback, authorization boundaries, methods, delete rule, form rendering, validation, flash, and redirect behavior; rerun `vendor/bin/phpunit tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`. <!-- sdd-owner: implementation -->
- [x] **TRIANGULATE:** Exercise empty and populated list DOM, super-admin versus admin access, named approver, create/edit validation including non-monotonic threshold rejection, level-one deletion denial, and non-base deletion using the existing suite; run `vendor/bin/phpunit tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`. <!-- sdd-owner: implementation -->
- [x] **REFACTOR:** Keep all table/action/form rules under `.gp-workflow--invoice`, preserve native controls and focus order, then run `vendor/bin/phpunit tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`; verify the PR remains below 400 changed lines. <!-- sdd-owner: implementation -->

### PR 5 — Milestone invoicing

- [ ] **RED:** In `tests/Controller/MilestoneInvoiceControllerTest.php`, add chooser and customer-list DOM assertions for the invoice roots, surfaces, table-scroll hooks, existing-copy empty states, existing generate-translation accessible name, customer context, warning selector/tooltip, disabled checkbox, and batch contracts (`DataTable('milestone_invoice')`, reload event, `multi_update_table`, entity CSV, template field, and CSRF); run `vendor/bin/phpunit tests/Controller/MilestoneInvoiceControllerTest.php` and record RED. <!-- sdd-owner: implementation -->
- [ ] **GREEN:** In `templates/milestone-invoice/customers.html.twig`, `templates/milestone-invoice/index.html.twig`, and the invoice Sass scope, add workflow hierarchy, local table containment, batch-action/warning/disabled-control hooks, and the existing translated accessible name; retain customer routes/access, datatable configuration, generated form ownership, reload event, warning/checkbox selectors, and all generation contracts; rerun `vendor/bin/phpunit tests/Controller/MilestoneInvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [ ] **TRIANGULATE:** Exercise eligible and empty customer chooser, selected-customer table, no-hours warning/disabled selection, customer filtering and IDOR, invoiceable/convertible filtering, happy-path generation, mixed customer, stale selection, invalid ID, and foreign-customer rejection; run `vendor/bin/phpunit tests/Controller/MilestoneInvoiceControllerTest.php`. <!-- sdd-owner: implementation -->
- [ ] **REFACTOR:** Consolidate only `.gp-workflow--invoice` milestone rules, retain native disabled semantics and focus behavior, then run `vendor/bin/phpunit tests/Controller/MilestoneInvoiceControllerTest.php`, `composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, and `pnpm build`; ensure the slice stays under 400 changed lines. <!-- sdd-owner: implementation -->

### Cross-slice implementation verification

- [ ] Run `vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php`, `vendor/bin/phpunit tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`, `vendor/bin/phpunit tests/Controller/MilestoneInvoiceControllerTest.php`, `./phpstan.sh test`, `composer linting`, `./php-cs-fixer.sh core`, and `pnpm build`; confirm generated output is not staged and no disallowed edit surface changed. <!-- sdd-owner: implementation -->
- [ ] Run `composer tests-unit` as the final non-destructive regression gate; inspect each PR diff and the complete chain for 400-line compliance, invoice-scoped selectors only, unchanged JS/data/form/CSRF contracts, and absence of prohibited payment-pending/progress/historical-paid language. <!-- sdd-owner: implementation -->

## Parent lifecycle and evidence gates

- [ ] For each accepted direct-to-main slice, perform bounded review of its vertical outcome, tests, allowed surfaces, rollback boundary, and sub-400-line diff before merge; do not begin the next slice until the current slice is merged to `main` and Dokploy deployment is confirmed. <!-- sdd-owner: parent -->
- [ ] Before candidate comparison, record the `origin/main` revision and whether `https://gppro.tbema.net` serves it; with explicit authorization only, use authenticated read-only browser access and retain screenshots/reports outside the repository with paths and SHA-256 digests recorded in apply/verify artifacts. <!-- sdd-owner: parent -->
- [ ] After each confirmed Dokploy deployment, capture available target states at 360px, 768px, and 1024px-or-wider in light and dark themes with reduced motion: no document/body page overflow; table overflow confined to local scrollports; readable/wrapping actions; distinguishable tokens, badges, warnings, disabled controls, and visible keyboard focus; unchanged native modal/collapse/link behavior. Treat unavailable data states as not observed and revision uncertainty as blocked/stale, never as a pass. <!-- sdd-owner: parent -->
- [ ] Complete the route-specific read-only evidence matrix: archive/history and modal edit; creation initial/empty/timesheet/milestone states without invoking save; direct and modal edit for unsubmitted, pending, and historical-paid invoices; approval-level list/create/edit GET pages; and milestone chooser/customer list. Confirm historical-paid invoices have no payment panel/language and representative expense, quotation, and approvals workflows remain unchanged. <!-- sdd-owner: parent -->
- [ ] Archive the OpenSpec change only after all five merged/deployed slices, command receipts, review evidence, browser report paths/digests, deployment revisions, and any unavailable/blocked states are recorded; preserve the proposal, specs, design, tasks, apply evidence, and verification report without storing credentials, cookies, or tokens. <!-- sdd-owner: parent -->
