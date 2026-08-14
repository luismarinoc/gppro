# Timesheet Approval Specification

## Purpose

Single-step team-lead approve/reject on `Timesheet` entries. Self-approval
allowed (no four-eyes rule). Does not touch `WorkingTime`.

## Requirements

### Requirement: Team lead can approve a team member's entry

The system MUST allow a project's team lead (`TeamMember::isTeamlead()` /
`RolePermissionManager::checkTeamLeadAccess()` — per-team, not a global role)
to approve a `Timesheet` entry logged by a member of their team on that
project.

#### Scenario: Team lead approves a member's entry
- GIVEN user L is team lead of project P and user M is a member of P's team
- WHEN L approves M's pending Timesheet entry on P
- THEN the entry is marked approved, recording approver and timestamp

### Requirement: Self-approval is allowed

The system MUST allow a team lead to approve their own logged hours on a
project they lead. No creator-exclusion rule applies to the `approve`
attribute.

#### Scenario: Team lead approves own hours
- GIVEN user L is team lead of project P and has logged hours on P
- WHEN L approves their own entry
- THEN the entry is marked approved

### Requirement: Non-team-lead cannot approve

The system MUST deny the `approve`/`reject` attribute to any user who is not
the entry's project team lead.

#### Scenario: Non-lead attempts approval
- GIVEN user X is not team lead of project P
- WHEN X attempts to approve an entry logged on P
- THEN the action is denied (403)

### Requirement: Approved entries become read-only

Once approved, a `Timesheet` entry MUST become non-editable to its owner,
mirroring Expense's `isEditable()` gate and its row-click-to-edit
enforcement (`row-click-edit-consistency`).

#### Scenario: Owner cannot edit an approved entry
- GIVEN a Timesheet entry approved by its team lead
- WHEN the owner attempts to edit it (including row-click-to-edit)
- THEN the edit is denied and the row is not clickable into edit mode

#### Scenario: Owner can still edit a pending entry
- GIVEN a Timesheet entry not yet approved
- WHEN the owner edits it
- THEN the edit succeeds

### Requirement: Team lead can reject a pending entry

The system MUST allow a team lead to reject a pending entry, returning it to
an unapproved, editable state for its owner. Rejecting an already-approved
entry is out of scope for this slice.

#### Scenario: Team lead rejects a pending entry
- GIVEN a pending Timesheet entry on project P
- WHEN team lead L rejects it
- THEN the entry remains/returns to unapproved state and stays editable by
  its owner

**Design note (non-blocking assumption)**: reject is modeled as "stays/returns
to unapproved," not a distinct terminal `rejected` state — no separate audit
state was locked in the proposal. Confirm at design time.
