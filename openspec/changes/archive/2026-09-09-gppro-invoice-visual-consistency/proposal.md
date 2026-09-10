# Proposal: GPPro invoice visual consistency

## Executive summary

Modernize the primary operational invoice workflows so they use the established GPPro visual system consistently across light and dark themes, responsive widths, dense tables, forms, statuses, empty states, and action groups. The change is presentation-only: it preserves all invoice, milestone, and payment-approval behavior while making the affected staff workflows easier to scan and operate.

The work will be delivered as an auto-chained set of vertical slices, each kept within the 400 changed-line review budget. Each slice will include its surface markup, scoped styling, and focused regression coverage rather than separating work by technical layer.

## Intent

Invoice operations currently span several visually inconsistent legacy layouts despite the shared GPPro workflow vocabulary already being available. Dense creation previews, archive history, payment actions, approval-level administration, and milestone invoicing do not yet present a coherent hierarchy or responsive experience.

This change will apply the existing `.gp-workflow` vocabulary with an invoice-specific `.gp-workflow--invoice` scope. It will improve visual hierarchy, action discoverability, local table containment, empty-state clarity, focus visibility, and light/dark theme parity without creating a second design system or changing financial behavior.

## Target users and outcome

The primary users are authenticated staff who create, review, edit, approve, administer, or generate invoices during routine operational workflows.

After the change, these users should be able to:

- recognize invoice workflow sections and key actions more quickly;
- scan archive, history, preview, approval, and milestone data with consistent hierarchy;
- reach existing actions at narrow viewport widths without page-level overflow;
- distinguish surfaces, controls, disabled states, focus, and existing statuses in both supported themes; and
- complete the same workflows with unchanged routes, submissions, permissions, calculations, and outcomes.

## Scope

### Included surfaces

1. **Invoice archive/listing and history summary**
   - Apply the invoice workflow root and shared table/surface vocabulary.
   - Improve archive hierarchy, local responsive overflow, action containment, and history-summary emphasis.
   - Preserve the existing lifecycle badges and status treatment.
   - Do not introduce a payment-pending indicator.

2. **Invoice creation and preview workspace**
   - Structure the filter, customer previews, nested entry tables, totals, empty states, and preview/save actions as one coherent workflow.
   - Improve responsive containment and action grouping while retaining the current dense operational information.
   - Preserve all existing form, token, URL, query-parameter, data-attribute, and JavaScript hooks.

3. **Invoice edit payment action panel**
   - Modernize only the existing submit/approve/reject action grouping.
   - Keep the panel compatible with both standard and modal form rendering.
   - Do not add payment approval progress, summaries, labels, or new status language.
   - Keep paid historical invoices free of retroactive approval messaging and controls.

4. **Invoice payment approval level administration**
   - Apply shared workflow surfaces to the level list and edit form.
   - Improve table responsiveness, action wrapping, form hierarchy, and the existing contextual empty state.
   - Preserve the current authorization, validation, deletion, POST, and CSRF contracts.

5. **Milestone invoicing**
   - Apply consistent workflow treatment to the customer chooser and customer-scoped milestone selection screen.
   - Improve table containment, batch-action hierarchy, warning semantics, empty states, disabled-control contrast, and responsive behavior.
   - Preserve customer scoping, reload events, datatable configuration, selectors, and server revalidation.

### Explicitly excluded

- Invoice template administration.
- Renderer-document upload or management.
- PDF, renderer, printable, public, or customer-facing output.
- Backend behavior, controllers, services, entities, routes, permissions, authorization, or approval eligibility.
- CSRF names, methods, validation, form field names, persistence, migrations, or audit behavior.
- Invoice calculations, totals, currencies, model generation, payment dates, status transitions, approval policy, or frozen-level rules.
- New payment approval progress/status language or an archive payment-pending indicator.
- New frontend dependencies, new visual tokens without a proven gap, or a repository-wide legacy UI retrofit.

## Product and visual rules

- The canonical `--gp-*` semantic tokens are the source of truth for colors, surfaces, borders, focus, statuses, shadows, and disabled controls.
- Invoice-specific presentation must remain scoped under `.gp-workflow--invoice` and reuse the existing workflow component vocabulary.
- Existing invoice lifecycle badges remain authoritative and visually recognizable; styling must not reinterpret payment approval state.
- Existing protected actions remain native, descriptively named, keyboard operable, and visibly focused.
- Dense tables may use intentional local horizontal scrolling, but the page itself must not overflow at the target viewport widths.
- Existing server errors, flash messages, calculations, and persisted outcomes remain authoritative.
- Existing translated strings should be reused. If implementation proves that net-new visible or assistive text is necessary, it must be supplied in both English and Spanish and must not introduce prohibited approval-status language.

## Affected areas

