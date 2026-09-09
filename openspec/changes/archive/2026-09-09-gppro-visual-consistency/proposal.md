# Proposal: GPPro Visual Consistency

## Intent

Bring the authenticated expense, quotation, and approval workflows into GPPro's established visual system. These newer finance surfaces are functional, but they still rely mainly on generic Tabler cards, tables, and forms while modernized core screens use the shared `--gp-*` token vocabulary, structured workspace patterns, responsive layouts, and consistent interaction feedback.

The change should make finance work feel like one coherent GPPro product for employees creating expenses or quotations and managers reviewing or approving them. It must provide complete dark-theme styling, standardized empty/loading/error states, accessible keyboard interactions, and usable narrow-screen layouts without changing business behavior.

## Problem Statement

Visual and interaction inconsistency currently creates avoidable friction in high-attention financial workflows:

- Expense, quotation, and approval screens do not consistently use GPPro's modern surface, spacing, typography, status, and form patterns.
- GPPro tokens remain light-themed under the dark Bootstrap theme, so modernized screens and newer finance screens do not have a complete dark presentation contract.
- Empty states are mostly table rows, while currency and exchange-rate lookups can fail silently and do not announce progress or failure to assistive technology.
- Clickable expense and quotation rows are not keyboard operable, and expense decision notes lack complete labelling and shared control styling.
- Dense finance layouts have not been deliberately adapted or verified at phone and tablet widths.

These gaps make the product harder to scan, less predictable to operate, and less accessible, particularly for mobile and keyboard users.

## Confirmed Product Decisions

1. All six gaps identified in `exploration.md` are in scope.
2. The target users are employees creating expenses and quotations, and managers reviewing or approving them, including mobile and keyboard users.
3. Existing GPPro and Tabler primitives must be reused. This change must not introduce a competing component system or frontend framework.
4. Expense and invoice entries on the approvals dashboard remain navigation-only. Existing timesheet inline approval and rejection POST controls remain unchanged in behavior.
5. Server validation, persisted calculations, permissions, routes, HTTP methods, and CSRF protections remain authoritative and unchanged.
6. Quotation PDFs and public quotation response pages remain separate document/public surfaces and are not redesigned.
7. Legally required Kimai attribution remains present and unchanged.

## Scope

### In Scope

#### 1. Expense and approval presentation

- Modernize authenticated expense list, pending, create/edit, detail, and decision surfaces.
- Modernize the approvals dashboard while preserving its domain-by-domain organization and current action model.
- Apply shared workflow headers, action grouping, metadata, status, table, form, and responsive surface patterns.
- Add visible labels, descriptive context, predictable focus order, and focus-visible treatment to expense decision controls.

#### 2. Complete dark-theme tokens

- Add a semantic `[data-bs-theme='dark']` GPPro token set for backgrounds, two surface levels, text, muted text, brand treatment, borders, focus, shadows, and semantic states.
- Keep existing `--gp-*` names stable and update only the necessary Tabler mappings.
- Validate existing GPPro token consumers so the shared token change does not regress modernized non-finance screens.

#### 3. Quotation presentation

- Modernize authenticated quotation list, create/edit, and detail surfaces.
- Bring filters, action groups, editable line items, metadata, status treatment, line and total tables, and the sticky summary into the GPPro visual vocabulary.
- Preserve existing quotation selectors, native controls, line limits, focus behavior, client-side previews, and server-authoritative save/calculation behavior.

#### 4. Standardized system states

- Establish a shared, tokenized finance/workflow vocabulary for contextual empty, loading, and inline error states.
- Add non-disruptive progress and unavailable feedback for expense currency preview and quotation exchange-rate lookup.
- Use semantic markup and appropriately scoped `role="status"` or `aria-live` announcements for dynamic informational state.
- Keep Symfony form errors and flash messages as the authoritative blocking error path.

#### 5. Keyboard-accessible navigation and controls

- Prefer native anchors and buttons for navigation and actions.
- Where whole-row navigation remains necessary, apply an explicit contract only to designated finance rows: focusability, an accessible link name, Enter activation, visible focus, and protection for nested interactive controls.
- Preserve conditional expense edit/view destinations and all existing approval form semantics.
- Avoid a repository-wide retrofit of legacy `.alternative-link` behavior.

#### 6. Responsive finance workflows

- Adapt workflow headers, action groups, tables, allocations, quotation lines/totals, sticky summaries, and approval controls for narrow screens.
- Validate expense, quotation, and approval surfaces at 360px, 768px, and 1024px or wider.
- Validate each target viewport in light and dark themes, with keyboard-only traversal and reduced motion enabled.

