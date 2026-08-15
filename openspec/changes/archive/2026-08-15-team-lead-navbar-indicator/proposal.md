# Proposal: Team Lead Navbar Indicator

## What

Display-only navbar indicator showing that the logged-in user is teamlead of at least one team.

## Why

The PO reported the same suspected bug twice in one session — testuser1 and testuser3 saw
Approve/Reject buttons on their own timesheet entries. Both times behavior was correct: they are
genuine team leads via the per-team flag `team_membership.teamlead` (Administration > Teams,
checkbox next to the member), which is separate from the global `ROLE_TEAMLEAD` shown in the
profile. Nothing in the top bar signals this today. PO delegated the visual design explicitly.

## Where

- New: `templates/bundles/TablerBundle/includes/navbar_user.html.twig` (override of
  `vendor/kevinpapst/tabler-bundle/templates/includes/navbar_user.html.twig`)
- Tests: `tests/Controller/LayoutControllerTest.php`
- Read-only deps: `src/Entity/User.php:676` (`isTeamlead()`),
  `src/EventSubscriber/UserDetailsSubscriber.php`, `translations/messages.{en,es}.xlf`

## Locked decisions

- D1 source of truth = `User::isTeamlead()` (true if teamlead of ANY membership), boolean only
- D2 vendor template override (precedent already in repo: `logo.html.twig`, `footer.html.twig`)
- D3 no PHP change — `UserDetailsSubscriber` passes the real `App\Entity\User` to
  `UserDetailsEvent::setUser()`, so Twig calls `user.isTeamlead` directly
- D4 `user.title` (job title, user-editable) MUST NOT be reused or overwritten
- D5 reuse existing translation key `teamlead` (en "Teamlead" / es "Líder de equipo") — zero new
  keys

## Design options considered

- A: text badge via `widgets.badge_type` next to the name, but it lives inside
  `d-none d-xl-block` so it disappears below XL.
- B: avatar corner indicator, visible at all breakpoints but a symbol alone plus tooltip fails
  on touch and does not state "team lead".
- **C (chosen)**: A + B combined (`d-none d-xl-inline` badge + `d-xl-none` avatar indicator),
  ~6 extra lines of Twig, non-blocking since the PO delegated design.

## Out of scope

Listing WHICH teams; any voter/permission/approval logic; the user dropdown contents;
`UserDetailsSubscriber.php`; entity/schema/migration.

## Testing (Strict TDD)

RED-first functional test — teamlead user (`TeamMember::setTeamlead(true)`, pattern from
`tests/Controller/ApprovalsDashboardControllerTest.php:176-186`) hits `/dashboard/` and the
marker is present; plain ROLE_USER hits `/dashboard/` and it is absent. Assert on a dedicated
CSS class / `data-*` hook, not Bootstrap utility classes.

## Success Criteria

- [x] 1. Teamlead-by-membership users see a visual indicator in the navbar.
- [x] 2. Plain users (no membership) see no indicator.
- [x] 3. Users with the global `ROLE_TEAMLEAD` role but no `TeamMember` membership see no
      indicator (gating is membership-based, not role-based).
- [x] 4. `user.title` and the user dropdown menu render unchanged.
- [x] 5. Zero PHP/entity/migration/voter changes; single new Twig template override.

## Notes

`translations/messages.es.xlf` already ships `teamlead` -> "Líder de equipo", so no new
translation is needed. `openspec/config.yaml` does not exist in this repo even though
`openspec/specs/` does — no project-specific proposal rules to apply.
