# Design: GPPro calendar visual consistency

## Technical Approach

Apply a presentation-only layer implementing [calendar workflow presentation](specs/calendar-workflow-presentation/spec.md). Add `gp-workflow gp-workflow--calendar` to the existing main row and additive surface hooks; keep Twig conditions and JavaScript unchanged. Consume existing semantic tokens from the shared visual system without modifying `_workflow.scss` or introducing dependencies.

Required artifacts and calendar files were read directly. CodeGraph and shell execution were unavailable; analysis used targeted reads. This phase writes only this design; validation below is planned, not executed.

## Architecture Decisions

| Option | Tradeoff | Decision |
| --- | --- | --- |
| Calendar-scoped Sass versus global workflow changes | Local selectors add modest specificity but isolate other consumers | Scope every new rule under `.gp-workflow--calendar`; retain existing legacy rules unchanged. |
| Additive hooks versus card replacement | Existing card structure limits composition freedom | Retain theme embeds; use enclosing hooks and existing card-body blocks to avoid guessing embed APIs. |
| CSS containment versus FullCalendar option changes | Local scrolling may remain necessary on narrow screens | Use shrinkable columns and a scroll wrapper around the existing target; preserve calendar sizing options. |
| Semantic decoration versus recolored events | Event colors cannot become uniformly branded | Style spacing, radii and focus, never override source background/text colors or contrast calculations. |

Use `--gp-surface`, `--gp-surface-muted`, `--gp-ink`, `--gp-ink-muted`, `--gp-line`, radius/shadow tokens, `--gp-brand-soft` and `--gp-focus`. Today takes precedence over weekend shading; selection remains distinguishable from both. Preserve native disabled semantics and visible focus without global opacity/filter treatment on events.

Keep existing toolbar-generated Bootstrap classes; scope wrapping, spacing and title sizing around them. Apply `min-width: 0` to columns/surfaces and `max-width: 100%; overflow-x: auto` to the target wrapper. Avoid document overflow suppression and event/popover clipping. Source text retains truncation and tooltips. Preserve `d-none d-md-block`: the sidebar remains hidden below 768px, not redesigned into new mobile interactions.

## Data Flow

Authenticated request → existing controller/configuration → unchanged Twig form/source data → additive HTML hooks → scoped Sass.

Existing inline options → `GpproCalendar` → existing event sources and timesheet endpoints remains unchanged. No new data structures, services, requests or persistence.

## Allowed Edit Surfaces

Paths below are implementation allowances, not edits performed in this phase.

| File | Allowed change |
| --- | --- |
| `templates/calendar/user.html.twig` | Main-block classes and containment wrappers only. |
| `templates/calendar/drag-drop.html.twig` | Optional additive presentation hooks only; preferably unchanged. |
| `assets/sass/calendar.scss` | Calendar-scoped shell, toolbar, state and responsive rules. |
| `tests/Controller/CalendarControllerTest.php` | Focused rendering/contract assertions using existing fixture patterns. |

No controllers, JavaScript, routes, permissions, translations, integrations, shared tokens, dependencies, migrations, plugins or generated assets may change.

## DOM Contracts

- Exactly one `#timesheet_calendar`, unchanged initialization target; retain optional `#calendar-form`, fields, hidden fields and submission semantics.
- Preserve `hasTwoColumns`, source filtering/slicing, card-body `.drag-and-drop-source`, `.external-events`, `.external-event`, `.draggable` and their container/item relationships.
- Preserve `data-method`, `data-route`, `data-route-replacer`, `data-entry`, JSON escaping, tooltips and custom source blocks.
- Leave stylesheet/script entry tags and the entire JavaScript block untouched, including permissions, URLs, event listeners and Google/source options.
- Do not add tabindex or button roles to draggable divs: they are not currently keyboard controls. Style existing focusable descendants and focus-within; keyboard drag support requires separate scope.

## Testing Strategy

1. **RED:** Add assertions for missing workflow/surface/scroll hooks to the existing populated fixture case. Run `vendor/bin/phpunit tests/Controller/CalendarControllerTest.php`; record command, failing assertion and exit status before presentation edits. Infrastructure failure is not RED.
2. **GREEN:** Add minimal Twig hooks, rerun and record passing evidence. Assert preserved IDs, selector relationships, decoded drag payloads and existing Google configuration.
3. **TRIANGULATE:** Cover filter-present and genuinely sidebar-absent rendering using verified existing configuration/fixture capabilities; retain security and super-admin coverage. Do not simulate absence with CSS.
4. **REFACTOR:** Consolidate scoped rules and rerun focused tests. This file is integration-group coverage; `composer tests-unit` alone is insufficient.
5. Record before/after browser evidence at 360/768/1024px in both themes: toolbar, events, weekend/today/selection, hover/disabled/focus, sidebar variants and document-versus-local overflow. PHPUnit cannot prove computed styling. Use local fixtures for mutating interaction checks; deployed checks remain read-only, credentials never printed.
6. Run `APP_DEBUG=1 composer linting`, `./phpstan.sh test`, `./php-cs-fixer.sh core`, `composer tests-unit`, and `pnpm build`; keep generated output out of delivery and preserve pre-existing changes.

## Review, Rollout and Rollback

Estimate additions plus deletions: Twig 25–45, Sass 85–120, tests 45–65, design 80–90: **235–320 lines**, reserving 40 for tasks/evidence. Recount the complete delivery diff including earlier artifacts; pause under ask-on-risk if the 360-line safety target or 400-line budget is threatened. No exception or chaining is preauthorized.

No migration or feature flag. Deploy through the existing asset pipeline after validation; repeat read-only visual checks. Roll back this slice's Twig/Sass/tests and redeploy matching assets, without touching data.

## Threat Matrix / Open Questions

N/A: no routing, shell, subprocess, VCS automation or process-integration boundary changes. No blocking design questions; actual browser containment and fixture feasibility remain implementation verification gates.
