# Reporting landing visual refresh

## Objective and completed tasks
User approved the Reports landing migration, preserving reports, exports, permissions, extensions and data. Branch visual_reporting_landing starts atba95ea9; standing authorization covered verified publication.

- [x] R1: Map route, dynamic collection, hooks and tests.
- [x] R2: Audit authenticated390/600/1440 light/dark baseline.
- [x] R3: Implement semantic navigation, explicit list role and tests.
- [x] R4: Independently verify static/test evidence and browser fixtures.
- [x] R5: Publish and verify actual deployed behavior in Spanish and Arabic.

## Delivery and contracts
Commit0c7b66f57af09488c949513c068f0480e06a6fbd, `feat(ui): streamline reporting navigation`, normal push HEAD:main; exact git ls-remote confirmation. Four files,88additions/17deletions: templates/reporting/index.html.twig, assets/sass/_reporting.scss, assets/sass/_gppro.scss, tests/Controller/ReportingControllerTest.php. Task documents/artifacts excluded. No controller/service/event/security/export/detail/plugin/dependency/global-token changes. Rollback is this reporting slice only.

Dynamic insertion order, report.route, label/domain, reportIcon/colorize, page_actions and base preserved. No synthetic groups or hardcoded IDs/counts. Semantic ul[role=list]/li navigation retains row-cards/card-link hooks, one link per item, decorative icons aria-hidden; scoped1column below992/2above, wrapping and visible focus. Explicit role and controller assertion address markerless-list semantics risk.

## Automated evidence
Writer muepi2pn-a-39o9/correction muepz1tv-c-fk64: PHPUnit baseline4tests61assertions; candidate4tests107assertions. git diff --check, staged check, PHP CS Fixer core dry-run/no-cache, ./phpstan.sh test and reporting Twig lint passed. Repeated after role correction. Parent readback/spot checks passed; independent muepoeek-b-dopy reran diff-check/PHPUnit4tests107assertions and hashes. LSP unavailable; PHP8.5/minimum8.2 warning noted.

Full raw Sass /tmp/gppro-reporting-candidate-XXXXXX/ltr.css SHA256bc1d6cf42006a2a5651f12b93559dfcd94b3af0a87ddb2655df98e93293bf586 (223sources); rtl.css SHA25624f59145b4449ee5512af788a92eeb3c3abb9710e80b6df33ccbab852166d842 (108sources). Writer reported zero warnings. Raw RTL external Tabler imports are not standalone bundled-integration proof; role correction left Sass unchanged.

## Candidate browser evidence
Verifier mueq1v78-d-evmr accepted R3/R4 without severe finding. Candidate-shaped DOM from11actual anchors preserved hrefs/labels/icons/order; exact compiled fragment over deployed bundled base. AX list11items/text-only names,1/1/2columns390/600/1440 light/dark, no overflow, first/last focus, sequential Tab/Shift+Tab, long/unbroken labels and3-item/empty fixtures passed.200% root-font reflow passed, not native zoom. Arabic RTL fixture passed with deployed RTL dependencies. Shared subtle hover shadow optional, not blocker. Fixtures restored; no screenshots or Safari/VoiceOver. This was fixture proof, not production proof.

## Actual production verification
First bounded check mufnrbrc-e-djzj still saw legacy cards/app.e8866eff.css and correctly reported deployment pending, without polling. User requested another check; mufyg3db-f-8cd0 completed actual deployment verification.

Fresh authenticated /es/reporting/ GET200: server-rendered ul[role=list],11li/links,11aria-hidden icons, zero legacy cards. Bundled app.c9cca071.css GET200 contains reporting rules. Actual Spanish hrefs/labels/icons/order matched baseline; AX list11items and label-only names. Real390/600/1440 light/DOM-dark1/1/2columns, row-major desktop, no overflow/clipping,2px first/last focus and sequential Tab/Shift+Tab. Solid/regular FontAwesome WOFF2 GET200 and glyphs resolved; zero console errors.

Safe /ar/reporting/ GET200 rendered actual lang=ar/dir=rtl with11accessible entries and app-rtl.75420878.css GET200/reporting rules.390/1440 RTL1/2columns, right-to-left row-major, no overflow,2px focus and Arabic label-only AX names passed. Restored actual/es/reporting/ Spanish/light1440 without fixtures. No candidate CSS/DOM injection in deployment checks.

Limits: site did not expose exact server Git SHA; verified deployed behavior, not server commit identity. Native browser zoom, Safari/VoiceOver and screenshot/visual-image assessment were not performed.

## Consent, safety and process
Initial safe GET auto-created .playwright-mcp/page-2026-09-23T23-06-47-161Z.yml and verifier paused. User explicitly authorized automatic snapshots/logs ONLY in.playwright-mcp/, preserved/excluded from commits. Screenshot to/tmp was denied; no screenshot or policy bypass. Final permitted snapshots: page-2026-09-24T19-59-09-390Z.yml, page-2026-09-24T20-01-05-683Z.yml, page-2026-09-24T20-01-39-794Z.yml. Prior diagnostics retained too; no cleanup authorized.

Only safe landing GET, no report execution/exports/downloads/forms/preferences/data writes. No process kills, lock deletion, manual var/cache manipulation, vendor/generated-build/plugin edits or installs. Normal isolated test harness only; no cache removal. Build outputs literal/tmp, never os.tmpdir. Unrelated files preserved.

Delegated direct, not SDD; TDD mode unknown, ordinary checks without strict-TDD claim. One105-line slice, ask-on-risk. Native inspect/ambient/committed assess unavailable/package-local-binary-missing; no lineage/mutation/approval/receipt. Required independent fallback completed; no installation or policy bypass. All scoped work complete, no pending deployment action.
