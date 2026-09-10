# Design: GPPro invoice visual consistency

## Design summary

Apply the existing GPPro workflow presentation system to five authenticated invoice operations without changing application behavior. Every target renders beneath `.gp-workflow.gp-workflow--invoice`, reuses the existing `--gp-*` tokens and shared workflow classes, and adds only invoice-scoped Sass in `assets/sass/_workflow.scss`.

The implementation is presentation-only. It changes Twig structure, scoped Sass, and additive controller-test assertions. It does not change controllers, services, entities, forms, routes, permissions, CSRF identifiers, JavaScript, persistence, calculations, approval policy, invoice output, or dependencies.

The five outcomes are independent vertical slices, each forecast below 400 changed lines. They are technically chain-compatible, but the active delivery policy is `ask-on-risk`: because the combined change exceeds 400 lines, implementation must pause for a human delivery-shape decision before application edits begin. No chain strategy or `size:exception` is selected by this design.

## Inputs and constraints

This design follows:

- `openspec/changes/gppro-invoice-visual-consistency/proposal.md`
- `openspec/changes/gppro-invoice-visual-consistency/exploration.md`
- `openspec/specs/gppro-visual-system/spec.md`
- `openspec/specs/finance-workflow-visual-states/spec.md`
- `openspec/specs/invoice-payment-approval/spec.md`
- the established implementation in `assets/sass/_workflow.scss`
- current invoice, payment-approval-level, and milestone-invoice Twig/controller-test seams

CodeGraph was not exposed to this executor, so targeted repository reads and searches were used as the documented fallback.

## Goals

1. Give archive, creation, edit actions, approval-level administration, and milestone invoicing one restrained invoice workflow hierarchy.
2. Keep dense tables locally scrollable while preventing page-level horizontal overflow at 360px, 768px, and 1024px or wider.
3. Preserve distinguishable surfaces, controls, native disabled states, existing status semantics, and visible focus in light and dark themes.
4. Preserve every current invoice, payment-approval, and milestone behavior contract.
5. Make each implementation slice independently testable, reviewable, deployable, and reversible below 400 changed lines.

## Non-goals

- No controller, service, repository, voter, policy, entity, form class, route, migration, schema, persistence, audit, or authorization change.
- No invoice calculation, total, currency, payment date, lifecycle transition, approval eligibility, frozen-level, or milestone-generation change.
- No payment-approval progress display, new approval status summary, historical-paid warning, or archive payment-pending indicator.
- No invoice-template administration, renderer-document management, PDF, printable, public, customer-facing, or downloaded-document changes.
- No JavaScript changes; `singleInvoice()` and datatable/reload plugins remain unchanged.
- No new dependency, visual token, Sass entry point, frontend framework, browser-test package, or generated `public/build/` edit.
- No broad rewrite of shared datatable macros, form embeds, Bootstrap, Tabler, or unrelated workflow consumers.
- No net-new user-facing copy is planned. If implementation proves new visible or assistive text unavoidable, the slice must stop for a scope update and add matching English and Spanish translations together.

## Architecture decisions

