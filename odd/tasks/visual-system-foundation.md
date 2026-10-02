# Visual system foundation

## Objective and authorization
User accepted systematic full-product migration beginning with foundation/global components. Local branch visual_system_foundation only; no commit/push/deployment authorized in this phase. Foundation provides a restrained premium enterprise vocabulary without changing routes, permissions, extensions or business behavior.

## Scope and tasks
- [x] V1: Map exact shared foundation and conflicts.
- [x] V2: Consolidate assets/sass/variables.scss, layout.scss and content.scss only.
- [x] V3: Independently compile/audit and browser-test a replacement candidate across representative routes/viewports/themes.
- [x] V4: Report foundation evidence, limits and next module slices.
No Twig, JS, controllers, modal, table, form, access or print files changed. Cumulative diff112 additions/178 deletions (290 authored lines), net deletion66.

## Implemented foundation
Semantic light/dark navigation, surface, line, ink, focus, radius, shadow, control and primary foreground roles mapped to Tabler. Hard-coded active navigation stripe removed; full active surface/foreground retained. Shared authenticated presentation scoped under .gp-app-shell. Page title/actions, controls, cards, nested-card no-shadow, semantic card header/footer, skip-link/focus and dashboard orientation unified. Tactile button active transform restored with reduced-motion override. Contradictory dashboard foundation/card hover rules removed from content; widget/grid/metric/chart specifics retained. Sidebar geometry/sidebar-hidden/JS contracts and detail exceptions preserved.

## Independent static evidence
Native review assessment unavailable package-local-binary-missing; independent verifier used. git diff --check clean; only3 authorized files. Full Sass compile succeeded with Sass1.101 via loadPaths node_modules/assets/sass and leading-tilde importer:872127 CSS bytes,220 loaded files,21 displayed deprecation messages; dependency/import warnings only. Candidate /tmp/gppro-visual-foundation-candidate.css SHA256 41171368af0dab6535a2b61be0f35c58080251969fc475c07e7d7cc363e4818b. Compiled cascade confirms button/reduced-motion order, nested no-shadow order, semantic card header/footer and shell nav specificity. Primary contrast: light10.99:1 default/12.61 hover-active; dark7.40/8.47. Status mappings unchanged.

## Browser replacement evidence
Parent disabled the sole deployed app stylesheet in-session and injected complete candidate CSS content fetched through Playwright request. This tests deletions rather than appending overrides. No server/data/source writes. Representative routes: dashboard,timesheet,customer detail,project detail,quotation create,system config. Desktop1440 light: all document widths <= viewport, nested elevated cards0. Mobile390 both light/dark: all6 routes <= viewport. Width992 four routes <= viewport. Dark desktop four routes <= viewport with nested elevated cards0. First keyboard Tab focuses skip link with2px solid focus+2px offset. Simulated html.sidebar-hidden places sidebar off-canvas left-240/right0, hidden, while reveal control remains flex and frame margin64px. Visual screenshots inspected dashboard light and system configuration dark.
Temporary candidate lacks webpack URL rewriting, causing only4 expected FontAwesome404s and placeholder glyphs; not treated as app defects. No additional candidate console errors.

## Remaining limits/debt
No Encore/public build generated. Actual deployed asset not validated because not published. Login/access and print were source-scoped but not visually rendered due authenticated session and asset-rewrite limitations. Modal behavior unchanged but only non-regression by scope/source, not interaction. Form background hook debt and dashboard/module-specific design remain for later slices. Sass reports dependency/import deprecations. Full accessibility audit not complete.

## Next module sequence
1. Shared page-action/card primitives already foundation-aligned; next build unified table/filter/list system.
2. Forms and remote modals.
3. Operational lists/time/calendar.
4. Relational details cleanup.
5. Financial workflows/approvals.
6. Dashboard/reporting/admin/access/print contextual variants.

## Delivery
Local foundation is verified as a candidate, not committed or pushed. Existing prototype/task/browser artifacts remain uncommitted. Await user instruction before publishing foundation or beginning the table/list slice.
