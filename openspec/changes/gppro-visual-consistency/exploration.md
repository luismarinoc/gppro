# Exploration: GPPro visual consistency

## Decision context

**Change:** `gppro-visual-consistency`  
**Mode:** read-only application exploration  
**Research:** unselected, no external research performed

Newer finance workflows are functional but use mostly unmodified Tabler cards, tables, and forms, whereas modernized GPPro core screens use the `--gp-*` vocabulary. This change should apply that vocabulary consistently to employee expense/quotation flows and manager approval flows, including dark theme, state feedback, keyboard access, and narrow-screen behavior. It must not alter business rules, routes, authorization, CSRF, persistence, PDFs, or legally required Kimai attribution.

## Exploration method and constraints

- Read `openspec/config.yaml`, repository guidance, relevant Twig, SCSS, JavaScript, controllers, translations, and existing PHPUnit integration seams.
- `<repo>/.codegraph/` exists. The CodeGraph query/CLI surface was not exposed in this executor, so targeted filesystem reads were used as the fallback rather than broad discovery.
- No application files were changed during exploration.

## Existing visual system to reuse

| Pattern | Evidence | Reuse in this change |
| --- | --- | --- |
| GPPro token layer | `assets/sass/variables.scss` defines `--gp-bg`, surfaces, ink, brand, semantic colors, line, focus, radii, shadow, and maps key Tabler variables. | Complete the same contract inside dark theme rather than introduce a second theme or hard-code colors in workflow styles. |
| Shared authenticated shell and focus behavior | `templates/base.html.twig`, `assets/sass/layout.scss` | Keep `gp-app-shell`, `gp-content-frame`, skip link, `:focus-visible` outline, compact control radii, active-button feedback, and reduced-motion rule. |
| Modern list/table treatment | `assets/sass/activity-list.scss` | Reuse tokenized surface, uppercase muted table headers, comfortable cell padding, hover/focus treatment, responsive table/footer behavior for expense and quotation lists. |
| Modern workspace/detail treatment | `assets/sass/activity-details.scss`, `activity-edit.scss`, `project-details.scss`, `project-edit.scss` | Reuse one surface wrapper, section separation, tokenized form controls, focus rings, and `md` single-column collapse; do not create a competing finance component model. |
| Modern empty state | `templates/dashboard/index.html.twig` and `assets/sass/content.scss` (`gp-dashboard-empty`) | Extract/adapt a generic tokenized dashed-surface state rather than leave table-only messages or invent bespoke empty cards. |
| Responsive cards and tables | `templates/activity_workspace/embed_activities.html.twig`, `assets/sass/content.scss`, `assets/sass/activity-list.scss` | Keep `table-responsive`, progressively hide non-essential columns only where labels/links remain understandable, and use one-column layout below `md`/`lg`. |
| Server form/error and flash mechanisms | `templates/default/_form.html.twig`, `templates/base.html.twig`, `assets/js/plugins/GpproAlert.js` | Preserve Symfony form errors and flash toast behavior. Finance async hints should supplement, never replace, the server-authoritative validation/error path. |

## Confirmed gaps and implementation map

### 1. Modernize expense and approval screens

**Current state**

- `templates/expense/index.html.twig`, `pending.html.twig`, `edit.html.twig`, and `view.html.twig` are plain cards/tables. Detail metadata is paragraph text; the approval decision textarea has no visible label or shared form-control class.
- `templates/approvals_dashboard/index.html.twig` is three plain cards with table-only empty rows. It preserves important permission behavior: expense and invoice are navigation-only; timesheets retain existing inline POST controls.
- `templates/expense/view.html.twig` is the expense decision screen. `src/Controller/ExpenseController.php` already supplies all needed data and decision endpoints.

**Likely presentation files**

- Modify: `templates/expense/index.html.twig`, `templates/expense/pending.html.twig`, `templates/expense/edit.html.twig`, `templates/expense/view.html.twig`, `templates/approvals_dashboard/index.html.twig`
- Add or modify narrowly scoped styles via `assets/sass/_gppro.scss` plus a finance/workflow stylesheet (or extend the existing `assets/sass/_quotation.scss` only if the selector remains quotation-specific).
- Preserve: `src/Controller/ExpenseController.php`, `src/Controller/ApprovalsDashboardController.php`, approval services/voters, POST routes, and CSRF token names.

**Direction**

Use a shared workflow header/action grouping, tokenized metadata/status treatment, tokenized surface tables, and predictable `gap`/responsive action wrapping. Keep existing primary actions, destructive styles, and decision permissions unchanged. The approval dashboard must retain its domain-by-domain layout and must not add inline expense/invoice decisions.