| ID | Decision | Choice | Tradeoff and rationale |
| --- | --- | --- | --- |
| D1 | Presentation boundary | Put every target under `.gp-workflow.gp-workflow--invoice`; put domain rules under `.gp-workflow--invoice` in `_workflow.scss`. | Some selectors must adapt legacy card/datatable markup, but invoice scoping prevents repository-wide regressions. |
| D2 | Token ownership | Use existing semantic `--gp-*` tokens only; do not edit `variables.scss`. | Existing tokens may limit decorative freedom, which is preferable to introducing a second theme contract. |
| D3 | Existing component vocabulary | Reuse `.gp-workflow__surface`, `__section`, `__header`, `__actions`, `__table-scroll`, `__table`, `__form`, `__status`, and `gp-workflow-state`. | A few invoice-specific layout classes remain necessary, but shared semantics stay recognizable. |
| D4 | Datatable integration | Add the workflow root in target templates and style existing target-specific datatable wrappers below it; do not modify `templates/datatable.html.twig`, shared datatable macros, or controllers. | This avoids blast radius at the cost of narrowly adapting `.datatable_invoices` and `.datatable_milestone_invoice`. |
| D5 | Responsive tables | Preserve all columns and existing responsive visibility classes. Constrain wide content with target-local horizontal scrolling and `min-width: 0`; never hide additional financial context solely for this redesign. | Users may scroll dense tables horizontally, but the page and action controls remain usable. |
| D6 | Status authority | Keep current invoice lifecycle badge macros and milestone warning badges unchanged in meaning and text. Styling may improve containment, not reinterpret state. | Visual uniformity is secondary to avoiding false payment-approval semantics. |
| D7 | Edit payment panel | Wrap only the currently rendered submit/approve/reject forms in an invoice action surface compatible with direct and modal responses. | No explanatory progress text is added, so the panel remains intentionally compact and behaviorally exact. |
| D8 | Empty states | Wrap existing translated empty copy in the shared contextual state structure where the target template controls emptiness. Do not invent text or suppress server flash/form errors. | Datatable-owned empty rendering may retain the existing widget while receiving root-scoped presentation. |
| D9 | Tests | Assert emitted workflow and protected DOM contracts through existing PHPUnit controller suites; use build plus browser evidence for computed CSS and runtime layout. | The repository has no browser runner, and adding one is outside scope. |
| D10 | Delivery | Implement serially in five vertical slices; measure additions plus deletions for each review unit and stop before 400 lines. | Shared `_workflow.scss` is touched incrementally, but each slice remains reversible with its matching Twig and tests. |

## Presentation contract

### Root and shared classes

Each target must expose one containing root with both classes:

```html
<div class="gp-workflow gp-workflow--invoice">…</div>
```

For templates extending `datatable.html.twig`, the `page_class` block may place these classes on the existing `<section class="content">`. For ordinary templates, an explicit wrapper owns the root. The invoice edit template may add `gp-workflow--invoice-edit` or an equivalent invoice-scoped modifier to distinguish modal-compatible rules, but the Bootstrap modal element, IDs, and form ownership remain unchanged.

Within the root:

- cards/surfaces use `.gp-workflow__surface`;
- domain groupings use `.gp-workflow__section`;
- native action groups use `.gp-workflow__actions`;
- local overflow wrappers use `.gp-workflow__table-scroll` and tables use `.gp-workflow__table` where markup ownership permits;
- Symfony forms use `.gp-workflow__form` without changing generated names or fields;
- contextual empty blocks use `.gp-workflow-state.gp-workflow-state--empty`;
- existing warning, danger, positive, and informational meaning resolves through existing Bootstrap badges or `gp-workflow__status` modifiers.

Invoice-specific selectors may define layout hooks for archive history, creation previews, payment actions, approval levels, and milestones. They must remain descendants of `.gp-workflow--invoice`; no unscoped `.card`, `.table`, `.badge`, `.btn`, `.form-control`, or datatable override is allowed.

### Theme, focus, disabled, and motion rules

- Colors, borders, shadows, surfaces, form focus, and disabled controls resolve through existing `--gp-*` roles.
- No new light/dark component forks are introduced; existing token overrides provide theme parity.
- Native focusability and keyboard order remain unchanged. Existing anchors, buttons, inputs, checkboxes, and forms retain visible `:focus-visible` treatment.
- Disabled milestone checkboxes remain disabled and use existing disabled-control tokens with sufficient visual distinction; styling must not simulate an enabled control.
- No animation is added. Existing reduced-motion behavior therefore remains authoritative.

## Surface designs and preserved contracts

### 1. Archive/listing/history visual consistency

`templates/invoice/listing.html.twig` adds the invoice workflow root to the existing datatable page. The generated archive card/table is visually adapted only below `.gp-workflow--invoice .datatable_invoices`. The existing history card receives shared surface/table classes and remains identified by `#invoice_history_summary_box`.

