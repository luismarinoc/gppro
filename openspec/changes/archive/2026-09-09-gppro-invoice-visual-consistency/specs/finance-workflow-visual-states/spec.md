# Delta for Finance Workflow Visual States

## MODIFIED Requirements

### Requirement: Contextual finance workflow empty states

Targeted authenticated expense, quotation, approval, invoice workspace, and milestone-invoicing workflows MUST render a contextual, consistently structured empty state when their relevant collection has no entries. The empty state MUST identify the absent workflow content and MUST NOT rely solely on a blank table row.

(Previously: Contextual empty states were required only for targeted expense, quotation, and approval workflows.)

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

#### Scenario: Invoice workspace collection is empty

- GIVEN an authorized user has no invoice archive, preview, or customer-scoped milestone content for the active workflow
- WHEN the relevant included invoice surface renders
- THEN the user sees contextual empty-state content without an error or a table-only empty message
