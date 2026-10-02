# Approvals dashboard visual hierarchy

## Objective
Continue full-product visual migration with a CSS-only treatment of the existing approvals dashboard sections. Improve scanning across expense, invoice and timesheet queues without editing authorization, forms, values, routes, translations or template markup. Branch visual_approvals_hierarchy starts at origin/main 2ee39d1.

## Allowed edit surfaces
- assets/sass/_workflow.scss

## Tasks
- [x] A1: Map the three approval domains, existing section/table hooks and security boundary.
- [x] A2: Apply narrowly scoped CSS hierarchy and responsive table containment to existing hooks.
- [x] A3: Compile CSS safely to /tmp, independently check fictional-data responsive/RTL behavior and diff.

## Boundaries
No edits to controllers, templates, tests, voters or permissions. Preserve all existing actions, forms, CSRF, list values and conditionals verbatim. No live approvals page or user data in browser. Do not generate public/build or manually touch var/cache. No commit/push unless explicitly requested. Public deployment cannot be inferred from synthetic evidence.

## Evidence and delivery status
Only assets/sass/_workflow.scss changed (+50 lines). Three queue sections now have lighter surfaces/headers and separators; existing amount and duration columns align at the end while the timesheet customer column stays start-aligned; table overflow stays local. No markup, actions, forms or authorization changed. git diff --check passed.

Sass JS API compiled both existing entrypoints with a tilde importer to literal /tmp paths: LTR /tmp/gppro-visual-approvals-ltr.css SHA256 032c19f7b792ab9840d795270b624002185288c8462ec8b028d7209adf90352e; RTL /tmp/gppro-visual-approvals-rtl.css SHA256 fe0c8bb87f5c274910e94b7460df3f1e1650590150db4d327c22258211fc11b8. Independent verifier matched hashes and synthetic fictional-data proof on about:blank across 390/600/768/1440 and 200%-equivalent 195/384/720, LTR/RTL and light/dark (28 combinations): zero document horizontal overflow, local table scroll, visible empty states and correct numeric/text alignment. RTL local scroll checked at 390. Initial exploratory Playwright snippets failed before successful corrected checks. Screenshot .playwright-mcp/page-2026-09-28T20-01-39-348Z.png preserved and excluded. Full Tabler/live deployed page not verified.

Native review inspect blocked package-local-binary-missing; no lineage, mutation or approval. User explicitly authorized publishing this CSS slice to main. Exactly assets/sass/_workflow.scss was committed as 84fff790c24ee40fa7930ade78862df3c1c1b029 (`feat(ui): clarify approval queue hierarchy`), normal push HEAD:main succeeded and git ls-remote confirmed that hash. Task docs and untracked artifacts were excluded. Public deployment remains unverified; untracked artifacts preserved.
