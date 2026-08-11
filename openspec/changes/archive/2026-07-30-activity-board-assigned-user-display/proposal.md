# Proposal: ActivityBoard Assigned User Display

## Intent

ActivityBoard cards currently omit the assigned user's display name, cannot be
found by that name, and may show `Unassigned` when only an assigned user exists.
Add the missing presentation and search behavior as an independent product
change. `gppro-codebase-analysis` remains documentation/measurement-only.

## Scope

### In Scope
- Render the assigned user's display name on the activity card.
- Include that name in the card's existing board-search metadata.
- Show `Unassigned` only when assigned, technical, and functional users are all absent.
- Preserve technical/functional role avatars and add focused regression coverage.

### Out of Scope
- Assignment authorization, persistence, DTOs, routes, controllers, services, APIs, schema, or migrations.
- Card movement/status/priority/due-date behavior, deployment, production repair, or plugin changes.
- Changes to the baseline measurement/documentation change.

## Capabilities

### New Capabilities
- `activity-board-assigned-user-display`: Display and search ActivityBoard cards by their assigned user while preserving role-user rendering and correct empty-state behavior.

### Modified Capabilities
- None.

## Approach

Make the minimal template change in `templates/project/_board_card.html.twig`:
reuse the existing `ActivityBoardCard` accessors, add the assigned display name
to `data-search`, render it with established card/avatar conventions, and make
the fallback require all three user relations to be null. Verify the existing
client search contract remains sufficient; do not add a lookup or view-model
layer. Extend `ActivityBoardControllerTest` for visible output, metadata, and
the all-null fallback while preserving technical/functional avatars.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `templates/project/_board_card.html.twig` | Modified | Assigned-user output, search metadata, fallback condition |
| `tests/Controller/ActivityBoardControllerTest.php` | Modified | Rendering, search, and coexistence regression coverage |
| `assets/js/widgets/GpproActivityBoard.js` | Verified | Preserve existing `data-search` matching contract |
| `src/Activity/ActivityBoardCard.php` | Reused | Existing assigned-user accessor; no change expected |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Markup disrupts role-avatar layout or escaping | Low | Follow existing Twig/avatar conventions and focused tests |
| Search metadata diverges from visible output | Low | Assert assigned display name in both outputs |
| Nullable user combinations regress fallback behavior | Med | Cover assigned-only, role-only, mixed, and all-null cases |

## Rollback Plan

Revert the isolated template and test changes. No schema, persisted data, API,
or migration rollback is required.

## Dependencies

- Existing nullable `assignedTo` relation and ActivityBoard search metadata contract.

## Success Criteria

- [ ] Assigned users are visible and searchable by display name.
- [ ] Mixed assigned/technical/functional cards retain role avatars.
- [ ] `Unassigned` appears only for cards with all three user slots empty.
- [ ] Focused controller regression coverage passes without baseline-scope changes.