### Non-Goals

- Backend business-rule, controller, service, voter, policy, entity, repository, schema, migration, route, permission, persistence, or CSRF changes.
- Redesigning approval eligibility or workflow lifecycle rules.
- Adding inline expense or invoice decisions to the approvals dashboard.
- Changing quotation pricing, conversion, or persisted calculation behavior.
- Introducing a new frontend framework, third-party UI dependency, polling, or decorative animation system.
- Redesigning quotation PDFs, public response pages, or public acceptance/recorded pages.
- Retrofitting every legacy `.alternative-link` row across the repository.
- Modifying generated assets in `public/build/`, vendor content, plugins, or runtime-managed files.
- Removing or altering legally required Kimai attribution.

## Capabilities

### New Capabilities

- `finance-workflow-visual-states`: A consistent presentation contract for empty, loading, and non-authoritative inline error feedback across the targeted authenticated finance workflows.

### Modified Capabilities

- `expense-allocation`: Expense list, pending, edit, detail, and decision surfaces gain GPPro styling, responsive behavior, accessible state feedback, and keyboard-safe navigation without changing expense rules.
- `quotation-management`: Authenticated quotation list, edit, and detail surfaces gain GPPro styling, responsive behavior, and accessible lookup state feedback without changing quotation behavior.
- `approval-workflows`: The approvals dashboard gains consistent GPPro presentation and accessible controls while retaining its current permission and action model.
- `gppro-visual-system`: The existing semantic token contract gains complete dark-theme values and shared workflow state presentation.

Capability names that do not yet exist as baseline specs should be reconciled during the spec phase rather than creating duplicate capability ownership.

## Approach

Extend the visual system already proven by GPPro's modernized activity, project, dashboard, and authenticated shell surfaces. Component styles will consume stable semantic tokens so light and dark themes share the same selectors. Finance-specific layout rules will remain narrowly scoped, while genuinely reusable workflow and state primitives will be imported through the existing GPPro Sass entry point.

Templates will retain their existing routes, permission checks, forms, CSRF token names, and behavioral selectors. Dynamic currency and exchange-rate feedback will describe advisory client state without masking server validation or inventing substitute values. Row interaction will favor native links, with narrowly scoped keyboard behavior only where whole-row navigation is retained.

Responsive work will preserve essential financial data and primary actions. Redundant columns may be deprioritized only when labels, destinations, amounts, statuses, and decision controls remain understandable and reachable. Horizontal table scrolling remains an acceptable fallback for irreducibly dense data, but page-level overflow does not.

## Affected Areas

| Area | Impact | Description |
| --- | --- | --- |
| `assets/sass/variables.scss` | Modified | Complete semantic GPPro dark-theme tokens and necessary Tabler mappings. |
| `assets/sass/_gppro.scss` and scoped workflow/state Sass | Modified or added | Import shared workflow/state presentation without creating a second design system. |
| `assets/sass/_quotation.scss` | Modified | Tokenized quotation line, summary, detail, and responsive treatment. |
| Existing GPPro Sass consumers | Validated, narrowly adjusted if required | Confirm shared dark tokens remain coherent across modernized core screens. |
| `templates/expense/*.html.twig` | Modified | Modern expense layout, system states, labels, actions, and responsive hooks. |
| `templates/quotation/index.html.twig`, `edit.html.twig`, `view.html.twig` | Modified | Modern quotation surfaces and accessible lookup feedback. |
| `templates/approvals_dashboard/index.html.twig` | Modified | Consistent sections, empty states, review controls, and responsive actions. |
| `assets/js/plugins/GpproAlternativeLinks.js` | Narrowly modified if needed | Scoped keyboard contract for designated finance rows while ignoring nested controls. |
| Finance template inline JavaScript | Narrowly modified | Loading and unavailable announcements without altering authoritative calculations. |
| `translations/messages.en.xlf`, `translations/messages.es.xlf` | Modified only if needed | Net-new visible or assistive loading/error/state text. |
| Focused controller tests | Modified | Stable DOM hooks, labelled fields, empty states, routes, permissions, and form behavior. |
| Manual browser validation | Required | Theme, viewport, keyboard, focus, async state, sticky behavior, and reduced-motion checks. |

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
| --- | --- | --- | --- |
| Shared dark-token changes regress modernized non-finance screens | Medium | High | Keep semantic names stable, avoid component-specific token meanings, and inspect known consumers in both themes. |
| Keyboard-enabled rows create invalid or surprising nested interactions | Medium | High | Prefer native links, scope row behavior, ignore events from nested controls, use Enter for link semantics, and preserve explicit destinations. |
| Presentation edits alter protected approval behavior | Low | High | Retain permission checks, POST methods, routes, CSRF names, and the navigation-only expense/invoice dashboard rule; reject any logic expansion. |
| Client lookup feedback conflicts with saved values | Medium | Medium | Describe previews as advisory, never invent fallback amounts, and preserve server validation and calculation as authoritative. |
| Responsive rules hide essential financial information or actions | Medium | High | Deprioritize only redundant data, retain explicit review/actions, and verify all target widths with real content and empty/error states. |
| New state text is incomplete across supported languages | Low | Medium | Reuse exact existing translations where possible; add English and Spanish entries together when new text is necessary. |
| Combined implementation exceeds the 400-line review budget | High | Medium | Preserve vertical-slice boundaries and defer the required delivery decision to the tasks phase under `ask-on-risk`; do not infer chaining or a size exception. |

