# Unified forms and remote modals

## Objective and authorization
Continue the user-approved full-product visual migration after table/list publication. Branch visual_forms_modals. Modernize shared form surfaces and remote modal chrome without changing routes, permissions, CSRF, validation, access filtering, field order, submit semantics or Ajax lifecycle. No commit/push authorized yet.

## Tasks
- [x] T1: Map form themes, remote lifecycle, security invariants, advanced controls and workflow exceptions.
- [x] T2: Consolidate customer/project/activity/timesheet edit surfaces into shared Sass while retaining entity layout rules.
- [x] T3: Modernize shared states, native files, accordion surfaces, Tom Select and modal chrome.
- [x] T4: Validate compilation/contracts and browser-test full-page/remote flows at390/600/1440 light/dark without business writes.
- [x] T5: Independently verify and report; no commit/push until explicit approval.

## Fixed contracts preserved
Remote forms still use .modal-ajax-form, X-Requested-With Gppro-Modal, gppro_context.modalRequest, #form_modal .modal-content, #remote_form_modal replacement, plugin activation/destruction, dirty state infrastructure, data-form-event and existing error selectors. No Twig,JS,PHP,controller/form-type/security changes. Tom Select retains dropdownParent body,z-index1056,min-width behavior,optgroup indent. Quotation/expense remain full-page because their DOMContentLoaded document-global code is not modal-safe. Uploads remain full-page.

## Source result
Nine Sass files: new _form-surface.scss plus _gppro.scss,customer/project/activity/timesheet edit,forms.scss,modal.scss,selectpicker.scss. Shared edit roots now own surfaces,labels,native controls,checkboxes,focus,metafields,responsive/modal compaction and reduced-motion transition behavior. Entity files contain only flat/address/invoice/extended/time exceptions. Actual vendor collapsible DOM (.card.border-0>.card-body.p-0>.accordion) receives item/button/body/focus/corner styles. Root errors before entity roots and native file controls in page/modal DOM are covered. Modal uses semantic surface/border/shadow and responsive header/body/footer; logical-end4.5rem clearance protects absolute close control. Tom Select wrapper/dropdown match native semantic surfaces with focus/disabled/active states.

## Verification evidence
Independent review found and drove fixes for title-close overlap, wrong legacy card-header selector and modal file selector. Final nine-file source review: no P1/P2; git diff-check clean. Full final Sass candidate `/tmp/gppro-forms-modals-candidate-v6.css`:869090 bytes,221 loaded files,21 warning callbacks,SHA256 e99f1368401d3b5e6c09836e988ca1ddd9def7f6ecc7673fb0fadf7b5c4ca5b6. Older candidate files remain frozen and untouched. Native review unavailable package-local-binary-missing; independent verifier used.

## Browser evidence
Complete stylesheet replacement, not override. Full-page routes customer/project/timesheet/user/quotation/expense at390/1440 light/dark: no overflow; specialized roots present on customer/project/timesheet; Tom Select widgets present. Customer/project/activity/timesheet at600 light/dark: no overflow and all expected roots/sections. Remote modals customer/project/timesheet/user/invoice edit opened through real Ajax handler at390/1440 light/dark using existing hidden/visible triggers: form,save control and widgets present; no document overflow. DOM-only long Spanish title never overlapped close: at390 title right294/close left310 (invoice309/325); at1440 16px clearance. Mobile footer buttons measured164x40 and153x40. Accordion real DOM expanded, border/radius/surface applied and overflow remained visible. Invoice document upload actual native file input matched shared style at390. Tom Select final v6 wrapper background/border exactly equalled native controls in light (252,252,253 /220,224,230) and dark (32,43,64 /57,71,93); dropdown same surface, active option branded,z-index1056,no overflow/double visible border. DOM-only disabled state verified in light/dark: wrapper and control backgrounds/colors matched muted semantic state exactly.

## Limits
No form submitted; no CSRF,permission,profile,upload,invoice or destructive mutation executed. Existing dirty-close warning did not fire from Playwright fill+Escape/close in the observed timesheet create modal; source behavior is unchanged, so this is recorded as an existing lifecycle/test-method uncertainty, not candidate regression. RTL was not rendered; logical padding is RTL-aware, but physical close alignment depends on rtlcss build. Firefox/WebKit were not available in current Playwright session. Warning text was suppressed; count remained21 as prior candidates. Candidate replacement generates expected font asset404 placeholders because it is served standalone without webpack URL rewriting.

## Next
Present verified local phase. Await explicit approval before staging,commit,push. Subsequent lifecycle phase may extract quotation/expense initializers into idempotent form-root modules before any remote-modal migration.
