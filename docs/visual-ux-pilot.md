# Visual and UX Pilot

This pilot applies the approved integrated journey context to the shared
authenticated frame and the widget dashboard without changing business rules,
routes, permissions, or stored data.

## What changed

- Added a small `--gp-*` token layer in `assets/sass/variables.scss`, mapped to
  the existing Tabler variables and brand blue.
- Applied the tokens to the shared page frame, focus treatment, controls, and
  dashboard spacing in `assets/sass/layout.scss`.
- Added a skip link and stable main-content landmark in `templates/base.html.twig`.
- Added a restrained operations orientation band to
  `templates/dashboard/index.html.twig`. The dashboard still renders the
  existing widgets and empty state unchanged.
- Added English translation keys for the new orientation and accessibility copy.

The proposed `Instrument Sans`, `Satoshi`, and mono display fonts remain
proposals. The pilot uses a robust system sans-serif stack and adds no font
dependency or licensing requirement.

## Accessibility and contrast

The pilot uses the existing brand blue (`#1B3A6B`) for primary actions and
navigation. Body text uses `#223047` on `#F3F6FA` and supporting text uses
`#53647A` on light surfaces. Their calculated contrast ratios are 12.25:1 and
5.58:1 respectively, while brand blue on the pilot surface is 10.97:1. Verify
them again in the rendered theme if Tabler or a deployment theme changes the
computed colors. Status colors remain paired with existing labels and are not
used as the only meaning signal.

## Manual validation

1. Open the authenticated dashboard at 320px, 768px, and 1280px widths.
2. Confirm the orientation band wraps without horizontal overflow and the
   existing widget actions remain available without hover.
3. Confirm keyboard focus reaches the skip link, navigation, page actions, and
   widget actions. Activate the skip link and confirm focus reaches `#main-content`.
4. Test a user with restricted permissions and confirm menu visibility, route
   access, and widget data remain unchanged.
5. Test the empty-widget dashboard and confirm the existing empty state remains
   readable.

## Rollback

Revert the pilot-only changes to the following files:

- `assets/sass/variables.scss`
- `assets/sass/layout.scss`
- `templates/base.html.twig`
- `templates/dashboard/index.html.twig`
- `translations/messages.en.xlf`

Rollback does not require a migration, database change, route change, or
generated asset change. Do not edit `public/build` or `public/bundles` while
rolling back source changes.
