# Exploration: expense-allocation ("Allocation")

## Current State

No expense/cost entity exists in gppro. `BudgetTrait` (`src/Entity/BudgetTrait.php`, used by `Project`/`Customer`/`Activity`) is only a monetary/time **cap**, not a record of actual spend. `Timesheet::internalRate` (`src/Entity/Timesheet.php`) is the closest thing to a cost concept — an internal hourly rate captured per timesheet row — but nothing aggregates it against revenue; no profitability/margin reporting exists.

## Affected Areas

- `src/Entity/BudgetTrait.php` — confirms "budget" ≠ "expense" (it's a ceiling, not actual spend)
- `src/Entity/Timesheet.php` — `internalRate`/`hourlyRate` fields, the only existing "cost vs. billing" data point
- `src/Entity/Quotation.php`, `src/Entity/QuotationLine.php` — precedent for header+lines, currency validation, guarded status transitions
- `src/Entity/Milestone.php`, `src/Milestone/MilestoneTotalCalculator.php` — precedent for multi-currency aggregation across entities via `ClpConverter`
- `src/FxRate/ClpConverter.php` — reusable currency-conversion service, currency-agnostic on purpose
- `src/Invoice/Calculator/*.php` — many-rows to one-total aggregation pattern
- `src/Voter/QuotationVoter.php` — permission pattern (role + team-scope checks) any new "Allocation" voter should follow
- `src/Entity/Activity.php` — `Activity -> Project` is nullable, confirming Customer -> Project -> Activity(optional) -> Timesheet hierarchy

## Reusable Patterns

- `ClpConverter` (CLP/USD/UF conversion, already used by `Milestone` and `Quotation`).
- Header + lines pattern with currency validation and a guarded status state machine (`Quotation`/`QuotationLine`).
- `MilestoneTotalCalculator` as precedent for "sum multi-currency amounts from many entities into one total" — but it is many-to-one, not one-to-many.
- Voter/permission pattern (`QuotationVoter` + `RolePermissionManager`) and domain namespaces (screaming architecture: `src/Milestone/`, `src/FxRate/`, `src/Invoice/`).
- Customer -> Project -> Activity(optional) -> Timesheet hierarchy, with `Timesheet.internalRate` as the only existing internal-cost data point.

## What's Missing

- No `Expense`/`Cost` entity, no cost-center concept, no real-cost-vs-budget-vs-invoiced report (despite `internalRate` already being captured, it's never aggregated anywhere).
- No existing service distributes **one amount across multiple targets** — everything existing aggregates (many to one), never splits (one to many).

## Risks

- Building an allocation model before deciding whether it touches `Invoice`/`Quotation` risks either duplicating billing logic or building something invoicing can't later consume.
- Distributing one amount across many targets by a ratio/criteria is new territory with no in-repo precedent to copy directly (only the currency-conversion and header/lines patterns are reusable).
- No cost-center concept exists; if the business needs one, that's a prerequisite entity, not part of this module.

## Open Business Questions (blocking — must be resolved before `sdd-propose`)

1. ¿Qué tipo de gasto se distribuye (operativo interno vs. reembolsable al cliente)?
2. ¿El gasto ya existe en otro sistema (ERP/contable) o se carga por primera vez acá?
3. ¿Entre qué se distribuye — Customer, Project, Activity, u otra cosa que no existe hoy (centro de costo)?
4. ¿Con qué criterio de reparto (porcentaje manual, monto fijo, prorrateo por horas, por presupuesto, otro)?
5. ¿Impacta facturación (`Invoice`/`Quotation`) o es solo costeo interno?
6. ¿Requiere multi-moneda como Quotation/Milestone?
7. ¿Es puntual o recurrente (ej. arriendo mensual)?
8. ¿Necesita workflow de aprobación (borrador → aprobado) o es cálculo directo?
9. ¿Quién puede crearlo/aprobarlo — roles nuevos o reutiliza los de Quotation/Invoice?
10. ¿Se espera además un reporte de costo real vs. facturado por proyecto/cliente como resultado?

## Recommendation

Do not proceed to `sdd-propose` yet. There is no existing "expense" concept to build on, so the questions above are true product blockers, not implementation details.
