# Timesheet Approval Workflow

Timesheets use an explicit single-step workflow: `draft`, `pending_approval`,
`approved`, or `rejected`.

## State model

| State | Meaning | Editable | Allowed transition |
|---|---|---:|---|
| `draft` | Saved but not submitted | Yes | stopped → pending |
| `pending_approval` | Submitted and awaiting a team-lead decision | No | approved or rejected |
| `approved` | Decision accepted | No | none |
| `rejected` | Decision declined and available for correction | Yes | stopped → pending |

Submission is rejected in the domain for running entries. Approval and rejection
are transactional service mutations and append a `TimesheetApproval` row. Re-
submission increments `approval_attempt`, preserving earlier decisions.

## Migration and rollback

`Version20260828130000` defaults existing records to `draft`, then backfills
records with a legacy `approved_at` value to `approved`. Existing approver and
timestamp columns remain for compatibility. Rollback drops only the new status,
attempt, and history table; it does not delete legacy approval columns or records.

## Permissions and risks

Existing project/team authorization, `TimesheetVoter`, route attributes, and
Symfony CSRF tokens remain authoritative. Only a project team lead can decide a
pending entry; no new role-level grant is introduced. Legacy records without
`approved_at` are intentionally interpreted as drafts rather than inferred
submissions.
