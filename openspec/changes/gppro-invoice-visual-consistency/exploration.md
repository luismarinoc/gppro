# Exploration: GPPro invoice visual consistency

**Change:** `gppro-invoice-visual-consistency`  
**Mode:** read-only application exploration  
**Artifact store:** OpenSpec

## Exploration constraints and method

- No application, generated, runtime, or vendor files were changed. This artifact is the only write.
- CodeGraph tooling was not exposed to this executor. Targeted repository reads and searches were used as the fallback.
- Existing OpenSpec visual-system and invoice-payment-approval specifications, the archived `gppro-visual-consistency` exploration/design, invoice Twig, controllers, Sass, translations, and controller tests were inspected.
- This change is presentation-only unless a later human decision explicitly expands scope. It must not alter calculations, approval policy, PDF/document rendering, public/customer pages, routes, permissions, CSRF, persistence, or migrations.

## Current-state findings

### Existing visual foundation is ready to consume

The prior visual-consistency change already provides the shared implementation foundation:

- `assets/sass/variables.scss` defines complete light and `[data-bs-theme="dark"]` `--gp-*` semantic tokens and their Tabler mappings.
- `assets/sass/_gppro.scss` imports `assets/sass/_workflow.scss` once.
- `_workflow.scss` scopes shared surfaces, tables, forms, states, focus treatment, responsive header/actions, and approval-specific structure beneath `.gp-workflow`; it currently has no invoice-specific modifier.
- The canonical specification at `openspec/specs/gppro-visual-system/spec.md` requires semantic-token usage and dark-theme parity without behavior changes.

Therefore invoice work should add scoped `.gp-workflow--invoice` presentation hooks and reuse the established workflow vocabulary. It should not reopen token/theming work or create a second component model.

### Authenticated invoice creation and preview workspace

`InvoiceController::indexAction()` renders `templates/invoice/index.html.twig` for authorized `create_invoice` users. The page contains:

- a dense invoice filter (`invoice-print-form`),
- a timesheet-invoice preview grouped by customer, including collapsible nested entry tables and per-customer preview/save actions,
- a milestone-mode preview and batch selection form,
- empty states via `widgets.nothing_found()`, and
- inline `singleInvoice()` JavaScript that derives existing CSRF-bearing preview/create URLs and disables the save trigger before navigation.

This is a primary candidate for visual consistency: workflow header/filter surface, preview hierarchy, nested table containment, totals/status rhythm, action grouping, empty state, and narrow-screen behavior. Existing form IDs, hidden token nodes, form fields, action routes, query parameters, and JavaScript data attributes are behavioral contracts and must remain unchanged.

### Invoice archive/list and history summary

`InvoiceController::showInvoicesAction()` renders `templates/invoice/listing.html.twig` through the normal datatable shell. It supplies archive filters/pagination, type/status/due-date badges, action menus via `invoice/actions.html.twig`, and a separate `#invoice_history_summary_box` table grouping full filtered results by customer/project/type.

The main row currently opens the existing modal edit form through `.modal-ajax-form.open-edit`; it is not a GPPro opt-in row link. Candidate presentation work includes a workflow root, tokenized archive/history tables, status/due-date treatment, responsive local overflow, and summary emphasis. It must retain action-menu behavior, row modal behavior, table data attributes, status-change URLs, and the archive/export data flow.

### Invoice edit and payment-approval controls

`templates/invoice/invoice_edit.html.twig` uses the standard form or modal form embed and appends payment controls only for users granted `edit_invoice`:

- unsubmitted/unpaid invoice: POST `invoice_submit_payment_approval` with token `invoice_submit_payment_approval_{id}`;
- pending payment approval: POST `invoice_approve_payment` and `invoice_reject_payment`, each with its existing per-invoice token;
- paid historical invoices show none of these controls.

These forms are a sensitive visual candidate: a compact, semantic payment-approval state/action panel can improve hierarchy and focus while preserving all guards, POST methods, route names, token IDs, field names, and redirect behavior. The current template does not visibly summarize approval level/progress; any proposal to introduce a new visible status must first confirm the required product language and not label historical paid invoices as unapproved.

`InvoiceController` owns all relevant state/status paths, including the PAID transition gate and submit/approve/reject endpoints. Presentation work should not modify it. The existing `openspec/specs/invoice-payment-approval/spec.md` is the behavioral authority: only PAID is gated; PENDING/CANCELED stay ungated; required levels freeze at submission; eligibility and audit remain intact; historical PAID invoices are grandfathered.

### Payment-approval-level administration

`InvoicePaymentApprovalLevelController` provides fully authenticated, `manage_invoice_payment_approval_levels`-guarded list/create/edit/delete screens:

- `templates/invoice_payment_approval_level/index.html.twig` is a generic card/table with click-through rows, edit anchors, protected POST deletes, and a table-row empty message.
- `templates/invoice_payment_approval_level/edit.html.twig` is a generic card/form with native save/cancel controls.