The archive table keeps:

- `.modal-ajax-form.open-edit`, its `data-href`, and modal edit behavior;
- `DataTable('invoices')`, search/filter/pagination/configuration markup, reload behavior, and export flow;
- `invoice/actions.html.twig` output and every status-change/download/view/delete URL;
- all column visibility classes and dynamic meta columns;
- invoice lifecycle, due-date, payment-date, and invoice-type macros and badge meanings;
- milestone names, timesheet durations, project names, and action containment;
- history grouping, row classes, amounts, currencies, partial-total tooltip, counts, and durations.

No row-link keyboard contract is added, and no payment-approval indicator appears in the archive.

### 2. Creation/preview workspace

`templates/invoice/index.html.twig` wraps the filter and result regions as one invoice workflow. The filter card is a shared surface/form; preview cards are sections; customer summary and nested-entry tables receive local overflow containers; existing action controls wrap at narrow widths.

The workspace keeps exactly:

- form ID `invoice-print-form`, its fields, names, method, validation, and hidden template/date rows;
- `invoice_search_form_row_*` classes;
- `#invoice_customer_forms`, `#create-token`, `#preview-token`, and token values;
- each customer form name/ID and all generated fields;
- preview/save links, `onclick`, `target`, `data-customer`, and `data-href` values;
- the complete `singleInvoice()` implementation and URL/query construction;
- customer/project/activity permission guards and destinations;
- collapse behavior, nested `invoice_create` table, 100-entry preview limit, skipped-row warning, totals, currencies, and durations;
- milestone mode, customer scoping, `invoice_milestone` datatable, `milestone_form`, and selected IDs;
- existing translated empty-state output for no customer, no milestones, or no invoice entries.

The design does not alter preview/downloaded invoice rendering and does not trigger save from presentation code.

### 3. Edit payment action panel

`templates/invoice/invoice_edit.html.twig` adds an invoice-scoped wrapper around the existing standard/modal form embed and a compact action surface beneath it. The action surface renders only when one or more current payment forms render.

It preserves:

- the standard `default/_form.html.twig` and modal `default/_form_modal.html.twig` selection;
- invoice edit form name, fields, action, save/reset/back controls, errors, modal IDs, and modal save behavior;
- `is_granted('edit_invoice', invoice)` and every current entity-state guard;
- POST methods and routes `invoice_submit_payment_approval`, `invoice_approve_payment`, and `invoice_reject_payment`;
- `_token` field names and per-invoice token IDs;
- existing translated button text and redirect behavior;
- exactly one submit form for an eligible unsubmitted/unpaid invoice;
- approve and reject forms only for the current pending state;
- no payment-action forms, panel, warning, or retroactive approval language for historical paid invoices.

The panel does not claim approval progress, eligibility, completion, or state beyond the actions the server already exposes.

### 4. Payment approval level administration

`templates/invoice_payment_approval_level/index.html.twig` and `edit.html.twig` become invoice workflow surfaces. The list gets a wrapping action header, local table scrolling, and a contextual block using the existing `invoice_payment_approval_level.none_found` translation. The form gets shared form/action hierarchy without replacing Symfony form rendering.

It preserves:

- controller-level full-authentication and `manage_invoice_payment_approval_levels` checks;
- list/create/edit/delete routes and methods;
- ordered values, raw rendered threshold values, role labels, named approver labels, and the em dash fallback;
- existing click-through `alternative-link` rows and direct edit anchors without adding a global keyboard behavior;
- level-one delete omission;
- non-base-level POST delete forms, `_token`, and CSRF ID `invoice_payment_approval_level`;
- Symfony field names, form token, validation errors, monotonic-threshold rejection, save, cancel, flash, and redirect behavior.

### 5. Milestone invoicing

`templates/milestone-invoice/customers.html.twig` and `index.html.twig` use the invoice workflow root. The customer chooser gains shared header/surface/table/state treatment. Its existing invoice icon link receives a descriptive accessible name from the existing `milestone_invoice.action.generate` translation without changing the destination.

