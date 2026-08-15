# Design: Team Lead Navbar Indicator

**Artifact file**: `openspec/changes/team-lead-navbar-indicator/design.md` (hybrid store)

## Technical Approach

One new Twig file overrides the vendor navbar user block: byte-verbatim copy of
`vendor/kevinpapst/tabler-bundle/templates/includes/navbar_user.html.twig` plus two
additions, both gated by `{% set isTeamlead = user.isTeamlead is defined and user.isTeamlead %}`:
1. Text badge at `xl+`, inside the existing `d-none d-xl-block` block next to `user.name`.
2. Avatar corner dot below `xl` (and always when condensed user menu is on), via the Tabler
   avatar macro's native `badge` option.
Zero PHP/entity/migration/CSS changes. Stable test hook: `data-teamlead-indicator`.

## Architecture Decisions

| # | Decision | Rejected | Rationale |
|---|---|---|---|
| A1 | Avatar dot via macro `badge` option `macro.avatar({..., badge: {...}})` | Manual `position-relative` wrapper + hand-written `badge badge-sm` | Verified `avatar.html.twig:89-92` renders the badge inside the avatar element; compiled CSS already ships `.avatar{...position:relative...}`, `.avatar .badge{position:absolute;bottom:0;right:0;border-radius:100rem}`, `.avatar-sm .badge:empty{height:.5rem;width:.5rem}`. Native slot exists. |
| A2 | Dot is an EMPTY badge, `color: 'blue'`, with `title`/`aria-label` | Badge with a FA icon | `:empty` is the only rule sizing the dot to 0.5rem; content breaks it and overflows a `sm` avatar. `badge.html.twig:39-43` also emits plain `bg-blue` (no `-fg`) only when content is empty. |
| A3 | Hook = `data-teamlead-indicator` with values `"text"` / `"avatar"` | Assert on `badge bg-blue`, `d-xl-none`, or the label | Utility classes/i18n are styling concerns; proposal requires a restyle-robust marker. Two values let tests target each branch. |
| A4 | Text badge hand-written `<span>`; only the dot uses a macro | `widgets.badge_type()` (no `attr` param, `widgets.html.twig:258-261`), or Tabler `badge` macro (`attr_to_html` emits a double space -> brittle exact-byte asserts) | Hand-written span emits identical classes `badge bg-blue text-blue-fg`, preserving app convention, with byte-exact output for `assertStringContainsString`. |
| A5 | Dot responsive class conditional on `tabler_bundle.isCondensedUserMenu()` | Hardcode `d-xl-none` | With `user_menu_condensed: true` the whole name block vanishes, so a hardcoded `d-xl-none` leaves a teamlead with NO indicator at `xl+`. Config is currently `false` (`config/packages/tabler.yaml:16`) but must not be silently depended on. |
| A6 | Guard computed once at top of `{% if user is not null %}` | Repeat inline twice | Twig resolves `isTeamlead` to `User::isTeamlead()` (`src/Entity/User.php:676`) via the `is`-prefix method map. Keeps the vendor diff to 3 contiguous hunks. |

## Data Flow

    tabler_user() -> UserDetailsEvent <- UserDetailsSubscriber::setUser(App\Entity\User)  [unmodified]
        -> navbar_user.html.twig (override)
             -> user.isTeamlead -> User::isTeamlead() -> iterate TeamMember::isTeamlead()
                  -> true: avatar badge dot  [data-teamlead-indicator="avatar"]
                  -> true: text badge        [data-teamlead-indicator="text"]

Read-only. No writes, no permission evaluation, no new service.

## File Changes

| File | Action | Description |
|---|---|---|
| `templates/bundles/TablerBundle/includes/navbar_user.html.twig` | Create | Vendor copy + indicator |
| `tests/Controller/LayoutControllerTest.php` | Modify | 3 RED tests + private `makeTeamlead()` helper |

## Exact template content

See `templates/bundles/TablerBundle/includes/navbar_user.html.twig` for the applied, verified
version (matches this design 1:1; the only runtime-confirmed deviation is documented in
apply-progress: the fixture title used in the regression test is "Head of Development", not
"Head of Sales" as originally assumed here — see Testing Strategy note below).

## Rendered contract (test-facing)

```html
<span class="badge bg-blue text-blue-fg ms-1" data-teamlead-indicator="text">Teamlead</span>
<span class="badge bg-blue d-xl-none"  data-teamlead-indicator="avatar" title="Teamlead" aria-label="Teamlead"></span>
```
Non-teamlead: the substring `data-teamlead-indicator` must not appear at all.
Responsive hiding is CSS-only, so both markers are always in the HTML for a teamlead.
`user.title` keeps its own `<div>` untouched (D4).
Note: `attr_to_html` renders a double space before `data-teamlead-indicator="avatar"` — confirmed
at apply time, matches Tabler's `utils.html.twig` output exactly.

## Testing Strategy (RED first)

| Layer | What | Approach |
|---|---|---|
| Functional | Present for per-team teamlead | `getClientForAuthenticatedUser(ROLE_USER)`, then persist `Team` + `TeamMember::setTeamlead(true)` + `$em->refresh($user)` (pattern `ApprovalsDashboardControllerTest.php:176-186`), request `/dashboard/`, assert `data-teamlead-indicator="text">Teamlead</span>` and `data-teamlead-indicator="avatar"` |
| Functional | Absent for plain user | ROLE_USER, no membership, `assertStringNotContainsString('data-teamlead-indicator', ...)` |
| Functional | Global ROLE_TEAMLEAD without membership NOT flagged | `tony_teamlead` has the role but no `TeamMember` in `UserFixtures`; assert marker absent. Locks Success Criterion 3 (D1 is membership-based) |
| Regression | `user.title` still renders | `tony_teamlead` (`ROLE_TEAMLEAD` fixture username) has a title set; assert present. **Deviation found at apply time**: the title used by the test database bootstrap (`src/Command/ResetTestCommand.php`) is "Head of Development", not "Head of Sales" as `UserFixtures.php` alone would suggest — `bin/console`/`vendor/bin/phpunit` reseed via `ResetTestCommand`, which defines its own literal title for `tony_teamlead`. Test asserts "Head of Development" to match actual runtime behavior. |

File `tests/Controller/LayoutControllerTest.php`, `#[Group('integration')]`,
extends `AbstractControllerBaseTestCase`, imports `App\Entity\Team` + `App\Entity\TeamMember`.
Order matters: authenticate first, create membership second, request third — security reloads
the `User` from DB on each request.
Do NOT assert on bare `Teamlead` (can appear elsewhere on the page).

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or
process-integration boundary. Display-only; output is HTML-escaped (`e('html_attr')` in
`attr_to_html`, auto-escaping for the inline span) and derived from a boolean plus an
existing translation key.

## Migration / Rollout

No migration. One commit. Rollback = delete the template; Symfony falls back to the vendor
version (clear `var/cache` in prod as for any template change).

## Open Questions

None. D1-D6 closed; avatar badge slot, `:empty` dot sizing, condensed-menu interaction,
`attr_to_html` byte layout, and fixture teamlead state all verified against the codebase.
