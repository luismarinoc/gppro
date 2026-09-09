# Design: GPPro Visual Consistency

## Design Summary

Modernize the authenticated expense, quotation, and approval surfaces by adding one narrowly scoped workflow presentation layer on top of the existing GPPro tokens, Bootstrap 5, and Tabler markup. The implementation will keep all routes, controller data, permissions, forms, CSRF identifiers, calculations, and persistence unchanged. Shared styles will own workflow structure and state presentation; quotation-specific styles will continue to own quotation line and summary layout; narrowly designated list rows will gain an explicit keyboard contract through the existing alternative-link plugin.

This is a product-interface consistency change, not a new component system. Familiar controls, restrained color, tokenized surfaces, compact state transitions, and predictable responsive collapse take precedence over decorative effects.

## Goals

1. Give authenticated expense, quotation, and approval screens one GPPro workflow vocabulary in light and dark themes.
2. Define exact reusable boundaries for headers, surfaces, actions, metadata, statuses, tables, forms, and empty or advisory lookup states.
3. Make designated expense and quotation row navigation operable on Enter without stealing nested control interaction.
4. Preserve all server-authoritative behavior and all existing quotation editor selectors and focus behavior.
5. Validate 360px, 768px, and 1024px-or-wider behavior in both themes, with keyboard-only traversal and reduced motion.
6. Keep implementation single-writer and stop for a delivery decision before exceeding the 400 changed-line review budget.

## Non-Goals

- No controller, repository, voter, policy, service, entity, route, schema, migration, or persistence changes.
- No changes to quotation PDFs or public quotation response templates.
- No new frontend framework, JavaScript test framework, third-party dependency, polling, or decorative animation.
- No repository-wide accessibility retrofit of every `.alternative-link` consumer.
- No change to legally required Kimai attribution.
- No inline expense or invoice decisions on the approvals dashboard.

## Current Implementation Constraints

- `templates/base.html.twig` already provides `gp-app-shell`, `gp-content-frame`, the skip link, global `:focus-visible`, and the frontend entry points.
- `assets/sass/variables.scss` defines the stable `--gp-*` light-theme vocabulary but currently leaves those values light under `data-bs-theme='dark'`.
- `assets/sass/_gppro.scss` is the single Sass import entry and already imports `_quotation.scss`.
- Expense templates are generic card/table/form markup. The list uses a conditional edit-or-view `data-href`; the detail page owns submit, delete, charge, approve, and reject forms.
- Quotation edit behavior is inline in `templates/quotation/edit.html.twig`. Its `data-*` selectors, ten-line limit, add/edit/confirm/remove behavior, subtotal calculation, and focus-after-add behavior are existing contracts.
- `GpproLoader.js` registers `GpproAlternativeLinks` globally for `.alternative-link`; therefore keyboard behavior must be opt-in and must not make every legacy row focusable.
- The project has ESLint and Encore build scripts but no JavaScript unit or browser test runner. PHPUnit can test rendered DOM contracts but cannot execute delegated key events or computed responsive CSS.

## Architecture Decisions