The customer-scoped datatable keeps its generated form and behavior while adding invoice-scoped customer context, local table containment, warning emphasis, action hierarchy, and disabled-control contrast.

It preserves:

- `create_invoice`, `view_invoice`, and customer `access` enforcement;
- customer filtering and selected customer route parameter;
- `DataTable('milestone_invoice')`, all columns/options, batch form, and `gppro.invoiceUpdate` reload event;
- `multi_update_table`, entity CSV, template field, generated CSRF field, and create action;
- `.milestone_no_hours_warning`, tooltip text, `.multi_update_single`, `.multi_update_all`, and disabled checkbox behavior;
- invoiceable/convertible filtering, no-hours handling, stale-selection checks, mixed-customer rejection, route-customer matching, and server revalidation;
- existing empty-state and warning translations;
- generation outcomes and redirect/flash behavior.

## Data flow and behavioral boundary

```text
unchanged authorized controller response
  -> unchanged Twig variables, forms, URLs, permission guards, and tokens
  -> additive invoice workflow wrappers/classes
  -> existing Bootstrap/Tabler components + existing --gp-* semantics
  -> unchanged native controls, inline invoice script, datatable plugins, and reload events
  -> unchanged controller/service validation, calculations, persistence, and output
```

No new client state is submitted. CSS classes and wrappers carry presentation only. Server form errors, flash messages, status transitions, approval policy, and persisted results remain authoritative.

## Allowed edit surfaces

Only these application/test files are allowed:

| File | Allowed responsibility |
| --- | --- |
| `assets/sass/_workflow.scss` | Add invoice-scoped layout, surface, table containment, actions, warning, disabled, and responsive rules below `.gp-workflow--invoice`. |
| `templates/invoice/listing.html.twig` | Archive root, local table/surface hooks, and history presentation hooks. |
| `templates/invoice/index.html.twig` | Creation/filter/preview hierarchy, table wrappers, action groups, and empty-state wrappers. |
| `templates/invoice/invoice_edit.html.twig` | Standard/modal-compatible root and existing payment-action grouping. |
| `templates/invoice_payment_approval_level/index.html.twig` | List surface, actions, table wrapper, and existing-copy empty state. |
| `templates/invoice_payment_approval_level/edit.html.twig` | Form surface and action hierarchy. |
| `templates/milestone-invoice/customers.html.twig` | Chooser surface/table/empty state and accessible naming with existing copy. |
| `templates/milestone-invoice/index.html.twig` | Customer context and target-specific datatable/warning/disabled hooks. |
| `tests/Controller/InvoiceControllerTest.php` | Add archive, creation, edit-panel, modal, and protected-DOM assertions while retaining all behavior tests. |
| `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` | Add list/form/empty/protected-delete DOM assertions. |
| `tests/Controller/MilestoneInvoiceControllerTest.php` | Add chooser/selection/workflow/warning/batch DOM assertions. |

`openspec/changes/gppro-invoice-visual-consistency/` remains the documentation/evidence surface.

Explicitly disallowed without a revised design and human approval: all `src/`, `config/`, migrations, form classes, shared datatable/form macros, `variables.scss`, `_gppro.scss`, JavaScript, translation catalogs, invoice renderers, `public/build/`, `vendor/`, plugins, and runtime data.

## Vertical delivery slices

Each estimate counts additions plus deletions in the slice's review diff. OpenSpec planning artifacts are not used to hide application size. A 40-line safety margin is reserved: if implementation forecasts more than 360 changed lines, split before continuing; 400 lines is a hard stop without a new human decision.