## Rollback Plan

Revert the Twig, Sass, JavaScript, translation, and focused test changes by vertical slice. Because this proposal excludes schema, persistence, route, permission, and backend behavior changes, rollback requires no migration or data repair. The existing generic Tabler presentation and current click-only row behavior will be restored. If shared dark tokens cause regressions, the token slice can be reverted independently while preserving later workflow markup only after confirming it still has safe Tabler fallbacks.

## Dependencies

- Existing GPPro semantic tokens and authenticated shell patterns.
- Existing Bootstrap 5 and Tabler components.
- Existing activity/project list and detail patterns, dashboard empty state, Symfony form errors, and flash alerts.
- Existing expense, quotation, and approval routes, permission checks, native forms, CSRF protection, and client selectors.
- English and Spanish translation catalogs when net-new user-facing text is required.

No new package or external research dependency is required.

## Delivery and Review Boundary

The combined work is expected to exceed the canonical 400 changed-line review budget. The proposal preserves these vertical slices for later planning:

1. Dark-theme foundation and shared workflow/state primitives.
2. Expense workflow presentation and regression coverage.
3. Quotation workflow presentation and regression coverage.
4. Approval dashboard and scoped row-navigation accessibility.
5. Cross-slice browser validation and bounded corrections.

The tasks phase must estimate concrete changes and trigger the configured `ask-on-risk` gate before selecting a chained or oversized delivery shape. No chain strategy or `size:exception` is approved by this proposal.

## Success Criteria

- [ ] Authenticated expense list, pending, create/edit, detail, and decision surfaces use the established GPPro visual vocabulary in light and dark themes.
- [ ] Authenticated quotation list, create/edit, and detail surfaces use the same vocabulary, including line items, totals, statuses, and summary behavior.
- [ ] The approvals dashboard uses consistent GPPro sections and states while expense/invoice remain navigation-only and timesheet retains its protected inline POST controls.
- [ ] The dark theme defines coherent semantic values for background, surfaces, text, muted text, brand treatment, borders, focus, shadow, semantic statuses, outline buttons, and disabled controls.
- [ ] Empty states are contextual and consistently structured across the targeted finance and approval surfaces.
- [ ] Expense currency preview and quotation exchange-rate lookup expose understandable loading and unavailable states to sighted and assistive-technology users.
- [ ] Client-side state feedback supplements rather than replaces Symfony form errors, flash messages, persisted calculations, and server validation.
- [ ] Designated row navigation is keyboard operable, visibly focused, correctly named, and never steals activation from nested links, buttons, inputs, labels, or menus.
- [ ] Expense decision fields have visible labels and all existing approval/rejection controls remain reachable and operable by keyboard.
- [ ] At 360px there is no page-level horizontal overflow, primary actions remain visible, and controls remain comfortably operable.
- [ ] At 768px quotation editing/viewing order and summary behavior remain coherent and unobstructed.
- [ ] At 1024px or wider, list density, sticky summary behavior, approval actions, focus rings, and intentional table scrolling remain correct.
- [ ] The target viewport matrix passes in light and dark themes, with keyboard-only traversal and reduced motion enabled.
- [ ] Existing routes, permissions, HTTP methods, CSRF token names, business rules, persisted calculations, PDFs, public quotation pages, and Kimai attribution remain unchanged.
- [ ] Frontend lint/build, focused controller tests, and Twig/XLIFF linting pass where applicable, with manual browser evidence recorded for behavior that PHPUnit cannot verify.
