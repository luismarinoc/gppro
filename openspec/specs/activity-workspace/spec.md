# Activity Workspace Specification

## Purpose

A project-scoped, three-panel screen — activity list, activity detail, comment
thread — reached from the "Actividades" menu via a project picker. Additive
only: the Kanban board and the existing global activity list are untouched.

## Requirements

### Requirement: "Actividades" menu opens a project picker

The system MUST repoint the `activities` menu entry to a project picker that,
on project selection, opens the activity workspace scoped to that project.

#### Scenario: Selecting a project from the picker opens its workspace

- GIVEN a user with `view` access to project P opens "Actividades"
- WHEN they select project P from the picker
- THEN the workspace renders scoped to project P only

### Requirement: Global activity list remains unchanged and reachable

The system MUST keep the existing cross-project activity list, its route,
permissions, and CRUD child routes byte-identical, and MUST expose it via a
new sibling menu entry ("Todas las actividades") distinct from the picker.

#### Scenario: Global list still renders and is still reachable

- GIVEN a user with `view_activity` permission
- WHEN they open "Todas las actividades" from the menu
- THEN the existing global activity list renders exactly as before this
  change, including its create/edit/details/delete routes

### Requirement: Panel 1 lists the project's non-global activities

The workspace MUST list every non-global activity belonging to the selected
project, and MUST NOT include activities from any other project.

#### Scenario: Panel 1 shows only this project's activities

- GIVEN project P has three non-global activities and project Q has two
- WHEN the workspace opens for project P
- THEN panel 1 lists exactly P's three activities and none of Q's

### Requirement: Panel 2 shows base activity fields only

Selecting an activity MUST render only base `Activity` fields (name,
description, visible, billable, number, budget/timeBudget, project,
milestone). The system MUST NOT read or display `ActivityBoardState` fields
(status, priority, due date, assignee) in either direction.

#### Scenario: Selected activity renders base fields

- GIVEN an activity with a name, description, and budget
- WHEN it is selected in panel 1
- THEN panel 2 shows those base fields and performs no board-state read

#### Scenario: No activity selected renders an empty state

- GIVEN the workspace opens with no activity in the URL
- WHEN the page renders
- THEN panels 2 and 3 show empty states without error

### Requirement: Activity selection is a bookmarkable URL segment

The selected activity MUST be addressed as a URL path segment, not
client-side-only state.

#### Scenario: Direct URL to a selected activity renders its detail

- GIVEN a URL containing an activity belonging to the current project
- WHEN a user with project access navigates to it directly
- THEN the workspace opens with that activity's detail and comments shown

### Requirement: Cross-project activity requests are rejected

The system MUST verify the requested activity belongs to the requested
project and MUST return 404 when it does not.

#### Scenario: Activity from another project is rejected

- GIVEN project P's workspace URL references an activity that belongs to
  project Q
- WHEN the request is made
- THEN the response is 404 and no data from project Q is rendered

### Requirement: Comment thread is visible only to authorized users

Panel 3 MUST render the comment thread and post form only for users granted
the project's `comments` permission, and MUST hide both for users who can
view the project but lack that permission.

#### Scenario: Authorized user sees thread and form

- GIVEN a user with `comments` access to project P
- WHEN they open an activity in P's workspace
- THEN panel 3 shows the existing comments and a post form

#### Scenario: Unauthorized user sees neither thread nor form

- GIVEN a user with `view` but not `comments` access to project P
- WHEN they open an activity in P's workspace
- THEN panel 3 shows no comments and no post form

### Requirement: Posting a comment persists it and reloads the thread

A comment post MUST be a form submission that saves the comment with its
author and timestamp, then redirects back to the same activity, showing the
new comment in panel 3.

#### Scenario: Comment appears after redirect

- GIVEN a user with `comments` access viewing an activity
- WHEN they submit a text comment
- THEN the comment is persisted and appears in panel 3 with the correct
  author and timestamp after the redirect

#### Scenario: Deleting an activity cascades its comments

- GIVEN an activity with existing comments
- WHEN the activity is deleted
- THEN all its comments are deleted with it and no orphan rows remain

## Acceptance Criteria

- The picker is the only path from "Actividades" to the workspace; the
  global list survives unchanged under its own menu entry.
- Panel 1 never mixes activities across projects.
- Panel 2 never reads or shows `ActivityBoardState` fields.
- An out-of-project `{activity}` in the URL always 404s.
- Panel 3 is gated by the project `comments` permission; posting is
  synchronous form POST + redirect, never real-time.
- Comments cascade-delete with their owning activity.
