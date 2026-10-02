# Locale reference table visual clarity

## Objective
Continue the full-product visual migration with the read-only `/help/locales` table. Improve scanability and bounded horizontal navigation for its many locale-format columns without altering content, row direction, column order, table preferences or behaviors. Branch visual_locale_reference starts at origin/main 0de006c.

## Allowed edit surfaces
- assets/sass/help.scss

## Tasks
- [x] L1: Map the help locale DataTable and existing scoped style import.
- [x] L2: Refine table typography, stable first-column orientation and local scrolling using the existing `.datatable_help_locales` hook.
- [x] L3: Safely compile LTR/RTL CSS to /tmp and independently check synthetic narrow/RTL/reflow behavior.

## Boundaries
Do not edit template, controller, tests, data, translations or shared DataTable behavior. Preserve 25 rows, 12 columns, per-cell `dir=rtl` and all values. No live `/help/locales` or real account data in browser; synthetic fixture only. Do not touch public/build or var/cache manually. No commit/push unless explicitly requested.

## Evidence and status
Only assets/sass/help.scss changed (+31 lines), using the existing page-specific DataTable wrapper. First three descriptive columns are addressed by actual column positions 1/2/5 (not nonexistent `.col_*` classes); no text-align override, so center/end utility alignment and per-cell RTL remain intact. The selector follows the actual `section.content > .gp-content-frame > .datatable_help_locales` structure, matching shared list specificity and appearing later in compiled CSS. `git diff --check` passed.

Sass JS API compiled entrypoints with a tilde importer to literal /tmp files: LTR /tmp/gppro-visual-locale-reference-ltr.css SHA256 9fb0c7be2591cf6ed56625b502a7b4586adb4a6a106df45bca0e3dcfcf23ae5c; RTL /tmp/gppro-visual-locale-reference-rtl.css SHA256 f234a73453965969622680294e2ced3ed3c81d3a05c3dfee057af4d825676fe8. Independent verifier initially found overridden typography due shared list selector specificity; corrected and rechecked. At 390px fictional about:blank table, headers/cells computed 10px 12px padding and 13px font, local table scrolling without document overflow in LTR/RTL, center/end alignment and hidden-column toggling retained. Earlier synthetic matrix included 390/600/768/1440 LTR/RTL light/dark and CSS zoom=2 with local scroll and no document overflow. Browser used compiled-rule excerpts, not complete CSS or live app. No real locale page visited; tooltip behavior not functionally tested.

Native review inspect blocked package-local-binary-missing; no lineage/mutation/approval. User explicitly authorized publishing this locale slice to main. Exactly assets/sass/help.scss was committed as 5beec3a5a12f006f87015597afebb64924450108 (`feat(ui): clarify locale reference table`), normal push HEAD:main succeeded and git ls-remote confirmed the hash. Task docs and unrelated untracked artifacts excluded. Public deployment and real application rendering remain unverified.
