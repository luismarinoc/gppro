# Quotation detail visual hierarchy

## Objective and authorization
Continue the user-approved full-product visual migration with quotation detail after System Configuration public asset verification. Improve read hierarchy for quotation identity, metadata, line items and totals without changing financial data/calculations, statuses, permissions, action availability, CSRF, routes, PDF/email/convert behavior or translations. Branch visual_quotation_detail starts atcb7d291 (release-only after system configuration811fd7b). Verified source published to main; public CSS deployment verified on the neutral reporting route. No production quotation route accessed.

## Delivery
Commitd982b8d3018e941d92d83202ffc8a33bbc7d81cb (`feat(ui): refine quotation detail hierarchy`) created and normally pushed HEAD:main; git ls-remote confirmed exact hash. Exactly3 intended files,134additions/20deletions; task docs/artifacts excluded. Committed-range assess unavailable/package-local-binary-missing with same completed independent fallback. Rollback boundary is quotation detail template/local Sass/test assertions only.

## Tasks
- [x] Q1: Map financial workflows and select quotation detail as smallest low-mutation slice.
- [x] Q2: Implement semantic detail hierarchy and scoped responsive styles, including totals 200% reflow correction.
- [x] Q3: Run focused tests/build/static checks and independent synthetic browser verification.
- [x] Q4: Publish exact verified slice and safely verify public assets/deployment markers. Source published; current public CSS contains the quotation detail rules.

## Scope and design
Edit only templates/quotation/view.html.twig, assets/sass/_quotation.scss and focused assertions in tests/Controller/QuotationControllerTest.php. Existing Sass import already active. No controllers/entities/repositories/forms/JS/translations/workflow shared styles.

Preserve outer gp-workflow quotation surface, title text/id, PDF target/rel, permission-gated edit link, draft send POST/action/CSRF id, accepted convert POST/action/CSRF id. Preserve every metadata field/value and conditional notes, original item iteration/order/description/amount/CLP money outputs, discount/surcharge/tax conditions/percentages and every server-derived CLP summary total.

Presentation approach: make the card an article labeled by its title; group definition-list pairs into a responsive metadata grid without adding copy; render status using existing quotation status pill hook; scope notes wrapping. Add detail-table description/numeric hooks with tabular/nowrap numeric alignment and safe horizontal scroll. Give totals a restrained contained summary treatment and strong final total, while retaining table semantics and exact money strings. No nested cards, gradients, new colors or synthetic headings/groups.

## Financial and browser safety
Quotation detail contains customer/project names, notes, line descriptions and monetary values. Never access real production quotation/list/PDF routes, never click/send/convert/edit, never request PDF/email/convert endpoints. Browser proof must use fully fictional DOM fixture on neutral /es/reporting/ or /ar/reporting/, with generic company/project/items and invented amounts, no copied production content/routes/tokens/forms. Server-render contracts come from isolated PHPUnit fixtures. Deployment check may inspect public CSS assets from neutral reporting pages; actual quotation template/data remains unverified unless a pre-proven nonproduction fixture is provided.

## Checks/process
Delegated direct, not SDD. Explorer mug3u0gy-n-pvw4 mapped financial family; quotation list/shared forms already migrated. One writer for three files then independent verifier. PRODUCT.md/DESIGN.md absent; established visual system is context. TDD mode unknown; ordinary checks, no strict-TDD claim.

Run baseline/candidate focused `vendor/bin/phpunit tests/Controller/QuotationControllerTest.php --testdox --colors=never` in existing test environment; git diff --check; PHP CS Fixer core dry-run/no-cache; phpstan test; Twig lint view; full LTR/RTL Sass compile to literal /tmp (never os.tmpdir). Tests must assert structural hooks and unchanged PDF/action methods/targets/CSRF/name/visible CLP content without weakening existing tests. Do not invoke PDF in browser; existing PHPUnit PDF test may run isolated harness. No manual var/cache, vendor/generated assets/plugins/data/log, dependency installs or broad fixes.

Native review assess fresh after writer; prior package binary unavailable, follow returned independent fallback. Delivery ask-on-risk, forecast under200 authored lines, one slice. Rollback boundary is quotation detail template/styles/test assertions only. Preserve unrelated artifacts.