### 2. Complete GPPro dark-theme tokens

**Current state**

- `templates/partials/head.html.twig` sets `data-bs-theme="dark"` for auto mode; stored dark preference is already supported by the existing theme integration.
- `assets/sass/variables.scss` defines GPPro colors only at `:root`; it currently special-cases only a dark navbar. `assets/sass/tabler-fixes.scss` contains limited dark-mode compatibility fixes.
- Modernized SCSS correctly uses `var(--gp-*, var(--tblr-*))`, but the root GPPro values remain light in dark mode. Hard-coded light values remain in navigation styles and must be audited against the completed tokens.

**Likely presentation files**

- Modify: `assets/sass/variables.scss`, possibly `assets/sass/tabler-fixes.scss`
- Validate consumers: `layout.scss`, `content.scss`, `activity-list.scss`, `activity-details.scss`, `activity-edit.scss`, `project-details.scss`, `project-edit.scss`, and the new finance styles.
- Do not change: user preference storage/configuration, theme subscriber/service, or generated CSS under `public/build/`.

**Direction**

Add a `[data-bs-theme='dark']` GPPro token set covering background, both surface levels, ink/muted ink, brand soft treatment, line, semantic colors, focus, shadow, and the Tabler mappings that workflow components consume. Retain the same semantic names so light/dark component styles are identical. Verify contrast for normal text, muted table headers, focus indicators, badges/statuses, outline buttons, and disabled controls.

### 3. Bring quotations into the GPPro visual system

**Current state**

- `templates/quotation/index.html.twig`, `edit.html.twig`, and `view.html.twig` use generic cards and tables.
- `assets/sass/_quotation.scss` has only sticky-summary and final-line rules despite being imported by `assets/sass/_gppro.scss`.
- Quotation edit already has strong behavioral primitives: semantic native buttons, focus after an added line, a `data-quotation-lines-empty` state, maximum-line disabling, client summary calculation, and server-authoritative save.
- The public acceptance/recorded pages and quotation PDF are separate document/public surfaces. The PDF has explicit document branding and is not part of application chrome.

**Likely presentation files**

- Modify: `templates/quotation/index.html.twig`, `templates/quotation/edit.html.twig`, `templates/quotation/view.html.twig`, `assets/sass/_quotation.scss`
- Potential translation additions: `translations/messages.en.xlf` and `translations/messages.es.xlf` only for genuinely new visible state/assistive text.
- Explicitly exclude: `templates/quotation/pdf.html.twig`, `templates/quotation/public_response.html.twig`, `templates/quotation/public_response_confirm.html.twig`, quotation pricing/conversion services, and controller business logic.

**Direction**

Make list filtering/actions, editable line items, sticky summary, detail metadata, line/total tables, status treatment, and small-screen stacking use the same GPPro surface/typography/spacing system as modernized core screens. Preserve inline JavaScript calculations and selectors unless a visual-state need requires a small, fully backward-compatible addition.

### 4. Standardize empty, loading, and error states

**Current state**

- Empty states are inconsistent: expense/quotation/pending/approval tables render a lone `colspan` text row; dashboard uses `gp-dashboard-empty`; quotation edit uses a text-muted inline message.
- `templates/default/_form.html.twig` and modal templates expose `data-loading-text`, while `GpproAjaxModalForm` handles only AJAX modal submit state. There is no shared loading primitive for expense currency preview or quotation FX lookup.
- The expense preview (`templates/expense/edit.html.twig`) hides itself on fetch failure; quotation edit (`templates/quotation/edit.html.twig`) hides the CLP row when rate lookup fails. Neither announces pending/error status to assistive technology. Server form errors and flash alerts already exist and remain authoritative.

**Likely presentation files**

- Modify: finance/approval Twig files above and their inline fetch-state markup/JS.
- Add a shared, tokenized state stylesheet imported from `assets/sass/_gppro.scss`; use semantic HTML and `role="status"`/`aria-live` only for dynamic informational status.
- Potential translation additions in both English and Spanish catalogs for loading/unavailable/retry guidance. Do not add strings if existing translated text conveys the exact state.

**Direction**

Define one visual state vocabulary: contextual empty state, inline loading skeleton or textual progress that preserves layout, and actionable inline error. Use it only in finance/approval scope for this change. Do not replace blocking server errors with client-only messages, and do not introduce polling, animation-heavy effects, or a new frontend library.

### 5. Keyboard-accessible row navigation and approval controls

**Current state**

