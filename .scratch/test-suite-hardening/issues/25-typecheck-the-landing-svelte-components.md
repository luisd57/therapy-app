# 25 - Typecheck the landing Svelte components

**What to build:** a type error inside a landing Svelte component fails the
required CI job, the same way one in an Astro page, a TypeScript module or an e2e
spec already does.

Ticket 10 made the landing typecheck run, and found it stops short. The Astro
checker covers `.astro`, `.ts` and the e2e directory, but not `.svelte`: a type
error planted in the slot card component passed it with zero errors (2026-09-26).
The build strips Svelte TypeScript without checking it either. The components hold
most of the public site's logic, so every type-level guarantee written inside one
is enforced by nothing.

**Use `svelte-check`.** It is the Svelte project's own checker, the only tool that
reads component TypeScript, and a checker rather than test infrastructure, so it
does not reopen the no-component-tests decision in `timezone-management/15`. Run
it as its own CI step beside "Typecheck landing" rather than chained into the
existing check script, so a red run names the checker that failed.

**Expect the first run to be red.** The size is unknown: nothing has ever
typechecked these files. Fix what it finds rather than narrowing its scope, and if
something genuinely has to be excluded, say why in the config.

**Out of scope: linting `.svelte` files.** That needs a Svelte ESLint plugin, a
second new tool with its own rule decisions. Record the choice here if it comes up,
rather than widening this ticket.

**Blocked by:** 10

**Status:** ready-for-agent

- [ ] Every landing `.svelte` component is typechecked, in a step of the required CI job
- [ ] Whatever the first run finds is fixed rather than excluded, and any remaining exclusion carries its reason
- [ ] A type error planted in a component, of the kind the Astro checker passes today, fails the pipeline, proving the gate is connected
- [ ] Landing unit tests, e2e suite and build still green