These are strong candidates for the shared workflow surface, action wrapping, responsive table, form, and contextual empty-state patterns. They require preserving super-admin authorization, the monotonic-threshold validation, level-one delete restriction, delete POST method, and `invoice_payment_approval_level` CSRF token.

### Milestone invoicing

`MilestoneInvoiceController` exposes two authenticated `create_invoice` screens:

- `templates/milestone-invoice/customers.html.twig`: customer chooser with a table and existing native invoice-icon link;
- `templates/milestone-invoice/index.html.twig`: customer-scoped invoiceable milestone datatable/batch form, including a no-billable-hours warning and disabled checkbox.

Both are candidates for an invoice workflow root, empty state, local table responsiveness, disabled-control contrast, warning semantics, and action consistency. The batch form, `gppro.invoiceUpdate` reload event, `milestone_invoice` datatable configuration, warning/checkbox selectors, customer access scoping, and server revalidation must remain intact.

### Secondary authenticated administration surfaces

`InvoiceController` also renders:

- `templates/invoice/templates.html.twig` — template list;
- `templates/invoice/template_edit.html.twig` — invoice-template configuration form, including renderer/calculator choice and logo upload/removal;
- `templates/invoice/document_upload.html.twig` — renderer-document upload/list management.

They are authenticated admin configuration screens and visually use legacy cards, forms, tables, and modal-edit rows. They are viable secondary candidates if “invoice/factura screens” includes template/renderer administration. However, renderer document contents and invoice output remain explicitly out of scope; this change must not change `templates/invoice/renderer/**`, upload validation, document/template persistence, or rendered/PDF output. Including these surfaces materially increases the delivery size and should be decided before proposal.

## Affected surfaces and likely files

| Priority | Surface | Likely presentation files | Behavioral/test seams to preserve |
| --- | --- | --- | --- |
| Primary | Invoice creation, preview, and milestone-mode preview | `templates/invoice/index.html.twig`, `assets/sass/_workflow.scss` | `src/Controller/InvoiceController.php`; `tests/Controller/InvoiceControllerTest.php` creation, preview, milestone-mode, and IDOR coverage |
| Primary | Invoice archive and history totals | `templates/invoice/listing.html.twig`, possibly `assets/sass/_workflow.scss` | datatable/modal/action macros, archive/export behavior, history-summary assertions in `InvoiceControllerTest.php` |
| Primary | Invoice edit payment actions | `templates/invoice/invoice_edit.html.twig`, possibly `assets/sass/_workflow.scss` | `InvoiceController` payment/PAID paths; payment approval behavior and historical-paid assertions in `InvoiceControllerTest.php` |
| Primary | Payment-approval-level list and form | `templates/invoice_payment_approval_level/index.html.twig`, `edit.html.twig`, `assets/sass/_workflow.scss` | `InvoicePaymentApprovalLevelController`; `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` route/role/CSRF/monotonic/delete tests |
| Primary | Milestone invoice customer and selection screens | `templates/milestone-invoice/customers.html.twig`, `index.html.twig`, `assets/sass/_workflow.scss` | `MilestoneInvoiceController`; `tests/Controller/MilestoneInvoiceControllerTest.php` access, empty, warning, batch-generation, and IDOR coverage |
| Optional | Template list/edit and renderer-document management | `templates/invoice/templates.html.twig`, `template_edit.html.twig`, `document_upload.html.twig`, `assets/sass/_workflow.scss` | template/logo/upload/delete tests in `InvoiceControllerTest.php`; no renderer/PDF changes |
| Reuse only | Shared visual system | `assets/sass/variables.scss`, `assets/sass/_gppro.scss`, `assets/sass/_workflow.scss` | `openspec/specs/gppro-visual-system/spec.md`; do not modify existing tokens/imports unless a concrete invoice gap proves necessary |
| Conditional | User-facing copy | `translations/messages.en.xlf`, `translations/messages.es.xlf` | Existing payment-approval-level and milestone strings already exist in both catalogs; add paired translations only for genuinely new visible or assistive text |

## Tests and translation inventory

- `tests/Controller/InvoiceControllerTest.php` already covers archive details/history totals, invoice creation/preview/download/status, milestone mode and its customer IDOR guard, template CRUD/logo handling, and payment submission/approval/rejection/PAID-gate/historical-paid behavior. It is the principal seam for additive DOM/class/route/form assertions.
- `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php` validates authentication/role restrictions, rendered form tokens, monotonic errors, and protected deletion. Add only DOM contracts that do not weaken these behavioral assertions.
- `tests/Controller/MilestoneInvoiceControllerTest.php` covers authentication, customer selection, empty state, no-hours warning/disabled selection, generation, and stale/mixed/unauthorized selection defenses. Preserve the current warning and batch-form selectors if markup changes.
- Existing English and Spanish translations include payment action labels, payment-approval-level labels and empty copy, milestone titles/actions/warnings/empty copy, and relevant flash messages. A purely structural visual pass should not need translations.
- PHPUnit can validate emitted markup and preserve protected forms, but not computed dark-theme contrast, responsive layout, focus visibility, or modal interaction. Those require a recorded browser pass at 360px, 768px, and 1024px+ in light/dark themes.