| ID | Decision | Choice | Rejected alternative | Rationale |
| --- | --- | --- | --- | --- |
| D1 | Shared workflow styling | Add `assets/sass/_workflow.scss`, imported once by `_gppro.scss`, and scope all selectors below `.gp-workflow`. | Global overrides for every `.card`, `.table`, and `.form-control`. | Prevents finance work from changing unrelated legacy screens while allowing expense, quotation, and approvals to share one vocabulary. |
| D2 | Quotation styling ownership | Keep quotation line-editor, totals, and sticky-summary rules in `_quotation.scss`, nested below `.gp-workflow--quotation`. | Move all quotation rules into the shared workflow file. | Line editing and summary behavior are domain-specific and should not become generic primitives. |
| D3 | Theme contract | Keep current token names stable, add missing semantic roles, and provide a complete `[data-bs-theme='dark']` override in `variables.scss`. | Theme-specific component selectors or a second dark stylesheet. | Components remain theme-agnostic and existing modernized consumers receive the same semantic contract. |
| D4 | Empty-state structure | Render a `.gp-workflow-state--empty` block instead of a lone `colspan` row when a targeted collection is empty. | Continue styling table-only empty rows. | Gives empty states contextual structure and avoids exposing an empty table as the only message. |
| D5 | Lookup feedback | Keep one persistent status region per lookup and render explicit `idle`, `loading`, `available`, and `unavailable` states. | Hide feedback on failure or use a spinner/animation-only signal. | Preserves layout, supplies visible and assistive feedback, and remains advisory rather than authoritative. |
| D6 | Row navigation | Retain existing mouse row navigation, add an opt-in `data-gp-row-link` contract, and handle Enter in `GpproAlternativeLinks`; do not change `GpproReducedClickHandler`. | Make all `.alternative-link` rows keyboard targets or wrap a `<tr>` in an anchor. | Avoids broad blast radius and invalid table markup while retaining current conditional destinations. |
| D7 | Interaction protection | Ignore key events originating from `a`, `button`, `input`, `select`, `textarea`, `label`, `[role='button']`, `[role='link']`, menus, or editable content inside a designated row. | Stop only anchors and buttons. | Meets the nested-control contract and prevents row navigation from competing with current action menus. |
| D8 | Responsive strategy | Use Bootstrap breakpoints and CSS Grid/Flex wrapping, keep table scrolling local, and enable quotation summary stickiness only at `lg` and above. | Page-level overflow, fixed widths, or sticky summary at tablet/phone widths. | Matches existing GPPro patterns and preserves reading/editing order at 360px and 768px. |
| D9 | Async race handling | Use a monotonically increasing request token for both expense preview and quotation FX lookup; every new request or reset invalidates older responses. | Allow out-of-order fetch responses to update the UI. | A stale advisory result is misleading even though server calculations remain authoritative. |
| D10 | Test architecture | Use PHPUnit DOM tests first for emitted contracts and existing behavior, then ESLint/build and a recorded browser matrix for runtime/CSS behavior not executable by the current test stack. | Add a new JS/browser dependency solely for this change. | Preserves dependency constraints while applying strict test-first work at every available automated seam. |

## Semantic Token Contract

`assets/sass/variables.scss` remains the only owner of theme values. Existing names remain unchanged. The implementation may add only role-based tokens needed by the specifications:

- Existing core roles: `--gp-bg`, `--gp-surface`, `--gp-surface-muted`, `--gp-ink`, `--gp-ink-muted`, `--gp-brand`, `--gp-brand-soft`, `--gp-line`, `--gp-focus`, `--gp-shadow-surface`, radius and font tokens.
- Semantic state pairs: foreground and soft-background roles for positive, warning, danger, and informational states.
- Control roles: outline foreground/border and disabled foreground/background/border.

The `[data-bs-theme='dark']` block must override every color/shadow role above and the Tabler values actually consumed by shared GPPro components: primary color and RGB, body background and RGB, body color, card/surface backgrounds, border colors, primary subtle treatment, secondary/muted text, input background/border, and disabled treatment. Component Sass must not contain separate light/dark selectors when a semantic token can express the role.

Contrast validation is required for normal and muted text, table headers, status labels, links, outline buttons, disabled controls, and focus rings. Existing radius and font tokens are theme-independent and remain unchanged.

## Component and Style Boundaries

### Shared workflow root

Each targeted template sets a page-level root through `page_class` and renders one `.gp-workflow` container:

- Expense: `.gp-workflow.gp-workflow--expense`
- Quotation: `.gp-workflow.gp-workflow--quotation`
- Approvals: `.gp-workflow.gp-workflow--approvals`

No shared workflow selector may style outside `.gp-workflow`.

### Shared structural classes

`_workflow.scss` owns only these reusable roles:

- `.gp-workflow__header`: title/context and action-group layout.
- `.gp-workflow__actions`: native links/buttons/forms, wrapping and narrow-screen full-width behavior.
- `.gp-workflow__surface`: tokenized border, surface, radius, and shadow.
- `.gp-workflow__section`: domain grouping and vertical rhythm.
- `.gp-workflow__metadata`: definition-list/grid layout for labelled values.
- `.gp-workflow__status`: compact semantic status treatment using modifier classes, never raw theme colors.
- `.gp-workflow__table-scroll` and `.gp-workflow__table`: local horizontal scrolling, tokenized headers/cells, row hover, and designated-row focus.
- `.gp-workflow__form` and `.gp-workflow__field-group`: labels, controls, focus, errors, helper text, and action spacing without replacing Symfony form rendering.
- `.gp-workflow-state` with `--empty`, `--loading`, and `--unavailable` modifiers: contextual states with stable spacing.