- Expense and quotation list rows use `.alternative-link` with `data-href`. `assets/js/plugins/GpproAlternativeLinks.js` delegates clicks through `GpproReducedClickHandler`, which listens only for `click`; rows are not focusable and have no keyboard operation.
- Quotation's first cell also contains a normal link, but expense's primary navigation is row-only. `templates/activity_workspace/embed_activities.html.twig` and admin list templates use the same shared class, so changing the plugin has a broad blast radius.
- Review links on expense/invoice approval rows are native anchors; timesheet approve/reject actions are native POST buttons and already keyboard-operable. The expense detail decision controls need labelled note fields and focus-visible styling, not a custom keyboard handler.

**Likely presentation files**

- Modify shared behavior: `assets/js/plugins/GpproAlternativeLinks.js` (and `GpproReducedClickHandler.js` only if a reusable key handler belongs there).
- Modify focused workflow rows: `templates/expense/index.html.twig`, `templates/quotation/index.html.twig`; consider a deliberately scoped class/attribute rather than changing every legacy `.alternative-link` row at once.
- Modify approval/decision markup: `templates/expense/view.html.twig`, `templates/approvals_dashboard/index.html.twig` only as needed for labels, descriptive text, control grouping, and clear focus order.
- Add browser-level regression coverage if the project gains a browser harness; PHPUnit cannot execute delegated keyboard behavior.

**Direction and risk control**

Prefer native anchors/buttons wherever possible. If whole-row navigation must remain, give only designated rows an explicit focusable/link contract (`tabindex`, link role/name, Enter activation; Space behavior only when it does not contradict link semantics) and ensure clicks/keys originating in nested links, buttons, inputs, labels, or action menus never navigate the row. Do not place invalid nested interactive controls inside a faux link. Preserve the existing conditional edit-versus-view destination and native form submission/CSRF semantics.

### 6. Responsive verification for expenses, quotations, and approvals

**Current state**

- All list surfaces have `table-responsive`, but header actions, dense quotation line views, `w-auto ms-auto` totals, multi-column expense allocations, sticky quotation summary, and approval action cells have not been given workflow-specific mobile rules.
- Existing modernized screens consistently collapse to a single column under Bootstrap `md`/`lg`, reduce table padding at `sm`, and avoid horizontal layout calculations.

**Required viewport checks**

- **360px:** expense list/pending/detail/edit, quotation list/create-edit/view, approval dashboard. No page-level horizontal overflow; controls remain at least comfortably tappable; action groups wrap without hiding the primary action.
- **768px:** quotation line editor/view and summary preserve a coherent reading/editing order; summary is no longer obstructive when the two-column layout collapses; metadata and totals remain readable.
- **1024px+:** list density, sticky quotation summary, approval action placement, focus rings, and horizontal table scrolling remain intentional.
- Execute each check in light and dark themes, with keyboard-only traversal, and with reduced motion enabled.

## Scope boundaries

### In scope

1. Authenticated application UI for expense list/pending/create-edit/view and its decision controls.
2. Authenticated quotation list/create-edit/view, excluding PDF/public response pages.
3. Authenticated approvals dashboard and its existing review/inline-timesheet controls.
4. Shared GPPro tokens and narrowly shared row-navigation behavior only where needed to realize the approved gaps.
5. Finance/approval empty, loading, and error presentation, accessible status messaging, and responsive behavior.
6. English and Spanish translations for net-new user-visible content.

### Out of scope

- Backend/business-rule, controller, voter, policy, service, entity, schema, migration, route, permission, and CSRF changes.
- Workflow redesign: approval eligibility and the existing dashboard rule remain intact (expense/invoice navigate to their domain screens; only timesheet has dashboard inline decisions).
- New frontend framework, third-party library, PDF redesign, public quotation response redesign, generated assets, vendor, plugins, runtime data/cache/logs.
- Removal or alteration of legally required Kimai attribution.
- A repository-wide retrofit of every legacy `.alternative-link` row unless a follow-up explicitly broadens the shared keyboard contract.

## Risks and mitigations

| Risk | Why it matters | Mitigation |
| --- | --- | --- |
| Dark token changes regress non-finance screens | `--gp-*` is shared by already-modernized core screens. | Keep token names stable, use semantic overrides only, inspect all known token consumers in both themes, and isolate workflow selectors. |
| Faux row links create invalid or surprising interaction | Rows contain nested actions/links, and click delegation currently bypasses them. | Prefer native anchors; otherwise scope keyboard behavior, maintain nested-control escape rules, test mouse and keyboard paths, and retain distinct edit/view URLs. |
| Approval controls accidentally alter authorization/write behavior | The screens expose sensitive approve/reject POST endpoints. | Presentation-only templates; retain `is_granted`, methods, routes, CSRF tokens, and existing navigation-only expense/invoice dashboard rule. Ask before any expansion into approval logic. |
| Client lookup feedback contradicts server state | FX/currency previews are advisory while server validation/calculation is authoritative. | Treat loading/failure as non-authoritative hints; retain existing save/validation and never infer amounts or statuses client-side. |
| Responsive styling hides necessary finance data | Tables contain amounts, statuses, approvals, and action controls. | Hide/deprioritize only redundant columns, keep an explicit review link/action, keep horizontal table fallback where necessary, and test 360/768/1024 viewports. |
| Review budget is exceeded | This spans tokens, SCSS, Twig, inline JS, translations, and tests. | Use vertical slices below; pause for delivery selection if an estimate grows beyond 400 changed lines. |