## Risks and non-goals

### Risks

| Risk | Mitigation |
| --- | --- |
| Visual edits accidentally alter financial/approval behavior | Do not edit controllers, services, entities, policies, routes, forms, or migrations. Retain every `is_granted`, POST method, action URL, field name, and CSRF token ID; run the focused controller suites. |
| Payment UI misrepresents approval status | Reuse the invoice-payment-approval specification. Do not infer a status for historical PAID invoices; clarify whether progress/status copy is desired before adding it. |
| Standard form/modal variants diverge | `invoice_edit.html.twig` serves both standard and `gppro_context.modalRequest` form embeds. Keep wrappers compatible with both and manually verify each presentation path. |
| Dense invoice data becomes unreadable on mobile | Use existing workflow local table scrolling and action wrapping; retain key customer, status, amount, warning, review/action, and batch-selection information. |
| Invoice preview JavaScript regresses | Preserve all `invoice-print-form`, `create-token`, `preview-token`, `data-customer`, `data-href`, and `singleInvoice()` contracts. |
| Admin configuration scope grows beyond review budget | Treat template/renderer administration as an explicit optional slice; do not combine it with core invoice/payment work by default. |

### Non-goals

- Invoice calculations, totals, currencies, invoice model generation, approval resolution/policy/audit, status-transition rules, payment dates, or persistence.
- PDFs, printable invoice templates, renderer source/content, downloaded invoice documents, public/customer pages, and generated assets under `public/build/`.
- Routes, permissions, CSRF behavior, controllers, entities, migrations, vendor, plugins, or runtime data.
- A new frontend dependency/framework or a repository-wide retrofit of legacy modal/table rows.

## Suggested vertical slices (each under the 400-line review budget)

| Slice | Scope | Estimated changed lines | Boundary |
| --- | --- | ---: | --- |
| 1. Archive consistency | Invoice archive row/history-summary workflow markup and invoice-specific scoped table/status styles; additive DOM assertions. | 180–280 | Preserve datatable, modal row, actions macro, and status links. |
| 2. Creation workspace | Invoice filter/preview/milestone-mode workspace hierarchy, tables, actions, and empty state; scoped responsive styles and focused tests. | 260–380 | Preserve form/JS/token/query contracts and nested preview tables. |
| 3. Edit payment panel | Invoice edit workflow/form-compatible payment action/status presentation with DOM regression assertions. | 100–180 | No controller or approval-policy changes; keep historical paid action-free. |
| 4. Approval-level administration | List/form workflow surfaces, contextual empty state, responsive actions/table, and controller DOM assertions. | 160–260 | Preserve super-admin, monotonic, delete, POST, and CSRF behavior. |
| 5. Milestone invoicing | Customer chooser and selection datatable/batch form visual treatment, warning/disabled-state contrast, and focused tests. | 160–280 | Preserve access scoping, reload event, warning/checkbox hooks, and batch behavior. |
| 6. Optional template/renderer administration | Template list/form and document-manager presentation only, with existing test preservation. | 220–360 | Excludes renderer content/output and must be separately approved. |

The complete primary scope exceeds 400 changed lines. Under the canonical `ask-on-risk` delivery strategy, implementation must pause for a human delivery decision before an oversized combined diff or any chain strategy is selected. No `size:exception` is implied.

## Questions and decisions needed before proposal

1. Is the intended scope the five primary operational invoice/payment/milestone surfaces only, or should it also include invoice template and renderer-document administration as optional slice 6?
2. Should the invoice edit surface display a read-only payment-approval progress/status treatment, or should it only modernize the existing submit/approve/reject action grouping? New status language must protect the historical-PAID grandfathering rule.
3. Should invoice archive status presentation remain limited to its existing invoice lifecycle badges, or should pending payment approval gain an explicit visual indicator? This is a product decision, not merely styling.
4. Is the desired design direction to use the already-established restrained `.gp-workflow` vocabulary without new brand tokens/components? Exploration recommends yes.
5. Once proposal/design estimates are confirmed, how should the above vertical slices be delivered under `ask-on-risk`? This is a required human gate before application changes if the selected set exceeds 400 lines.

## Recommended next phase

Proceed to proposal/spec/design after the scope decision above. The proposal should define an authenticated invoice-workflow visual requirement that reuses the canonical GPPro visual-system contract, preserves payment-approval semantics and all protected controls, explicitly excludes document/PDF/public output, and decomposes delivery into the selected sub-400-line slices.