These classes enhance existing Bootstrap/Tabler classes rather than replace their semantics. Native anchors, buttons, forms, tables, labels, outputs, and definition lists remain native elements.

### Expense-specific boundary

Expense-only selectors remain below `.gp-workflow--expense` in `_workflow.scss` unless the implementation proves a separate `_expense.scss` is needed to stay below the review budget per slice. They own:

- Allocation grid collapse and remove-button alignment.
- Expense detail metadata and allocation table sizing.
- Decision-panel form layout and uniquely associated note labels.
- Currency-preview status-region minimum height and state color.

The expense list must retain the exact conditional URL expression based on `is_granted('edit_expense', expense)`. Detail forms must retain their current route names, POST methods, field names, CSRF token IDs, and permission guards.

### Quotation-specific boundary

`_quotation.scss`, scoped below `.gp-workflow--quotation`, owns:

- General-information and line-editor spacing.
- Existing `[data-quotation-line]`, view/edit state, subtotal, and line-action layout.
- One-column phone layout and coherent tablet ordering for line fields and controls.
- Summary/totals typography and width.
- `.quotation_summary_sticky`, static below `lg` and sticky with `top: 1rem` at `lg` and above.
- Quotation-specific responsive rules; public/PDF templates receive no workflow root and therefore cannot match these selectors.

All existing behavioral `data-*` attributes remain unchanged. New status hooks are additive.

### Approvals-specific boundary

`.gp-workflow--approvals` uses one workflow section for each domain. It may style domain headers, local table scrolling, states, and action wrapping, but it must not introduce new write controls. Expense and invoice retain descriptive native review anchors. Timesheet retains only its existing protected approve/reject forms.

## Markup Contracts

### Designated row link

Only expense and quotation list rows receive the opt-in contract:

```html
<tr class="alternative-link ..."
    data-href="..."
    data-gp-row-link
    tabindex="0"
    role="link"
    aria-label="localized destination and record label">
```

Requirements:

- `data-href` remains the existing conditional edit/view destination.
- `aria-label` identifies both the operation/destination and the row record.
- Existing nested links and action-menu controls remain native and unchanged.
- Focus is visible through `.gp-workflow [data-gp-row-link]:focus-visible` using `--gp-focus`.
- No other `.alternative-link` consumer receives `tabindex`, role, or keyboard handling.

### Empty state

When a targeted collection is empty, the template renders a contextual block outside the table body. The table may be omitted entirely for that empty collection. When populated, the existing semantic table remains. Quotation line emptiness reuses its current `data-quotation-lines-empty` hook and adds the shared state class; JavaScript continues to toggle its `hidden` property.

### Dynamic lookup status

Each lookup has one persistent element with:

- `role="status"`, `aria-live="polite"`, and `aria-atomic="true"`.
- The existing endpoint URL and successful-result templates in data attributes.
- Localized loading and unavailable strings in data attributes.
- A `data-state` value of `idle`, `loading`, `available`, or `unavailable`.
- Text content that changes atomically; no fabricated fallback amount/rate.

The region is informational. Symfony field errors, form-level errors, and flash messages remain separate and authoritative.

### Expense decision note

Approve and reject forms each receive a visible `<label>` and a unique `id` on their textarea. The textarea keeps `expense_approval_decision_form[note]`; each form keeps the existing CSRF name/token and action. Placeholder text may remain as a hint but is not the label.

## Interaction Contracts

### Row navigation event flow

1. Existing delegated click handling continues for `.alternative-link`.
2. `GpproAlternativeLinks` adds one delegated `keydown` listener scoped to `[data-gp-row-link]`.
3. Any key other than unmodified Enter is ignored. Space is not treated as activation because the affordance has link semantics.
4. If the event originated in a nested interactive/editable element, the row handler returns without preventing default or propagation.
5. Otherwise, the closest designated row is resolved, its non-empty `data-href`/`href` is read, default is prevented once, and the existing navigation callback is used.
6. Repeated initialization must not install duplicate behavior; the plugin lifecycle must remain consistent with its current one-time loader registration.

### Expense currency preview state flow

