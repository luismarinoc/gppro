# Finance Workflow Visual States Specification

## Purpose

Provide a consistent, accessible, non-authoritative presentation vocabulary for empty, loading, and inline unavailable states in authenticated expense, quotation, and approval workflows.

## Requirements

### Requirement: Contextual finance workflow empty states

Targeted authenticated expense, quotation, and approval workflows MUST render a contextual, consistently structured empty state when their relevant collection has no entries. The empty state MUST identify the absent workflow content and MUST NOT rely solely on a blank table row.

#### Scenario: Expense collection is empty

- GIVEN an authorized user has no expenses in a targeted expense list or pending collection
- WHEN the collection renders
- THEN the user sees a contextual structured empty state rather than a table-only empty message

#### Scenario: Quotation collection or lines are empty

- GIVEN an authorized user has no quotations in a targeted list or a quotation has no editable lines
- WHEN the relevant surface renders
- THEN the user sees a contextual structured empty state that distinguishes the absent content

#### Scenario: Approval collection is empty

- GIVEN an eligible user has no pending approval items
- WHEN the approvals dashboard renders
- THEN the user sees the contextual approvals empty state without an error

### Requirement: Advisory lookup progress and unavailable feedback

Expense currency previews and quotation exchange-rate lookups MUST expose a non-disruptive progress state while a lookup is pending and an understandable unavailable state when no result can be shown. Informational state changes MUST use appropriately scoped `role="status"` or `aria-live` semantics and MUST preserve the affected layout without misleading the user that a value was saved.

#### Scenario: Expense lookup status is announced

- GIVEN an expense editor changes a value requiring a currency preview lookup
- WHEN the lookup begins and later succeeds or is unavailable
- THEN the user receives the corresponding visible and assistive-technology status without a claimed persisted value

#### Scenario: Quotation lookup status is announced

- GIVEN a quotation editor changes a value requiring an exchange-rate lookup
- WHEN the lookup begins and later succeeds or is unavailable
- THEN the user receives the corresponding visible and assistive-technology status without a fabricated rate or total

### Requirement: Client state feedback supplements authoritative errors

Client-side empty, loading, and unavailable feedback MUST supplement, and MUST NOT replace, Symfony form errors, flash messages, server validation, persisted calculations, or server-authoritative save outcomes. The state presentation MUST NOT add polling or an animation-dependent interaction.

#### Scenario: Lookup failure does not mask a server error

- GIVEN a lookup is unavailable and a submitted finance form is rejected by server validation
- WHEN the response renders
- THEN the server validation or flash error remains visible as the blocking result and the client unavailable message remains advisory

#### Scenario: Server calculation wins over advisory feedback

- GIVEN an advisory preview or rate was displayed before a finance form is saved
- WHEN the server persists or rejects the submission
- THEN the persisted calculation or server validation result is authoritative regardless of the prior advisory state

### Requirement: State text remains localized

Any net-new visible or assistive state text introduced for targeted finance workflows MUST be available in both English and Spanish catalogs.

#### Scenario: Dynamic state text renders in the active supported locale

- GIVEN the active locale is English or Spanish and a targeted lookup enters a loading or unavailable state
- WHEN the state text is rendered or announced
- THEN it uses a translation available for that locale

## Acceptance Criteria

- Targeted empty states are contextual and structurally consistent.
- Lookup loading and unavailable states are visible, announced, and non-authoritative.
- Server validation, flash feedback, calculations, and save outcomes retain authority.
- New state text has English and Spanish coverage.