## Implementation evidence
Writer mug3ypbm-o-ydq6 changed exactly template/local Sass/test,123additions/20deletions. Detail is article/title-labelled; metadata semantic grid with notes/status hooks; description/numeric cells; contained totals/final row. All value/calculation/conditional/action expressions preserved in source. Baseline PHPUnit16tests129assertions; final16tests150assertions. One intermediate code2 run had insufficient output; independent verifier must determine reproducibility. Final diff-check, CSFixer dry-run, phpstan test and Twig lint passed.

Full Sass /tmp/gppro-quotation-detail-20260924/ltr.css SHA256b6c7a786cfbbc48f81eb773ab43e420a4fdd6c2adbe9f8e9168c9c828d3ab23a (224sources); rtl.css SHA2561faeec1837ef5ba0340c3143ed9df3ee54e31c340ff5bc18a2143632f88702da (109sources),21 warnings each. Native assess unavailable/package-local-binary-missing, high-risk independent fallback required. Verifier mug47htt-p-ohrl owns source/action/calculation audit, focused test rerun, artifact hashes and synthetic fictional-data LTR/RTL browser fixture.

## Independent finding and correction scope
Verifier mug47htt-p-ohrl independently passed diff-check and PHPUnit16tests150assertions; writer intermediate code2 did not recur but cause remains unknown. Source/controller audit confirms financial expressions/order/conditionals/escaping and PDF/edit/send/convert POST/CSRF/permission contracts preserved. Artifact hashes matched; source counts not independently established. LSP unavailable.

Synthetic fictional-data fixture normal font passed390/600/768/992/1440 light/dark LTR/RTL: metadata, notes, status, wrapping, localized item-table scroll and totals bounded. At200% root font the new totals amount nowrap caused candidate-specific overflow: LTR390 document598 vs baseline375, cell beyond summary; RTL390 cell left-157; LTR600 also beyond summary. Browser restored neutral page; no financial route/action/PDF.

Correction writer mug4givx-q-oec6 changed local Sass only: summary max-width/min-width; fixed totals table;58/42 columns; cell overflow-wrap; amount nowrap removed, tabular/end alignment retained. diff-check, CSFixer, PHPUnit16tests150assertions and phpstan pass. New Sass: /tmp/gppro-quotation-detail-reflow-20260924/ltr.css SHA256ba928600b0892cfe49ca4bd758ab3c11bf19d0c85ab28568b94e5d31a6b6ac37 (224sources); rtl.css SHA2565964baf7a2200fb259e550702aa0b93667fe7ba3b755eb74f8c4afebd819246f (109sources),21warnings each.

Verifier mug4j2gr-r-7io0 matched hashes/rules and accepted synthetic reflow. At200%/390 LTR+RTL document fixture on/off375/375; summary/table/12cells contained, no scroll overflow. At600 on/off585/585, all bounds contained. Normal390/600/1440 light/dark contained. Browser restored neutral; no live financial route/data.

Fresh native inspect unavailable/package-local-binary-missing, no lineage/mutation/receipt. Origin/main fetch had no newer commit. Exactly3 files staged,134additions/20deletions; staged diff-check clean, artifacts/task docs excluded. Committed-range assessment after commit returned same unavailable/high fallback already completed.

Post-publication safe verifier muh312pz-s-1gf5 made one neutral /es/reporting/ GET only. It referenced old /build/app.144e528a.css GET200; new quotation metadata/summary/fixed58-42/wrapping/final/description/numeric rules absent. Deployment pending; stopped without polling or Arabic check. No quotation/list/PDF/edit/send/convert route or payload requested. Browser remains neutral light1440. Actual quotation template and server Git SHA unknown. After the user confirmed deployment and supplied the exact origin, independent verifier checked only https://gppro.tbema.net/es/reporting/ (HTTP 200). Its referenced https://gppro.tbema.net/build/app.b2686370.css returned HTTP 200 and contained the quotation metadata grid, fixed totals table with 58%/42% columns, wrapping, final-total, description and numeric rules. Browser generated .playwright-mcp/page-2026-09-26T22-06-42-768Z.yml, preserved and excluded from delivery. No quotation/financial route or action was accessed. This proves public CSS deployment only; deployed quotation template, live rendering and server revision remain unverified.
