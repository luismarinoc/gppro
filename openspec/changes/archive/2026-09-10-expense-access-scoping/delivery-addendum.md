# Delivery Addendum: Expense Access Scoping

## Purpose and publication boundary

This post-archive note records delivery evidence gathered on 2026-09-12. It does not replace the historical `pass_with_warnings` verdict, command outputs, counts, hashes, or archive-time statements in [the verification report](./verify-report.md) and [the archive report](./archive-report.md).

At authoring time, the implementation was merged into `main`, while the archive move and canonical specification update still awaited Git publication. This documentation state is not an implementation verification failure.

## Delivered implementation

[PR #144](https://github.com/luismarinoc/gppro/pull/144) was merged as `4a1d5eaf3823c37867cb404883985ecf0b9f0157`. It includes the allocation-less expense-list correction and regression, a test-formatting correction, and the dependency remediation integrated from [PR #145](https://github.com/luismarinoc/gppro/pull/145).

Access enforcement remains intentionally split:

- `ExpenseVoter` checks detail visibility.
- `ExpenseRepository::findForListing()` checks creator and team-based list visibility; team-based matching requires an existing joined project.
- `ExpenseController::index()` adds eligible pending approvers for unfiltered and pending-approval lists, then de-duplicates and sorts the result.
- `ExpenseApprovalPolicy::canApprove()` remains authoritative for conditional approver eligibility. This note adds no permission rules.

## Post-merge CI evidence

All seven workflows and ten checks completed successfully on the merge commit above. These are later executions, not replacements for the historical archive evidence.

| Check | Observed result |
| --- | --- |
| [PHP integration](https://github.com/luismarinoc/gppro/actions/runs/34694756875) | PHP 8.2, 8.3, 8.4 and 8.5 each passed 4,787 full-suite tests and MySQL migration round trips. |
| [PHP lint and security](https://github.com/luismarinoc/gppro/actions/runs/34694756747) | Application/test PHPStan, style, validation, linting and security checks passed. |
| [Frontend](https://github.com/luismarinoc/gppro/actions/runs/34694756750) | Node 24.20.0 / pnpm 10.15.1 frozen installation, production build and project audit passed; no known project vulnerabilities reported. |

The automatic version-only descendant `485fd9667bfa8883e04b8d7c902367f191f1dd98` set version `2.62.165`. Its `[skip ci]` commit had no independent CI run. Non-failing build, release-API, abandoned-package and setup-installer warnings remain separate maintenance items.

## Live evidence and coverage limits

Authenticated, read-only checks at 12:55–12:56 UTC on 2026-09-12 passed for expense details at 320, 390, 768 and 1024 pixels, and expense lists, invoices and calendar at 390 and 1024 pixels. The site served `/build/app.df52a9a4.css`, replacing the previously observed asset. No styles were injected and no business records were created or changed.

This proves sampled page health and a frontend update, not the exact deployed backend revision or exhaustive production authorization behavior. The ten exposed expenses all had allocations accessible to the available teamlead; they did not provide an outsider or allocation-less production case.

The dedicated `testFindForListingExcludesAllocationLessExpenseForStranger` regression exercises repository filtering for an unrelated user without teams. Allocation-less variants for a user with teams, the creator and an administrator were not added. Passing this change does not close the separate historical scope failure in `gppro-codebase-analysis`.

## Subsequent maintenance delivery

[PR #146](https://github.com/luismarinoc/gppro/pull/146) merged into `main` as `067e5958c5279ae84f59b2af8f4ed6668400d67d` on 2026-09-12 at 18:48:57 UTC. It updated the pnpm toolchain and included the separately reviewed PDF test-reader correction from [PR #147](https://github.com/luismarinoc/gppro/pull/147). Neither expense-access rules nor production PDF sanitization changed in this follow-up.

- **Fresh CI:** seven workflows and ten checks succeeded on that exact merge. [PHP integration](https://github.com/luismarinoc/gppro/actions/runs/34712327663) passed 4,805 tests per PHP version 8.2–8.5 and migration round trips; [application/test PHPStan, lint and security checks](https://github.com/luismarinoc/gppro/actions/runs/34712327875) also passed.
- **Toolchain:** [frontend verification](https://github.com/luismarinoc/gppro/actions/runs/34712327636) confirmed Node 24.20.0, selected pnpm 10.34.4, frozen installation and production build. The bootstrap audit reported zero vulnerabilities; the separate project audit reported no known vulnerabilities.
- **Version-only descendant:** the bot then created `93acb75a7ce5b3adef3a319dd0850f52a6ec1deb`, changing only `src/Constants.php` to version `2.62.166` / ID `26366`. Its `[skip ci]` commit had no independent CI execution.

These later results do not revise historical verification outcomes, prove the cause of the earlier PHP 8.5 test failure, or establish the deployed backend revision.

The legacy pnpm layout warning, 63 Sass/build warnings, abandoned `doctrine/cache`, release API 404, PHP deprecations and Release Drafter configuration warnings remain separate maintenance items.
