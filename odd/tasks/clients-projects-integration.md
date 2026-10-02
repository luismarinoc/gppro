# Clients and projects premium UI integration

## Intent and authorization
User approved the isolated prototype and authorized local integration into real Clients and Projects. Branch prototype_clients_projects. No commit, push, deployment, database reset or security behavior changes authorized.

## Scope
Six changed files: templates/customer/details.html.twig, templates/project/details.html.twig, assets/sass/customer-details.scss, assets/sass/project-details.scss, assets/sass/customer-list.scss, assets/sass/project-list.scss. Existing shell, controllers, permissions, DataTables and plugins preserved. No fake prototype data introduced.

## Tasks
- [x] I1: Implement local relational-table styling and continuous main/metadata rail layout for four screens; correct empty rail/plugin gaps and long team-name overflow.
- [ ] I2 (blocked: isolated application harness unavailable): Complete real unpublished application rendering and regression checks. Safe syntax/compilation/fixture checks passed, but not equivalent to full application validation.
- [x] I3: Report local implementation and explicit readiness limitations. No production readiness claim.

## Routing and constraints
Delegated one writer and independent verifier; parent reviewed Twig diff and macro-scope concern. TDD not established; ordinary checks only. Protected cache/vendor/generated assets and production unchanged. Standard formatter/console/PHPUnit were not run because of out-of-scope mutations, protected cache and database reset risks. No Encore output into public/build. Forecast300–500 authored lines exceeded; final observed372 additions/506 deletions. No commits or PR requested; delivery strategy remains ask-on-risk for future delivery.

## Observed evidence
Independent final verification: git diff --check passed; all four in-memory Sass compilations passed (5137/4879/3992/4047 CSS characters); both Twig templates parsed using php -n direct Twig loader, cache disabled and extension stubs (syntax only). Prior isolated fixture reproduced empty rail defect; corrected rails always contain real metadata and plugin wrappers require nonempty registered boxes. Post-fix fixture at1000px desktop content width had336px rail/307px team table with18 avatars wrapping across6 rows, no overflow;390px no overflow. Existing responsive member hiding preserved. Continuous main/rail layout matches prototype structure. Permission predicates, fields, IDs, actions, routes, render calls, plugin loop order, budgets/rates/milestones/reload events preserved by source comparison. Outer widgets imports confirmed in both templates. No additional concrete defect found in focused review.

## Remaining limits
Actual application render, full CSS cascade, dropdown behavior, keyboard accessibility, dark-theme contrast and visual fidelity remain unverified. Plugins registered but returning empty HTML can still occupy space. Full controller regressions not run. Prototype preview is not proof of integrated pages.

## Next step
Prepare or obtain a safe isolated unpublished application environment for final browser and regression validation, without production data/reset/deployment. Do not publish or describe implementation as production-ready until this is resolved. User need not supply design expertise; only environment authority if setup extends existing scope.

## I2 follow-up (2026-10-02)
Validated read-only on the deployed QA site (gppro.tbema.net): customer/project list and detail at 1633px, 1280px and 390px, dark theme forced via DOM attribute. No horizontal overflow on mobile. Defects found and fixed locally (uncommitted): hardcoded English "View quotation history" in customer details now uses `quotation.view_history|trans` (en/es); es "Pais" corrected to "País"; project budget table overlapped its progress bars at ~1280px, fixed by stacking `.col-md-6` columns below xxl in project-details.scss. Still unverified: the SCSS fix rendered (requires a build/deploy), keyboard accessibility, dropdown behavior, controller tests.
