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