| Slice | Files | Forecast | Independent acceptance boundary |
| --- | --- | ---: | --- |
| 1. Archive/listing/history | `_workflow.scss`, `invoice/listing.html.twig`, `InvoiceControllerTest.php` | 190–290 | Archive and history are visually scoped; datatable/modal/actions/status/filter/pagination/export/history values remain unchanged. |
| 2. Creation/preview workspace | `_workflow.scss`, `invoice/index.html.twig`, `InvoiceControllerTest.php` | 280–380 | Filter, timesheet preview, milestone mode, nested tables, totals, states, and actions are contained; every form/token/URL/query/data/JS hook remains unchanged. |
| 3. Edit payment action panel | `_workflow.scss`, `invoice/invoice_edit.html.twig`, `InvoiceControllerTest.php` | 100–180 | Standard and modal rendering show only current forms; methods/routes/tokens/guards remain exact; historical paid remains action- and message-free. |
| 4. Approval level administration | `_workflow.scss`, both approval-level Twig files, `InvoicePaymentApprovalLevelControllerTest.php` | 170–270 | List/empty/form/actions are consistent; authorization, monotonic validation, level-one rule, delete POST, and CSRF remain exact. |
| 5. Milestone invoicing | `_workflow.scss`, both milestone-invoice Twig files, `MilestoneInvoiceControllerTest.php` | 190–300 | Chooser and selection workflow are consistent; customer scope, datatable, events, selectors, disabled state, revalidation, and generation remain exact. |

Dependency order is 1 → 2 → 3 → 4 → 5 so each slice extends the same invoice Sass namespace without speculative primitives. A later slice may reuse accepted invoice styles but must not require unmerged later markup. Each slice can be reverted by removing its markup hooks, Sass subsection, and additive tests.

Because all five slices together exceed the 400-line review budget, the transition to implementation must invoke the active `ask-on-risk` gate. The human must choose the delivery shape; this design does not infer auto-chain, single PR, or an exception.

## Strict TDD and automated test strategy

OpenSpec has `strict_tdd: true`. For every slice:

1. **RED:** add focused authenticated DOM assertions before template/style changes and record the assertion failure caused by the missing workflow contract.
2. **GREEN:** add the minimum Twig hooks and invoice-scoped Sass, then pass the focused controller file.
3. **TRIANGULATE:** exercise populated/empty and authorized/unauthorized variants plus the existing behavior tests named below.
4. **REFACTOR:** consolidate only repeated invoice declarations under `.gp-workflow--invoice`; rerun the focused suite and frontend build.

### Slice-specific assertions

**Archive**

- Assert one invoice workflow root and target-local archive/history surface hooks.
- Assert `#invoice_history_summary_box`, milestone/timesheet total row classes, grouped values, and partial warning remain present.
- Assert invoice rows retain `.modal-ajax-form.open-edit`, edit `data-href`, action output, lifecycle badges, and existing detail values.
- Retain archive search, pagination, download/view/status, project, duration, and summary calculation tests.

**Creation workspace**

- Assert the workflow root, filter surface, preview section, and local table wrappers for timesheet and milestone modes.
- Assert `#invoice-print-form`, `#invoice_customer_forms`, token nodes, customer form IDs, `data-customer`, `data-href`, preview/save links, and collapse hook remain exact.
- Triangulate unsearched, empty searched, populated timesheet, milestone with customer, milestone without customer, and unauthorized-customer IDOR cases.
- Retain create, preview, limit, query, token, and generated invoice behavior tests.

**Edit payment panel**

- Assert direct rendering and modal rendering both contain the invoice root without changing their base/modal structures.
- For unsubmitted/unpaid, assert one POST submit form with the exact route suffix, `_token`, and translated button.
- For pending, assert one POST approve and one POST reject form with exact route suffixes and tokens.
- For historical paid, assert no panel, action forms, or prohibited status/progress language.
- Retain submit/approve/reject outcomes, ineligible approver denial, PAID gates, PENDING/CANCELED regression, and historical-paid save coverage.

**Approval levels**

- Assert root/surface/header/actions, responsive table hooks, populated rows, and structured existing-copy empty state.
- Assert list edit destination, create destination, level-one delete absence, non-base delete POST action, `_token`, and CSRF-bearing form remain unchanged.
- Assert create/edit forms keep names, fields, action, POST method, Symfony errors, save, and cancel.
- Retain route security, super-admin access, named approver, monotonic rejection, and deletion tests.

