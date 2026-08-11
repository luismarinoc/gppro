# Design: ActivityBoard Assigned User Display

## Technical Approach

Make one presentation-layer change in `templates/project/_board_card.html.twig`.
Reuse `ActivityBoardCard`'s existing `assignedTo`, `technicalUser`, and
`functionalUser` accessors. Add the assigned user's display name to the
server-rendered `data-search` value and render an assigned-user block using the
existing avatar macro plus explicit text. Keep the technical and functional
avatar block unchanged. No controller, service, DTO, entity, JavaScript, API,
schema, or baseline-analysis changes are required.

## Architecture Decisions

| Decision | Alternatives considered | Rationale |
|---|---|---|
| Compute presentation data in Twig | Add a view model or controller mapping | The card already exposes all required nullable users; a template-only change is the smallest compatible implementation. |
| Reuse `data-search` and `filterCards()` | Add an endpoint or client-side lookup | The board renders all cards and the JavaScript already performs case-insensitive substring matching on `data-search`. |
| Render assigned user separately from role avatars | Replace role avatars or merge all users into one undifferentiated list | Assignment and technical/functional roles have distinct meaning; separate markup preserves current role semantics and layout. |

## Data Flow

    ActivityBoardService
        └─ ActivityBoardCard accessors
             └─ _board_card.html.twig
                  ├─ visible assigned-user name/avatar
                  └─ data-search → GpproActivityBoard.filterCards()

The assigned display name is appended only when `assignedTo` is non-null.
`Unassigned` is emitted only when assigned, technical, and functional users are
all null. Technical and functional users continue to render their current role
avatars independently, including when an assigned user is present.

## File Changes

| File | Action | Description |
|---|---|---|
| `templates/project/_board_card.html.twig` | Modify | Add assigned-user search metadata and presentation; tighten empty-state condition; preserve role avatars. |
| `tests/Controller/ActivityBoardControllerTest.php` | Modify | Add focused rendering, metadata, nullable-combination, coexistence, and escaping assertions. |
| `assets/js/widgets/GpproActivityBoard.js` | Verify only | Confirm existing `data-search` substring contract; no code change. |
| `src/Activity/ActivityBoardCard.php` | Verify only | Reuse existing accessors; no code change. |

## Interfaces / Contracts

The existing DOM contract remains authoritative:

```text
.activity_board_card[data-search="lowercased activity and user names"]
```

Twig autoescaping must remain enabled for visible names, tooltip attributes,
and `data-search`; do not use `|raw`. Search metadata may be lowercased as it
is today, while visible display names retain their original casing. Use the
existing `activity_board.unassigned` translation for the empty state. The
assigned user's explicit text is the accessible identification; the avatar
must not be the only representation. Existing role tooltip labels and avatar
classes remain unchanged.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Integration | Assigned name is visible and in `data-search` | Extend `ActivityBoardControllerTest` with an assigned card and assert both DOM surfaces. |
| Integration | Assigned-only card is not unassigned | Assert assigned name/avatar exists and `Unassigned` is absent. |
| Integration | Role coexistence and fallback matrix | Cover assigned-only, technical/functional-only, mixed users, and all-null; assert role avatars remain present and only all-null shows `Unassigned`. |
| Integration | Escaping | Use a display name containing markup-sensitive characters and assert rendered text/attribute is escaped. |

No JavaScript test or end-to-end browser flow is needed: the existing client
contract consumes the unchanged attribute and the focused rendered HTML proves
the data source.

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file
classification, or process-integration boundary.

## Migration / Rollout

No migration required. The change is immediately reversible by reverting the
isolated template and test edits; persisted data and APIs are unaffected.

## Open Questions

None.