| Area | Likely files | Contracts to preserve |
| --- | --- | --- |
| Shared invoice presentation | `assets/sass/_workflow.scss` | Existing workflow consumers, semantic tokens, one-time stylesheet import |
| Archive and history | `templates/invoice/listing.html.twig` | Datatable attributes, modal edit row, actions macro, status URLs, filters, pagination, export flow |
| Creation and preview | `templates/invoice/index.html.twig` | `invoice-print-form`, token nodes, form fields, `data-customer`, `data-href`, `singleInvoice()`, preview/create URLs |
| Edit payment actions | `templates/invoice/invoice_edit.html.twig` | Permission guards, POST methods, route names, token IDs, redirect behavior, modal and standard embeds |
| Approval-level administration | `templates/invoice_payment_approval_level/index.html.twig`, `templates/invoice_payment_approval_level/edit.html.twig` | Super-admin access, monotonic thresholds, level-one deletion rule, protected delete submission |
| Milestone invoicing | `templates/milestone-invoice/customers.html.twig`, `templates/milestone-invoice/index.html.twig` | Customer access scope, batch form, datatable configuration, reload event, warning and checkbox hooks |
| Focused regression coverage | `tests/Controller/InvoiceControllerTest.php`, `tests/Controller/InvoicePaymentApprovalLevelControllerTest.php`, `tests/Controller/MilestoneInvoiceControllerTest.php` | Existing behavioral, authorization, CSRF, IDOR, payment, and generation assertions remain intact |

Controllers, domain services, entities, migrations, renderer templates, generated assets, plugins, and runtime data are not affected.

## Delivery strategy

Delivery is an auto-chained sequence of vertical slices. Every slice must stay within the 400 changed-line review budget and combine the relevant markup, scoped styles, and focused regression assertions.

| Slice | Vertical outcome | Expected boundary |
| --- | --- | --- |
| 1. Archive consistency | Modernized archive/listing and history summary | Preserve datatable, modal-row, actions, status, filter, and export behavior |
| 2. Creation workspace | Modernized filter and invoice/milestone-mode preview workspace | Preserve form, JavaScript, token, URL, nested-table, and query contracts |
| 3. Edit payment panel | Modernized existing submit/approve/reject grouping | No new status/progress language and no approval-policy changes |
| 4. Approval-level administration | Modernized level list and edit form | Preserve role, validation, delete, POST, and CSRF behavior |
| 5. Milestone invoicing | Modernized customer chooser and selection/batch workflow | Preserve access scope, events, selectors, revalidation, and generation behavior |

Slices should be reviewed and reversible independently. No `size:exception` is accepted or implied.

## Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| Markup changes break invoice preview JavaScript or protected forms | Retain all IDs, names, data attributes, URLs, methods, and CSRF token IDs; add focused DOM assertions and run existing controller coverage. |
| Visual hierarchy implies payment approval state that the product does not expose | Limit invoice edit changes to action grouping and retain archive lifecycle badges without new approval indicators or copy. |
| Standard and modal invoice edit variants diverge | Use wrappers compatible with both embeds and verify both rendering paths. |
| Dense invoice data becomes unusable on small screens | Use local table overflow and wrapping action groups while retaining customer, status, amount, warning, selection, and action context. |
| Invoice-specific styles regress other GPPro workflows | Scope additions under `.gp-workflow--invoice`, reuse semantic tokens, and verify representative existing workflow consumers. |
| Theme parity or focus visibility is missed by controller tests | Record manual browser checks at 360px, 768px, and 1024px or wider in light and dark themes, including reduced motion and keyboard traversal. |
| A vertical slice exceeds the review budget | Split that surface into smaller coherent vertical outcomes before implementation; do not silently accept an oversized slice. |

## Rollback

Each vertical slice can be reverted independently by removing its invoice workflow wrappers, invoice-scoped styles, and additive DOM assertions. Because the change does not modify persistence, migrations, routes, calculations, or approval behavior, rollback requires no data repair or compatibility migration. If a shared-style regression appears, revert the affected slice while retaining previously accepted slices.

## Success criteria

- All five included operational areas use a coherent GPPro workflow hierarchy and invoice-scoped presentation.
- Light and dark themes provide distinguishable surfaces, text, borders, existing statuses, controls, disabled states, and keyboard focus.
- Empty and populated states remain understandable at 360px, 768px, and 1024px or wider without page-level horizontal overflow.
- Existing invoice lifecycle badges remain unchanged in meaning, and no payment-pending archive indicator is added.
- Invoice edit shows only a modernized grouping of the currently available payment actions, with no new progress or status language.
- Existing routes, HTTP methods, permissions, CSRF contracts, form fields, JavaScript hooks, calculations, persistence, approval rules, and rendered outputs behave unchanged.
- Existing focused controller suites continue to pass with additive presentation-contract coverage.
- Every delivery slice remains within the 400 changed-line review budget and is independently reviewable and reversible.

## Proposal question round

The confirmed product decisions resolve the exploration's blocking scope and status-language questions. This proposal assumes the business priority is faster, clearer staff operation rather than new invoice capabilities, and that existing translated copy is sufficient for the first slice. No additional product question blocks progression; stakeholders may correct these assumptions or request a second question round before specification or design.

## Next step

Create the change specification and implementation design from this proposal. They should define verifiable presentation scenarios, the exact preserved DOM/behavior contracts, responsive and theme checks, and the dependency order for the five auto-chained vertical slices.