## Test seams and validation plan

### Existing automated seams

- `tests/Controller/ExpenseControllerTest.php`: already asserts pending-list content and the conditional `data-href` edit/view row destination.
- `tests/Controller/QuotationControllerTest.php`: already asserts creation/edit DOM selectors, client-line primitives, and a clickable list row.
- `tests/Controller/ApprovalsDashboardControllerTest.php`: asserts aggregate/empty/localized states, authorization filtering, navigation-only expense/invoice behavior, and existing timesheet POST forms/CSRF.
- `tests/EventSubscriber/ThemeOptionsSubscriberTest.php` and profile/theme tests: confirm dark-theme preference plumbing; this change should not need to modify them.

### Needed regression additions

- Update controller DOM assertions for stable workflow hooks/classes, labelled decision fields, native review controls, and empty-state containers where templates change. Preserve current authorization and route assertions.
- Add a focused JavaScript test only if a project-supported JS test runner is introduced or already available at implementation time. `package.json` currently supplies ESLint/build but no JS test script.
- Validate translations with `composer linting` if catalogs change.

### Manual/browser validation required

PHPUnit/DOM crawler cannot validate computed CSS, dark contrast, breakpoints, focus visibility, delegated key events, async loading/error announcements, or sticky positioning. A browser pass must cover the viewport/theme/keyboard matrix above, including nested row actions, Enter navigation, native button submit, focus return after quotation line creation, fetch unavailable states, and reduced motion.

### Expected commands after implementation

- `pnpm lint`
- `pnpm build`
- `vendor/bin/phpunit tests/Controller/ExpenseControllerTest.php`
- `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php`
- `vendor/bin/phpunit tests/Controller/ApprovalsDashboardControllerTest.php`
- `composer linting` when Twig/XLIFF changes
- `./php-cs-fixer.sh core` only if PHP changes occur (not expected)
- `./phpstan.sh core` only if `src/` changes occur (not expected)

## Likely review slices (400-line budget)

| Slice | Scope | Estimated changed lines | Gate |
| --- | --- | ---: | --- |
| 1. Theme foundation and state primitives | Dark `--gp-*`/Tabler mappings; shared workflow/empty/loading/error SCSS import; no workflow logic. | 120–180 | Keep token visual regression review focused. |
| 2. Expense workflow | Expense list/pending/edit/view markup and scoped styles; labelled decision controls; empty and async preview states; DOM tests. | 220–340 | Do not modify service/controller/authorization. |
| 3. Quotations | Quotation list/edit/view and `_quotation.scss`; line empty/loading/error feedback; responsive summary; DOM tests/translations. | 280–380 | Preserve client selectors and server-authoritative calculation. |
| 4. Approval dashboard and navigation accessibility | Approval dashboard presentation and review controls; scoped row keyboard contract in JS/templates; DOM tests plus browser validation. | 200–320 | Preserve expense/invoice navigation-only and timesheet POST exception. |
| 5. Cross-slice validation/follow-up | Browser matrix fixes and translation consistency. | ≤150 if needed | If it cannot remain a small corrective slice, pause under `ask-on-risk`. |

A combined implementation is likely to exceed 400 lines. Under the selected `ask-on-risk` strategy, implementation must pause for a human delivery decision once concrete estimates or the first slice show a larger combined diff; do not infer a chain strategy or size exception.

## Recommended next phase

Proceed to proposal/spec/design with the following non-negotiable acceptance boundaries:

1. All targeted authenticated finance/approval surfaces consume the GPPro token vocabulary in both light and dark themes.
2. No backend rule, route, CSRF token, authorization decision, database schema, PDF, or public quotation page changes.
3. Empty/loading/error states have consistent visual structure and accessible dynamic announcements without replacing server validation.
4. Row navigation has an explicit, tested keyboard contract that does not steal interaction from nested actions.
5. Native approval/review controls remain keyboard reachable; expense/invoice dashboard rows remain navigation-only and timesheet keeps its existing protected POST controls.
6. The 360/768/1024 light/dark, keyboard, and reduced-motion browser matrix passes.
