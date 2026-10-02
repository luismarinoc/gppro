# Unified table, filter and list system

## Objective and authorization
Continue the user-approved full-product design migration after deployed foundation validation. Local branch visual_table_system. No commit/push/deployment authorized in this phase. Unify shared list presentation without changing routes, permissions, profiles, filters, batch actions or JavaScript behavior.

## Tasks and scope
- [x] T1: Map shared DataTable and manual workflow contracts/exceptions.
- [x] T2: Implement dual shared contracts and delete page baseline duplication.
- [x] T3: Independently compile/audit, parse Twig with Symfony form_theme support, and browser-test full replacement CSS plus DOM-only toolbar hook.
- [x] T4: Report result and next forms/modal slice.
Changed8 files: assets/sass/tables.scss,_workflow.scss,_quotation.scss,customer-list.scss,project-list.scss,timesheet-list.scss,user-list.scss and templates/macros/datatables.html.twig. Diff159 additions/484 deletions, net-325; deletion-heavy authored total643 due consolidation. Macro adds only gp-datatable-toolbar to existing btn-list. No controllers, JS, routes, permissions, shared profiles or generated assets changed.

## Implemented contracts
DataTable contract scoped to list data_table under authenticated content: consistent headers,density,focus,row/checked states,badges,action wrapping,footer/pagination,toolbar and empty alerts. Checked rows use full2px inset brand outline, preserving recording/overlap warning background/text. Page list Sass retains only domain semantics (names/comments, timesheet recording/overlap/summary/description/actions, user identity/avatar/roles/email). Workflow contract scopes manual quotation/expense/invoice-history/approval list tables, without adding batch/sort/profile behavior. Quotation/expense row/status duplication consolidated; quotation status text now matches expense using normal ink for readability. Invoice archive scroll exception and detail/preview/reporting/embed exclusions preserved. :has enhancements progressively fall back without functional loss.

## Static and independent evidence
Native review unavailable package-local-binary-missing; independent verifier used. git diff-check clean; exactly8 paths. Full Sass compile via tilde importer:220 stylesheets,864791 bytes final candidate,21 displayed deprecation warnings. Final candidate /tmp/gppro-table-system-candidate-v2.css SHA256 e75fb3ae1c011d43ecb954e1586c4047c48bfe6722e33c4e6f414a6badb7add8. Source comparison found no lost IDs,forms,filters,profile/batch selectors,reload events,sorting,pagination,sticky/context-menu hooks or visibility overrides. No global min-width/scroll introduced; unsupported :has loses styling only. Standalone Twig parser initially lacked Symfony FormTheme parser; exact retry with Symfony Bridge FormThemeTokenParser passed templates/macros/datatables.html.twig syntax. No kernel/cache/DB.

## Browser replacement evidence
Parent disabled sole deployed app CSS and injected complete candidate; added exact gp-datatable-toolbar class DOM-only because deployed macro lacks unpublished hook. No server/data/source writes. Matrix8 routes (timesheet,customer/project/user lists,quotation,expense,invoice history,approvals) at390/1440 light/dark: all document widths <= viewport. Table/dropdown/pagination presence recorded. First/last row dropdowns opened for customer,project,user,invoice; menus shown. Timesheet row dropdown,sticky header,pagination present. Project column modal opened/closed at390 without saving and no overflow. Project GET search with nonsense term produced0 rows and1 empty alert; no write. Checkbox selection client-only, no submit. Toolbar visible/mobile search remains visible.
Initial selected fill obscured semantic backgrounds and was corrected. Final v2 DOM-only test forcibly applied real overlapping class to row then checked checkbox: light background remained rgb247,215,215 and red text; dark background remained43,11,11 and light-red text; only2px brand inset outline changed. Both doc widths1440. Candidate standalone CSS caused only expected FontAwesome asset404 placeholders due no webpack URL rewriting.

## Limits
No actual AJAX filter/pagination navigation styling after response replacement was exercised end-to-end, though section.content/classes/contracts are unchanged and full GET empty search loaded styled candidate after injection. Approvals route had no tables in current data state. No business POST/batch/profile save/export executed. Print/report/detail tables intentionally excluded. Full PHPUnit/build not run due DB reset/protected/generated constraints. Browser candidate is strong presentation evidence but not deployed proof.

## Next sequence
Next slice: shared forms and remote modals, followed by operational time/calendar lists and relational detail cleanup. Local table system is verified candidate only; await user instruction before commit/push.