**Milestones**

- Assert roots on chooser and customer-scoped list, local table hooks, existing-copy empty state, and descriptive chooser-link accessible name.
- Assert datatable name/configuration, `data-reload-event`, batch form name/action/token/fields, warning selector, tooltip, and disabled checkbox selector remain unchanged.
- Retain customer filtering, permission, IDOR, invoiceable/convertible filtering, no-hours rejection, happy path, mixed-customer, stale-selection, invalid-ID, and foreign-customer tests.

### Command gates

After each owning slice, run its focused controller test. Before a slice is accepted, run:

```text
vendor/bin/phpunit tests/Controller/InvoiceControllerTest.php
vendor/bin/phpunit tests/Controller/InvoicePaymentApprovalLevelControllerTest.php
vendor/bin/phpunit tests/Controller/MilestoneInvoiceControllerTest.php
./phpstan.sh test
composer linting
pnpm build
./php-cs-fixer.sh core
```

Run only the tests relevant to the current slice during RED/GREEN, then all three focused suites at the cross-slice gate. `composer tests-unit` is the broader regression gate before final delivery. `pnpm lint` is optional because no JavaScript file is allowed to change. No production `src/` PHP change is permitted, so `./phpstan.sh core` should remain unnecessary; if it becomes necessary, scope has already escaped this design and work must stop.

Generated build output is validation output only and must not be committed or intentionally edited.

## Browser evidence strategy against Dokploy/main

Browser evidence is a required visual gate, not a replacement for PHPUnit. Use authenticated Chrome/CDP externally; do not add a browser dependency to the repository.

### Revision and environment protocol

1. Before implementation, record the current `origin/main` commit and confirm the Dokploy deployment at `https://gppro.tbema.net` serves that revision or identify it as an older baseline.
2. With explicit authorization, load credentials from `.env.local` without printing or storing secrets in OpenSpec artifacts.
3. Capture a read-only baseline of the current main deployment. Missing candidate selectors are baseline evidence, never a candidate pass.
4. After a slice is merged and a human confirms Dokploy deployed that exact `origin/main` revision, record the commit, deployed asset filename, browser/version, timestamp, locale, and presence of the slice's workflow selectors.
5. If deployed revision identity is uncertain or selectors are absent, classify browser verification as blocked/stale deployment rather than failed implementation.
6. Store screenshots and machine-readable reports outside tracked source; record their paths and SHA-256 digests in apply/verify artifacts. Do not put credentials, cookies, or tokens in reports.

Deployment, merge, push, and any write action on Dokploy remain human-controlled gates. The evidence runner must not submit create, approve, reject, delete, payment, or milestone-generation forms on the deployed environment.

### Route and state matrix

Use the active locale prefix discovered after login (currently expected as `/es`). Sample:

- archive: `/invoice/show/1`, populated history when available, archive empty/filter state when safely reachable;
- creation: `/invoice/`, initial, empty search, populated timesheet preview, milestone mode with/without selected customer;
- edit: direct `/invoice/edit/{id}` plus archive-triggered modal for safe representative unsubmitted, pending, and historical-paid records;
- approval levels: `/admin/invoice/payment-approval-levels/` and safe GET create/edit pages;
- milestones: `/invoice/milestones/` and `/invoice/milestones/{customerId}`.

For every available target state, capture 360px, 768px, and 1024px-or-wider in light and dark themes. Use the application's real theme preference where possible; a forced `data-bs-theme="dark"` pass is diagnostic only unless the preference flow is also confirmed. Emulate `prefers-reduced-motion: reduce` and confirm no new motion appears.

Use local/test fixtures for destructive or hard-to-source empty/populated/state combinations. Dokploy evidence is read-only and may mark unavailable data states as not observed; it must not fabricate or mutate production-like records to complete the matrix.

