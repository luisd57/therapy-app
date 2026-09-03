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

**Four of the five rules land green. The cross-domain ban does not.** Measured 2026-09-03
across the five domains, `appointments`, `auth`, `layout`, `patients` and `schedule`: no
file under a `ui/` imports `data-access/` or `feature/`, and no file under a `data-access/`
imports `feature/` or `ui/`. But `layout/shell.component.ts` imports `AuthService` from
`auth/data-access/`, one domain reaching straight into another domain's data-access, which
is the exact import the rule forbids. It is the only violation of its kind in the tree.

Fix the import rather than allowlisting it, the way ticket 14 handles its two known hits.
The shell reads the signed-in role to choose its nav items, and the rules file says a
cross-domain need goes in `shared/`. So either `AuthService` moves to `shared/data-access/`,
or the shell stops injecting it and takes the role from whatever routes it. That is a design
call to make deliberately, not a line to suppress.

Worth knowing while making it: `layout/` has no `feature/`, `ui/` or `data-access/`
subfolders at all, with `shell.component.ts` and `nav-items.ts` sitting at the domain root.
These rules cannot see that, since import linting has nothing to look at when a directory is
simply absent.

Green on the other four shows the tree is clean, not that the rules are wired. Each still
has to be shown failing on a violation introduced for the purpose.

**`shared/` and `environments/` have to stay reachable from everywhere.** The rules file
already says shared needs go in `shared/`, and the `data-access` services read `environment`
for the API base URL. A cross-domain rule written without those carve-outs fails immediately
on correct code, and the reason for each carve-out belongs in the config rather than in
whoever's memory added it.

**Worth doing now rather than later.** The dashboard is the least finished deployable, with
several domains still unbuilt. `docs/STATUS.md` carries the current list. Boundaries are
cheap to hold at five domains and expensive to recover at nine.

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
- [ ] The `layout/shell.component.ts` import of `auth/data-access/` is resolved by moving the dependency or dropping the injection, not by an allowlist entry, and the pull request says which and why
- [ ] With that cleared, a full run over `src/` is green, reported as a measurement rather than as evidence the rules are connected
- [ ] Introducing each violation deliberately fails `npm run lint`, one at a time, proving no rule is inert
- [ ] Dashboard lint and build green
