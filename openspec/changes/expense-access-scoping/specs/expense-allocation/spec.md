# Delta for Expense Allocation

## ADDED Requirements

### Requirement: Expense visibility scoped for non-admin users

For users without `canSeeAllData()` (all current non-admin callers hold only
`ROLE_TEAMLEAD`), an `Expense` MUST be visible in both the list and the
detail view if, and only if, at least one of the following holds:
(a) at least one of its `ExpenseAllocation` rows targets a project the
user's team can access, using the same team-membership scope already
applied to `Project` visibility; (b) the user is an eligible approver for
the expense, per the existing approval-eligibility rule; (c) the user
created the expense. Visibility across multiple allocations MUST be
evaluated as OR: a single team-accessible allocation is sufficient
regardless of the projects targeted by the expense's other allocations.
Users with `canSeeAllData()` (`ROLE_ADMIN`, `ROLE_SUPER_ADMIN`) MUST
continue to see every expense, unaffected by this rule.

#### Scenario: Team-accessible allocation grants visibility

- GIVEN a ROLE_TEAMLEAD user whose team has access to project P
- AND an expense with an allocation on project P, created by another user
- WHEN the user opens the expense list or the expense's detail page
- THEN the expense appears in the list and the detail page renders

#### Scenario: Approver carve-out grants visibility without a team-project match

- GIVEN a ROLE_TEAMLEAD user who is an eligible approver for an expense
- AND none of that expense's allocations target a project their team can
  access
- WHEN the user opens the expense list or its detail page
- THEN the expense appears in the list and the detail page renders

#### Scenario: Creator always sees their own expense

- GIVEN a ROLE_TEAMLEAD user who created an expense
- AND the expense's allocation targets a project their team cannot access
- WHEN the user opens the expense list or its detail page
- THEN the expense appears in the list and the detail page renders

#### Scenario: Visibility is OR across multiple allocations

- GIVEN an expense with allocations on project P (team-accessible) and
  project Q (not team-accessible)
- WHEN a ROLE_TEAMLEAD user with team access only to P views the list
- THEN the expense appears, based solely on the P allocation matching

#### Scenario: Admin and super-admin see every expense unchanged

- GIVEN a ROLE_ADMIN or ROLE_SUPER_ADMIN user
- WHEN they open the expense list or any expense's detail page
- THEN every expense is visible, regardless of allocations, approver
  status, or creator

### Requirement: Unauthorized direct access to an expense is denied, not merely hidden

The system MUST enforce the same visibility rule at the single-record
boundary as at the list boundary. A user for whom none of the visibility
conditions in the preceding requirement hold MUST be denied access with an
HTTP 403 response when requesting the expense directly by ID, MUST NOT
receive any of the expense's data in that response, and MUST NOT see the
expense in the list. The 403 status matches this application's existing
convention for a subject-aware voter denial on a single-record view (as
already used by `view_invoice` and `view_quotation`).

#### Scenario: Unauthorized user is excluded from the list

- GIVEN a ROLE_TEAMLEAD user with no team-accessible allocation, no
  approver eligibility, and no creator relationship to an expense
- WHEN the user opens the expense list
- THEN the expense does not appear

#### Scenario: Unauthorized direct URL access is denied, not silently filtered

- GIVEN the same user and expense as above
- WHEN the user requests `/expense/{id}` directly, using a known or
  guessed ID
- THEN the response is HTTP 403 and contains none of the expense's data
