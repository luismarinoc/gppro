# Expense detail visual hierarchy

## Objective
Continue the approved full-product visual migration with the read-only expense detail surface. Clarify the amount, metadata and allocation table hierarchy without changing financial values, permissions, forms, actions, routes, CSRF, or translations. Branch: visual_expense_detail.

## Allowed edit surfaces
- templates/expense/view.html.twig
- assets/sass/_workflow.scss
- tests/Controller/ExpenseControllerTest.php

## Tasks
- [x] E1: Map existing expense detail contracts and choose bounded presentation scope.
- [x] E2: Implement semantic detail hierarchy and responsive scoped styling with focused regression assertions.
- [x] E3: Run focused tests, style/static checks and independent synthetic responsive verification. All safely available local and synthetic checks passed; live expense page remains intentionally untested.

## Boundaries
Preserve every existing Twig value/expression and conditional; leave edit/submit/delete/approve/reject/charge forms and permission gates unchanged. Scope CSS to a new detail-only hook so expense create/edit/list screens are unaffected. Avoid extra cards and hardcoded copy. Do not browse actual production expense routes or financial data; browser layout checks use fictional data only. No generated assets, vendor, cache, plugin, or data changes. This feature document and automatic browser artifacts stay outside source delivery.

## Checks and evidence
Writer changed only the three allowed surfaces: view-only hook, amount hierarchy, unboxed detail metadata and numeric allocation columns; draft/approval/charge forms, data expressions and gates unchanged. Independent verifier found two new-test fixture assumptions (null allocation amount and conditional approval progress); both corrected. Focused full ExpenseControllerTest.php passes: 38 tests, 278 assertions. Twig lint, phpstan test, PHP CS Fixer core dry-run/no-cache and git diff --check pass. PHP CS Fixer ran under PHP 8.5.10 while project minimum is 8.2.

Plain Sass CLI builds initially failed on Webpack-style tilde imports; Encore would clean/write forbidden public/build. Parent then used the installed Sass JS API with a tilde-to-node_modules importer to compile both existing entrypoints to literal /tmp paths, without source/generated asset writes: LTR /tmp/gppro-visual-expense-detail-ltr.css (884558 bytes, SHA256 30b108502ea4f06be2bfbfdcd02fb6f0d9c461469f4ab539eb2a99), RTL /tmp/gppro-visual-expense-detail-rtl.css (255857 bytes, SHA256 55bd31c220abe18b8bccfabd7f036ff898460a6a5c116fc751826fe33daf633f). Independent verifier used fictional data on about:blank with compiled candidate CSS: 390/600/768/1440 px LTR/RTL light/dark (16 combinations), no document/metadata/amount overflow; allocation table alone scrolls at 390/600 and both directions remain reachable. At 200%-equivalent widths, no document overflow; table-only scrolling through 640 px. RTL used LTR CSS plus RTL overlay due external imports in standalone RTL entrypoint, not full production proof. Automatic screenshots preserved under .playwright-mcp/ (2026-09-26T22-40-56-544Z, 22-41-25-021Z, 22-41-32-187Z). No real expense route was accessed. Native review inspect blocked (package-local-binary-missing), no lineage or mutation; no native approval claimed. User explicitly requested publication to main after being told review was unavailable. Exactly the three intended source files were committed as 156a73379ea89c315a30816282f11a2159618dc3 (`feat(ui): refine expense detail hierarchy`), after rebasing over release-only origin/main 5cccba2. Normal push HEAD:main succeeded and git ls-remote confirmed the same hash. Task document, untracked browser artifacts and generated files were excluded. Public asset deployment and production expense template/forms/data remain unverified.
