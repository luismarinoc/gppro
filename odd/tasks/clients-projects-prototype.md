# Clients and projects visual prototype

## Objective and authorization
User approved an isolated premium SaaS prototype of clients and projects, lists and details. Fictional data only. No production, backend, authentication, permission, deployment, commit or push changes.

## Direction and scope
Restrained light enterprise workspace, deep-blue accents, readable contextual tables and consolidated details inspired by Linear hierarchy, Attio tables and Teamwork summaries. Spanish interface; standalone HTML/CSS/JS in prototypes/clients-projects/ with no external dependencies.

## Constraints and routing
Branch: prototype_clients_projects. Existing untracked browser artifacts preserved. Delegated writer for four files; independent verifier for static checks; parent browser for runtime and representative visual checks because verifier browser profile was occupied. TDD not established; ordinary functional checks used. No commits or PR authorized. Original estimate 500–800 lines advisory only.

## Tasks
- [x] T1: Build client list/detail and project list/detail, fictional fixtures, navigation, search/status filters and responsive layout.
- [x] T2: Run independent static checks and parent runtime checks. Coverage and limitations below.
- [x] T3: Provide local preview URL and demo limitations to user.

## Acceptance and evidence
Writer created index.html, styles.css, app.js and README.md. node --check prototypes/clients-projects/app.js passed; independent verifier also ran syntax and git diff --check without diagnostics. New untracked files are outside git diff coverage.
Parent browser loaded all four routes at 1440x900 and 390x900: expected headings and no horizontal document overflow in eight observations. Inspected screenshots of both lists on desktop and both details across desktop/mobile representative views. Client search returned one row, combined archived filter produced empty state, reset restored six rows, client detail navigation worked. Project search returned two rows, reset restored eight; invalid route showed recovery view. Full keyboard/accessibility audit and exhaustive interaction combinations not performed. Only console error observed was optional favicon.ico HTTP404; no JavaScript runtime errors observed.
Native assessment unavailable, so independent verifier used. Browser file URLs blocked. Initial port8765 belonged to unrelated service and was not changed. Own Python startup was delayed; verifier confirmed PID14449 serves prototype with HTTP200 at http://127.0.0.1:51055/index.html. Other own preview listeners14096/51015 and14318/51041 were identified; no unrelated process stopped.

## Preview and limitations
Entry: http://127.0.0.1:51055/index.html or open prototypes/clients-projects/index.html directly. Routes: #/clients, #/clients/albor, #/projects, #/projects/portal-albor. Static demo only, no save/export/auth/backend; fictional EUR amounts, not live business reports. Mobile tables scroll internally. Local preview availability depends on running server.

## Next step
User evaluates visual direction. Do not integrate with application or deploy without new authorization.
