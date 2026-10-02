# Profile header visual hierarchy

## Objective
Continue the Gppro full-product visual migration with a CSS-only improvement to the existing user profile introduction/header. Keep the account identity and existing notification/email actions readable across desktop/mobile, without touching profile details, rates, permissions or scripts. Branch visual_profile_header starts at origin/main 73edeca.

## Allowed edit surfaces
- assets/sass/avatar.scss

## Tasks
- [x] P1: Map existing profile header hooks, actions and Sass import.
- [x] P2: Improve header spacing, identity hierarchy and action wrapping using `.box-user-profile` only.
- [x] P3: Compile safely to /tmp and independently verify fictional responsive LTR/RTL/reflow layout.

## Boundaries
No Twig/JS/controller/test changes. Preserve profile title, avatar, tooltip, notification/email links and their conditions; do not touch auth, teams, rates or other profile data. Browser proof on about:blank with fictional account details and inert action placeholders only. No live profile route, public/build, manual var/cache access, commit or push without explicit authorization.

## Evidence and status
Only assets/sass/avatar.scss changed (+45 lines), scoped under `.box-user-profile`. Identity title/subtitle wrap naturally; existing #profile-buttons links wrap at small widths without reordering. No Twig/JS/permission/rate/action changes; git diff --check passed.

Sass JS API compiled both existing entrypoints to literal /tmp with a tilde importer: LTR /tmp/gppro-visual-profile-header-ltr.css SHA256 fcb27348cb8b1b00e8e5473ea6c4f68e80a0e7dcc0416add4aad9564a4d6794e; RTL /tmp/gppro-visual-profile-header-rtl.css SHA256 0d07ba8c87f635af707bc111053976446c5957a8975fb4f50fec905f280f2863. Independent verifier matched hashes and checked 28 fictional about:blank viewport/direction/theme combinations (390/600/768/1440 and 200%-equivalent 195/320/720): no document/header overflow, visible ordered actions, long unbroken identity text bounded, title/subtitle/action contrast above 4.5:1. Owner-notification-first JS assumption preserved structurally; email-only case visible. FontAwesome/icons, JS and live rendered Twig not verified. Screenshots under /tmp/gppro-profile-header-*.png and automatic .playwright-mcp/page-2026-09-29T04-00-49-657Z.yml preserved.

Native review inspect blocked package-local-binary-missing, no lineage/mutation/approval. User explicitly authorized publishing this profile CSS slice to Git main. Exactly assets/sass/avatar.scss was committed as f5f105fdb842bada93922aa8ffbd14bbd54cac1a (`feat(ui): refine profile identity header`), normal push HEAD:main succeeded and git ls-remote confirmed that hash. Task docs and unrelated untracked artifacts excluded. Public deployment/live profile rendering remain unverified.
