# Unified operational calendar system

## Objective and authorization
Continue full-product visual migration after forms/modals publication. Branch visual_operational_surfaces. Modernize FullCalendar month/week/day as one presentation slice while preserving event sources,permissions,routes,filters,modals,URL/date events,popovers,reloads,drag/drop/resize mutations and reverts. User authorized eventual push when verified.

## Tasks
- [x] T1: Map calendar architecture, contracts and adjacent operational surfaces.
- [x] T2: Audit deployed month/week/day desktop/mobile/light/dark read-only.
- [x] T3: Implement presentation-only calendar/sidebar/event/toolbar/responsive refinements.
- [x] T4: Compile LTR/RTL and independently verify source contracts.
- [x] T5: Replace deployed stylesheet, browser-test read-only matrix and publish exact source slice if clean.

## Scope and preservation
Only assets/sass/calendar.scss changed. No template,JS,PHP,controller,API,permission or mutation changes. Preserved #timesheet_calendar,gp calendar wrappers,calendar-form,external/draggable data routes,event IDs/events,toolbar chunks,FullCalendar classes/context/popover hooks and event-derived colors. Weekly quick entry,dashboard,ticktac,boards deferred.

## Implementation
Calendar canvas/sidebar cards use semantic surfaces,lines,radii and restrained no-shadow treatment. Toolbar now owns Bootstrap-assigned chunk widths with explicit3-column desktop grid and2-row mobile grid inside deliberate horizontal scroll. Title is single-line ellipsized. Buttons use semantic normal/active/disabled/focus states. Grid headers,week/time axes,day numbers,weekends,today,selection,now line,events and FullCalendar popovers have coherent hierarchy. Recent external entries get hover/focus/grab states. Reduced-motion disables introduced transitions. Event background/text colors remain source-owned and vendor hover preserved.

## Defects found and fixed
Independent review found candidate arrow rules overwrote FullCalendar direction-dependent transparent triangle sides; removed and retained --fc-now-indicator-color. Removed dead .fc-timegrid-all-day selector. Added long-title nowrap/ellipsis. Browser showed candidate event brightness filter was overridden and unnecessary; removed it to avoid contrast uncertainty. No final P1/P2.

## Static evidence
Final LTR candidate `/tmp/gppro-calendar-candidate-v3.css`:875277 bytes,221 files,21 warning callbacks,SHA256 cb23557fee244017d95af45955ae4634e2cf1a5620189597caa46a8daa74d141. RTL candidate `/tmp/gppro-calendar-candidate-v3-rtl.css`:246643 bytes,106 files,21 warnings,SHA256 70ca5d7d869a5841047d4bac4cc9e4715254d9d4a2680bf634e41d68536566d1. Both exclusive. Diff-check clean. Independent final source verifier found prior P2s resolved and no new P1/P2. Native review unavailable package-local-binary-missing.

## Browser evidence
Full stylesheet replacement on `/es/calendar/?date=2026-08-28&view=month`. Matrix390/600/1440,light/dark,month/week/day: event loaded in all views; no document overflow; desktop toolbar chunks remain same row; title nowrap; mobile uses explicit2-row toolbar within 36rem calendar and horizontal scroll. Desktop scroll client/width832/832;390 client357/scroll576;600 client567/scroll576. Sidebar visible desktop and intentionally hidden under md. Existing external recent activity visible. Hovering actual event showed vendor background and filter none, opened one popover without edit/click/mutation. URL/view client state updated through existing mechanism. No create/edit/event click,drag,resize,PATCH/POST or form submit.

## Limits
RTL Sass compiles, but runtime RTL replacement timed out in current browser harness; inherited Tabler RTL important declarations remain a P3 rendered-style caveat. Long ellipsized title has no hover title attribute because no template change was justified. Only one app timesheet and one recent source existed in current fixture; exported/running/external calendar source permutations were source-audited,not all rendered. Standalone candidate has expected font asset limitations.

## Next
Publish exact calendar.scss after remote synchronization. Then treat dashboard time-at-a-glance as next independent slice; weekly quick entry and ticktac remain separate due mutation risk.
