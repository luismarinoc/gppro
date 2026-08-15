# Team Lead Navbar Indicator Specification

## Purpose

Make "you are a team lead" self-evident in the top navbar so elevated
actions (Approve/Reject on timesheets) stop reading as a bug. Display-only:
no permission, voter, entity, or migration change. Source of truth is
`User::isTeamlead()` — per-team membership flag, not the global
`ROLE_TEAMLEAD`.

## Requirements

### Requirement: Indicator gated by per-team membership, not by role

The navbar user area MUST show a visual team-lead indicator only when
`user.isTeamlead` (per `App\Entity\User::isTeamlead()`, true if the user is
teamlead of at least one `TeamMember` record) evaluates to true. It MUST NOT
show the indicator based on the global `ROLE_TEAMLEAD` role alone, and MUST
NOT show it when `user.isTeamlead` is false or undefined.

#### Scenario: Team lead by membership sees the indicator

- GIVEN a user who is teamlead (`TeamMember::setTeamlead(true)`) of at least
  one team
- WHEN any page renders the navbar
- THEN the team-lead indicator is present in the user area

#### Scenario: Plain user sees no indicator

- GIVEN a user with no teamlead membership on any team
- WHEN any page renders the navbar
- THEN no team-lead indicator is present in the user area

#### Scenario: Global role without membership does not trigger the indicator

- GIVEN a user holding `ROLE_TEAMLEAD` globally but with `teamlead = false`
  on every `TeamMember` record
- WHEN any page renders the navbar
- THEN no team-lead indicator is present (gating is membership-based, not
  role-based)

### Requirement: Indicator uses the existing `teamlead` translation key

The indicator's visible or accessible label MUST use the existing
translation key `teamlead` (already defined in
`translations/messages.en.xlf` and `translations/messages.es.xlf`). No new
translation key MAY be added for this indicator.

#### Scenario: Label renders via existing key

- GIVEN a team lead viewing the navbar in either supported locale (en/es)
- WHEN the indicator renders
- THEN its text is sourced from the `teamlead` translation key, matching the
  locale's existing translation

### Requirement: Responsive presentation — text badge at XL, avatar overlay below XL

At viewport widths `xl` and above (`d-xl-inline` or equivalent, matching the
expanded, non-condensed user menu), the indicator MUST render as a text
badge adjacent to `user.name`. Below `xl`, or whenever
`tabler_bundle.isCondensedUserMenu()` is true, the indicator MUST instead
render as a small overlay marker positioned on the user avatar. Both forms
MUST be driven by the same `user.isTeamlead` condition and MUST NOT both
render at once for the same breakpoint.

#### Scenario: XL breakpoint shows the text badge

- GIVEN a team lead viewing at a viewport width of `xl` or greater, with the
  full (non-condensed) user menu
- WHEN the navbar renders
- THEN a text badge with the `teamlead` label appears next to the user's
  name

#### Scenario: Below-XL or condensed menu shows the avatar overlay

- GIVEN a team lead viewing at a viewport width below `xl`, or with
  `tabler_bundle.isCondensedUserMenu()` true
- WHEN the navbar renders
- THEN an overlay indicator appears on the user's avatar, and the text
  badge form does not render

#### Scenario: Non-team-lead sees neither form at any breakpoint

- GIVEN a user with no teamlead membership
- WHEN the navbar renders at any viewport width or menu mode
- THEN neither the text badge nor the avatar overlay indicator renders

### Requirement: No regression to `user.title` or the user dropdown menu

`user.title` (job title, user-editable via `User::getTitle()`) MUST
continue to render exactly as before this change when set, and MUST render
nothing when unset — unchanged behavior. The user dropdown menu's link
contents and structure MUST remain unchanged; this capability MUST NOT add,
remove, or reorder dropdown links.

#### Scenario: Title still renders when set

- GIVEN a user (team lead or not) with a non-empty `title`
- WHEN the navbar renders
- THEN the title text appears below the user's name exactly as it did
  before this change

#### Scenario: Dropdown menu links unchanged

- GIVEN any authenticated user
- WHEN they open the user dropdown menu
- THEN the same set of links renders in the same order as before this
  change, regardless of teamlead status

### Requirement: Display-only implementation, no backend change

This capability MUST be implemented entirely as a new Twig template
override (`templates/bundles/TablerBundle/includes/navbar_user.html.twig`)
that shadows the vendor template. It MUST NOT introduce PHP changes, new
entity fields, database migrations, or changes to `UserDetailsSubscriber`,
`UserDetailsEvent`, `ActivityVoter`, or any approval/permission logic.

#### Scenario: No schema or permission side effects

- GIVEN this capability is applied
- WHEN reviewing the change
- THEN it contains only a new/overridden Twig template and its tests — no
  PHP class changes, no migrations, and no altered voters or approval logic