### Required observations

At each applicable width/theme:

- `document.documentElement.scrollWidth <= document.documentElement.clientWidth` and body width confirm no page-level overflow;
- wide archive, preview, approval-level, and milestone tables overflow only inside their designated local wrappers;
- headers and action groups wrap without overlap or off-screen primary actions;
- customer, project, status, amount, duration, warning, selection, and action context remain readable;
- surface levels, borders, normal/muted text, existing badges, form controls, disabled checkboxes, outline buttons, and focus rings remain distinguishable;
- keyboard Tab order reaches native links/buttons/fields, focus is visible, and Enter/Space retain native behavior;
- opening an archive invoice still renders the existing modal edit form, while direct edit renders the standard form;
- the creation collapse control still opens nested entries; preview/save links retain their computed destinations, but save is not activated on Dokploy;
- historical paid invoices show no approval panel or approval language;
- no selector appears outside an invoice workflow root and representative existing expense/quotation/approvals workflow pages remain visually unchanged.

Evidence reports must distinguish DOM-contract pass, computed-layout pass, interaction pass, unavailable state, and blocked/stale deployment. Descendants extending beyond a local scrollport are not page overflow if document/body widths remain bounded; record both measurements to avoid the prior false-positive pattern.

## Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| Legacy datatable markup resists local containment without shared-macro edits. | Use the workflow page root plus target-specific `.datatable_invoices`/`.datatable_milestone_invoice` selectors; do not modify shared macros. Verify document width separately from table scroll width. |
| Creation markup changes break `singleInvoice()` or generated URLs. | Do not edit JavaScript; preserve every ID/data attribute/form field; add DOM assertions before restructuring and retain create/preview tests. |
| Payment actions imply unsupported status or progress. | Render only existing actions, with existing labels and guards; no status summary, progress, archive indicator, or paid-history message. |
| Modal and direct edit variants diverge. | Keep existing embed selection and Bootstrap modal structure; assert and browser-check both rendering paths. |
| Empty-state restructuring hides errors or reload behavior. | Keep server flash/form output and datatable reload events; only wrap existing translated empty copy. |
| Disabled milestones appear selectable in dark mode. | Keep the native `disabled` attribute and selectors; use existing disabled tokens, then verify keyboard and computed contrast manually. |
| Invoice Sass leaks into expense, quotation, or approvals. | Require `.gp-workflow--invoice` ancestry for every new rule and inspect representative existing workflow consumers after build/deploy. |
| Dokploy data cannot represent every state safely. | Use Dokploy only for read-only representative evidence and local fixtures for full state triangulation; report unavailable states honestly. |
| Review size grows during responsive correction. | Measure each slice continuously, reserve a 40-line buffer, split before 400, and return to the human gate rather than infer an exception. |
| Proposal wording says auto-chain while canonical preflight says ask-on-risk. | Treat `ask-on-risk` as authoritative; define chain-compatible slices but select no delivery shape until the human decision. |

## Rollout and rollback

There is no migration, feature flag, cache schema, or backend rollout. For each human-approved delivery unit:

1. complete strict TDD and focused/cross-slice command gates;
2. confirm the review diff remains below 400 changed lines;
3. merge/deploy only through the maintainer-controlled process;
4. confirm Dokploy serves the exact `origin/main` revision;
5. capture the slice browser matrix and evidence digest;
6. proceed to the next slice only after the current slice is accepted.

Rollback is per slice: revert its Twig hooks, corresponding `.gp-workflow--invoice` Sass subsection, and additive tests. No data repair, migration rollback, token change, route compatibility step, or persisted-state correction is needed. If a shared workflow regression is observed, revert only the invoice-scoped Sass/markup slice; do not alter the canonical visual tokens or unrelated consumers.

## Implementation readiness

The technical design is complete. The next phase may write specifications/tasks, but application implementation is blocked on the required `ask-on-risk` delivery decision because the combined scope exceeds 400 changed lines.