```text
missing input/date or CLP
  -> invalidate prior request -> idle -> clear advisory text
eligible foreign-currency input
  -> increment token -> loading
  -> latest successful convertible response -> available(converted amount/date)
  -> latest non-convertible, non-OK, or rejected fetch -> unavailable
stale response
  -> ignored
```

The 350ms debounce remains. Endpoint, request parameters, locale/date parsing, and amount formatting remain unchanged.

### Quotation FX lookup state flow

```text
CLP or missing lookup prerequisites
  -> invalidate prior request -> idle -> clear rate and CLP advisory row
eligible currency/date
  -> increment token -> loading
  -> latest available response -> available -> update advisory CLP equivalent
  -> latest unavailable, non-OK, or rejected fetch -> unavailable -> no CLP amount
stale response
  -> ignored
```

`updateSummary()` continues to calculate client previews exactly as today. The lookup state only controls the advisory FX row/status and never changes submitted values, line limits, or server calculations.

## Data Flow and Behavioral Boundaries

```text
Controller-authorized collection/entity/form
  -> unchanged Twig variables and permission checks
  -> additive workflow classes, labels, state regions, and row-link attributes
  -> existing GPPro/Tabler primitives + semantic --gp-* tokens
  -> browser interaction through existing inline finance scripts and GpproAlternativeLinks
  -> unchanged routes/forms/CSRF/server validation/calculation/persistence
```

No client state is sent as an authoritative value. Lookup feedback is derived only for display. Existing form controls remain the submitted source, and existing controller/service calculations remain the saved source.

## File Change Plan

| File | Action | Exact responsibility |
| --- | --- | --- |
| `assets/sass/variables.scss` | Modify | Complete dark semantic tokens and required Tabler mappings; keep existing names stable. |
| `assets/sass/_gppro.scss` | Modify | Import the shared workflow stylesheet once. |
| `assets/sass/_workflow.scss` | Add | Scoped shared workflow, state, expense, approvals, focus, and responsive presentation. |
| `assets/sass/_quotation.scss` | Modify | Quotation line, totals, summary, and breakpoint-specific rules below the quotation root. |
| `templates/expense/index.html.twig` | Modify | Workflow/list structure, contextual empty state, status treatment, designated row-link attributes; preserve conditional URL. |
| `templates/expense/pending.html.twig` | Modify | Workflow section, native review controls, contextual empty state. |
| `templates/expense/edit.html.twig` | Modify | Workflow form/allocation hooks and accessible lookup state; preserve form and allocation selectors. |
| `templates/expense/view.html.twig` | Modify | Workflow metadata/table/actions, labelled decision notes; preserve all guards/forms/tokens. |
| `templates/quotation/index.html.twig` | Modify | Workflow header/filter/list, status and empty state, designated row-link attributes. |
| `templates/quotation/edit.html.twig` | Modify | Workflow/line/summary classes and accessible FX status; preserve all existing behavior selectors. |
| `templates/quotation/view.html.twig` | Modify | Workflow metadata, line/totals table and responsive action grouping; no PDF/public changes. |
| `templates/approvals_dashboard/index.html.twig` | Modify | Domain sections, contextual overall/per-domain states, responsive native controls; no action-model changes. |
| `assets/js/plugins/GpproAlternativeLinks.js` | Modify narrowly | Add opt-in delegated Enter handling and nested-control escape checks. |
| `assets/js/plugins/GpproReducedClickHandler.js` | No change | Preserve global click behavior and blast radius. |
| `translations/messages.en.xlf` | Modify only as needed | Add localized loading and quotation unavailable text, plus a dedicated note label only if no exact existing key exists. |
| `translations/messages.es.xlf` | Modify with English | Add exact Spanish counterparts for every net-new state/label key. |
| `tests/Controller/ExpenseControllerTest.php` | Modify | Add DOM contracts while preserving access-scoping and business-behavior coverage. |
| `tests/Controller/QuotationControllerTest.php` | Modify | Add workflow/state/row contracts and preserve editor/save/PDF behavior assertions. |
| `tests/Controller/ApprovalsDashboardControllerTest.php` | Modify | Add state/section/control contracts and preserve authorization/navigation/POST/CSRF assertions. |

Generated files under `public/build/` are never modified.

## Active `expense-access-scoping` Overlap Resolution

`openspec/changes/expense-access-scoping/` remains an active change artifact. Its tasks show implementation complete, but it owns current changes in `ExpenseVoter`, `ExpenseRepository`, `ExpenseController`, and the access-control sections of `ExpenseControllerTest`.

