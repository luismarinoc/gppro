# ActivityBoard Assigned User Display Specification

## Purpose

Define how ActivityBoard cards display and expose the assigned user while preserving technical and functional user presentation and the correct empty state.

## Requirements

### Requirement: Display and search by assigned user

The system MUST display the assigned user's display name on an ActivityBoard card when an assigned user exists. The card's existing board-search metadata MUST include the same display name so a board search can match that user.

#### Scenario: Assigned user is visible and searchable

- GIVEN a card has an assigned user with display name “Alex Rivera”
- WHEN the card is rendered and the board searches for “Alex Rivera”
- THEN the display name is visible on the card
- AND the search metadata contains “Alex Rivera” and the card matches

#### Scenario: Assigned display name is safely rendered

- GIVEN an assigned user's display name contains markup-significant characters
- WHEN the card is rendered
- THEN the name is presented as text without creating executable or unintended markup
- AND the search metadata represents the same user name for matching

### Requirement: Preserve role-user presentation

The system MUST preserve technical and functional user avatars when those users exist, regardless of whether an assigned user also exists. The assigned user MUST coexist with those role-user presentations without replacing or suppressing them.

#### Scenario: All user roles coexist

- GIVEN a card has assigned, technical, and functional users
- WHEN the card is rendered
- THEN the assigned display name is shown
- AND both technical and functional avatars remain visible

#### Scenario: Role users exist without an assigned user

- GIVEN a card has a technical or functional user but no assigned user
- WHEN the card is rendered
- THEN the available role avatar remains visible
- AND no assigned-user name is shown

### Requirement: Show Unassigned only for an empty user set

The system MUST show `Unassigned` only when the assigned, technical, and functional user slots are all empty. It MUST NOT show `Unassigned` when any one of those users exists.

#### Scenario: All user slots are empty

- GIVEN assigned, technical, and functional users are all absent
- WHEN the card is rendered
- THEN `Unassigned` is shown

#### Scenario: Any user slot is populated

- GIVEN exactly one or any combination of the three user slots is populated
- WHEN the card is rendered
- THEN `Unassigned` is not shown
- AND each populated user's existing presentation remains available

## Acceptance Criteria

- Assigned display names are visible and included in board-search metadata.
- Technical and functional avatars remain present on mixed-user cards.
- `Unassigned` appears only when all three user slots are empty.
- Existing card search behavior and unrelated ActivityBoard behavior remain unchanged.
- Focused regression coverage verifies assigned-only, role-only, mixed, all-null, and safe-rendering cases.
