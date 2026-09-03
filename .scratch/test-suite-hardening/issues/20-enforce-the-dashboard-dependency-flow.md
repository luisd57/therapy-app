# 20 - Enforce the dashboard dependency flow

**What to build:** the dashboard's folder dependency rules become lint errors, so an import
that crosses a boundary is caught by `npm run lint` instead of by whoever happens to notice
it in review.

`dashboard-angular.md` already states the flow, and states it strictly: `feature` to
`data-access` to `utils`, `ui` to `utils`, cross-domain imports forbidden, `ui/` must not
import from `feature/` or `data-access/`, and `data-access/` must not import from `feature/`
or `ui/`. Five rules, none of them checked by anything. `eslint.config.js` runs
`strictTypeChecked` with full type-aware linting and carries no import-boundary rule of any
kind.

**This lands green.** Measured 2026-09-03 across the five domains, `appointments`, `auth`,
`layout`, `patients` and `schedule`: no file under a `ui/` imports `data-access/` or
`feature/`, no file under a `data-access/` imports `feature/` or `ui/`, and every import
that escapes its own domain resolves into `shared/` or `environments/`. Same consequence as
ticket 19. Green shows the tree is clean, not that the rule is wired, so each rule has to be
shown failing on a violation introduced for the purpose.

**`shared/` and `environments/` have to stay reachable from everywhere.** The rules file
already says shared needs go in `shared/`, and the `data-access` services read `environment`
for the API base URL. A cross-domain rule written without those carve-outs fails immediately
on correct code, and the reason for each carve-out belongs in the config rather than in
whoever's memory added it.

**Worth doing now rather than later.** The dashboard is the least finished deployable: the
schedule manager, exception manager, therapist profile and patient area are all still to
come, on top of the Spanish sweep. Boundaries are cheap to hold at five domains and
expensive to recover at nine.

**Scope is `src/` only.** The `e2e/` directory has no domain structure and these rules mean
nothing there. Ticket 10 is what brings `e2e/` under lint at all, and the two do not
conflict: that ticket widens which files are linted, this one changes which imports are
legal inside `src/`. Either order works.

**What this cannot catch.** Import paths, not design. A `feature/` page can inject a store
it has no business touching, a `ui/` component can take an input that leaks a data-access
type, and both pass every rule here. It is also silent on the naming conventions in the same
rules file, which are file layout rather than imports.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] All five rules from `dashboard-angular.md` are expressed in `eslint.config.js`: feature to data-access to utils, ui to utils, no cross-domain imports, ui not importing feature or data-access, and data-access not importing feature or ui
- [ ] `shared/` and `environments/` are reachable from every domain, with the reason for each carve-out in the config
- [ ] Each rule's message names the convention and where it is written down, not just the offending path
- [ ] The rules are scoped to `src/`, leaving `e2e/` alone
- [ ] The first run over the current tree is green, and the pull request reports that as a measurement rather than as evidence the rules are connected
- [ ] Introducing each violation deliberately fails `npm run lint`, one at a time, proving no rule is inert
- [ ] Dashboard lint and build green