The overlap is resolved as follows:

1. Treat `expense-access-scoping` as the upstream behavioral baseline, whether it is already on the working branch or must be integrated before the expense visual slice.
2. This change must not edit `src/Controller/ExpenseController.php`, `src/Repository/ExpenseRepository.php`, `src/Voter/ExpenseVoter.php`, or duplicate their access logic.
3. The only shared file is `tests/Controller/ExpenseControllerTest.php`. The visual change may append presentation assertions but must preserve all scoping fixtures, helpers, and tests for creator, team member, approver, admin, list exclusion, and direct 403 denial.
4. Immediately before the expense slice, the single writer must re-read the active change and current test file. If the access change has moved, rebase/adapt the visual tests instead of overwriting or reverting it.
5. The expense slice cannot be considered green until the entire `ExpenseControllerTest.php` passes, including the access-scoping tests. A visual failure must never be “fixed” by broadening the collection or bypassing the voter.
6. If both changes are simultaneously unmerged and cannot share one baseline cleanly, implementation pauses for sequencing rather than allowing parallel writers on the shared test file.

## Strict TDD Strategy

OpenSpec has `strict_tdd: true`. Implementation proceeds serially with RED, GREEN, TRIANGULATE, REFACTOR evidence recorded for each slice.

### Slice 1: Theme and shared workflow primitives

- **RED:** Add/extend DOM assertions in the three controller tests for workflow roots and shared state hooks before adding template classes. Confirm focused tests fail for missing selectors.
- **GREEN:** Add the minimum template hooks, `_workflow.scss`, import, and token contract required to satisfy markup assertions and compile Sass.
- **TRIANGULATE:** Exercise populated and empty collections, light and dark browser states, and at least one existing modernized non-finance consumer in each theme.
- **REFACTOR:** Consolidate repeated declarations into semantic tokens/shared workflow selectors; do not generalize beyond `.gp-workflow`.

Computed CSS, color contrast, and theme rendering have no current automated runner. Their explicit non-automated gate is `pnpm build` plus recorded browser evidence; this limitation must be documented rather than represented as an automated pass.

### Slice 2: Expense workflow

Write failing assertions first for:

- Expense page roots and structured empty state outside a table-only row.
- Designated list row attributes: existing conditional `data-href`, `data-gp-row-link`, `tabindex="0"`, `role="link"`, and non-empty accessible name.
- Persistent currency-preview status semantics and localized loading/unavailable data.
- Visible, uniquely associated approve/reject note labels.
- Preservation of submit/delete/charge/approve/reject form actions, POST methods, field names, and CSRF inputs.

Then implement the smallest Twig/JS/style changes. Triangulate draft versus approved row destinations, empty versus populated lists, preview available versus unavailable, stale-response suppression, creator/team/approver/admin visibility, and unauthorized 403/list exclusion. Refactor only after the entire existing `ExpenseControllerTest.php` is green.

### Slice 3: Quotation workflow

Write failing assertions first for:

- Quotation workflow roots, filter/action structure, statuses, and contextual empty state.
- Designated row-link attributes while preserving the native first-cell anchor.
- Persistent FX status semantics and localized loading/unavailable strings.
- Unchanged `data-quotation-*` selectors, ten-line controls, and form submission fields.
- Authenticated view structure while PDF/public URLs and behavior remain unchanged.

Triangulate empty and populated lines, create and saved edit modes, CLP and foreign currencies, available/unavailable/stale FX responses, and 360/768/1024 summary behavior. Existing save, notes, currency, date, line-edit, and PDF tests must remain green.

### Slice 4: Approval dashboard and keyboard behavior

Write failing assertions first for:

- Overall and per-domain workflow state/section hooks.
- Descriptively named native expense/invoice review links.
- Absence of expense/invoice inline decision forms.
- Presence of unchanged timesheet approve/reject form actions and CSRF inputs.

The emitted row-link DOM contract is automated through PHPUnit. Runtime delegated-key behavior has no supported automated runner, so the implementation must first record the failing browser observations, then verify after the minimal plugin change:

- Enter on a designated expense or quotation row navigates once.
- Space does not activate the row.
- Enter/click on nested links, buttons, form controls, labels, menus, and editable content does not activate the row.
- Legacy `.alternative-link` rows without `data-gp-row-link` do not become tab stops and receive no new keyboard behavior.

