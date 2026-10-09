# Onboarding checklist widget

Branch: `feature/onboarding_checklist` (from `main` 9bc3b61). Goal: first step toward a professional SaaS feel. A dashboard widget "Getting started" shows real progress for a new account: create customer, project, activity, first timesheet, invite a colleague. Each step links to its screen, is checked from existing data, and the widget hides itself when all steps are done. No auth, permission, schema, dependency or infrastructure changes. No Docker.

Allowed edit surfaces: `src/Widget/Type/OnboardingChecklist.php`, `templates/widget/widget-onboardingchecklist.html.twig`, `tests/Widget/Type/OnboardingChecklistTest.php`, `src/Controller/DashboardController.php` (default widget list only), `translations/messages.en.xlf`.

## Tasks
- [x] O1 Test-first: write `OnboardingChecklistTest` (RED), then `OnboardingChecklist` widget returning ordered steps `{key, route, done}` using existing repository counts; permission-gated per step.
- [x] O2 Twig template using existing card/embed pattern; English translation keys; widget hidden when all steps done.
- [x] O3 Register in default dashboard list.
- [x] O4 Independent verification PASS: OnboardingChecklist 7 tests/39 assertions, TotalsCustomer 3/9, php -l, git diff --check, php-cs-fixer dry-run, XML well-formed with 9 unique ids. **Not run:** PHPStan (phpstan.neon:33 and :43 use protected var/cache), browser check, full suite. Known limits: the colleague count is not a lazy-loading N+1 on the normal path (UserRepository.php:165 loads the user with UserLoader batch hydration, UserLoader.php:69-87), but no DB profiling was done; five aggregate counts run on every dashboard load even once all steps are done; users with a saved custom dashboard do not get the widget automatically.
- [x] O5 Empty column fixed in templates/dashboard/index.html.twig (column only emitted when the widget renders content); independently re-verified: Twig parse, widget tests 7/39, diff check, cs-fixer. DashboardControllerTest not run (needs kernel, DB and var/cache). Committed and pushed to main with explicit user approval.