This is the explicit strict-TDD exception at an unavailable execution seam; no new dependency may be introduced to disguise the limitation.

### Required command gates

After each relevant slice:

```text
vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php
vendor/bin/phpunit tests/Controller/QuotationControllerTest.php
vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php
pnpm lint
pnpm build
composer linting
./phpstan.sh test
```

`./php-cs-fixer.sh core` and `./phpstan.sh core` are not expected because no PHP production file is in scope. Run them only if scope is explicitly expanded and approved.

## Browser Verification Matrix

Every targeted surface must be checked with realistic populated, empty, and applicable lookup-unavailable content.

| Width | Required checks |
| --- | --- |
| 360px | No page-level horizontal overflow; headers/actions wrap; primary actions remain visible; allocation and quotation-line controls are comfortably operable; dense tables scroll only inside their table wrapper; totals remain readable. |
| 768px | Expense metadata and allocations preserve order; quotation line editor/view precedes summary; summary is non-sticky and unobstructive; approval controls remain reachable. |
| 1024px or wider | Intended table density and local scrolling; quotation summary sticky behavior; action alignment; visible row/control focus rings. |

Repeat at each width in light and dark themes, keyboard-only, and with reduced motion enabled. Also verify lookup loading/success/unavailable transitions, nested row controls, quotation focus after adding a line, and all native approval submissions. Record browser/version and pass/fail evidence in verification artifacts.

## Single-Writer and Delivery Plan

- One implementation owner edits all application and test files serially. Parallel agents or sessions may review but must not write these files.
- The architecture supports vertical slices, but no chain strategy is selected here.
- The combined change is expected to exceed 400 changed lines. Under `ask-on-risk`, the tasks/apply transition must pause for a human delivery decision before producing a combined oversized diff.
- Each proposed review unit must be estimated independently against 400 lines. No `size:exception` is inferred.
- If chaining is approved later, slices remain ordered: theme/shared primitives, expense, quotation, approvals/keyboard, then bounded browser corrections. A later slice may depend on an earlier one, but all are implemented by the same writer.
- If chaining is not approved, scope must be reduced or another explicitly authorized delivery shape selected before application code changes begin.

## Risks and Mitigations

| Risk | Mitigation |
| --- | --- |
| Dark tokens regress modernized non-finance screens. | Keep names semantic/stable, map only consumed Tabler roles, build once per token change, and inspect known activity/project/dashboard consumers in both themes. |
| Workflow selectors leak globally. | Require `.gp-workflow` ancestry for all new shared selectors and quotation-root ancestry for domain rules. |
| Keyboard row behavior competes with nested actions. | Opt-in attribute, Enter-only semantics, comprehensive nested-control escape list, and browser checks for designated and legacy rows. |
| Async responses display stale values. | Invalidate prior requests on every fetch/reset and ignore non-current tokens. |
| Visual edits weaken authorization or CSRF. | Do not edit `src`; retain Twig guards, methods, action routes, field names, and token identifiers; rerun existing functional tests. |
| Mobile rules hide financial data. | Keep essential labels, amounts, statuses, destinations, totals, and actions; use local table scrolling rather than page overflow. |
| Translation additions drift. | Add English and Spanish units together and run XLIFF/Twig linting. |
| Review scope grows during visual correction. | Stop at 400-line risk, keep corrections within the owning vertical slice, and ask before selecting chain or exception behavior. |

## Rollout and Rollback

There is no migration, feature flag, data conversion, or backend rollout. Build and deploy the existing frontend bundle after tests and browser verification pass. Roll back by vertical slice:

1. Revert dark tokens/shared workflow import and styles.
2. Revert expense markup/state/plugin hooks and its tests.
3. Revert quotation markup/domain styles and its tests.
4. Revert approvals markup and opt-in keyboard listener.

Because controllers, routes, permissions, forms, and persistence remain unchanged, rollback restores the previous presentation without data repair. If only dark-theme values regress shared consumers, revert the token slice independently before rolling back workflow markup.

## Open Questions

No product or architecture question blocks the design. Delivery shape remains intentionally unresolved: the required `ask-on-risk` human gate occurs before implementation because the forecast exceeds the 400-line review budget.
