# 25 - Typecheck the landing Svelte components

> Frozen record, resolved 2026-10-29.

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

**Status:** resolved

**Resolved by:** [PR #124](https://github.com/luisd57/therapy-app/pull/124)

- [x] Every landing `.svelte` component is typechecked, in a step of the required CI job
- [x] Whatever the first run finds is fixed rather than excluded, and any remaining exclusion carries its reason
- [x] A type error planted in a component, of the kind the Astro checker passes today, fails the pipeline, proving the gate is connected
- [x] Landing unit tests, e2e suite and build still green

**2026-10-29** - `npm run check:svelte` runs `svelte-check --fail-on-warnings`, as the "Typecheck
landing Svelte" step of the `test` job. The first run found no type errors and six
`state_referenced_locally` warnings. Two were labels computed once from a prop, now `$derived`.
Four seed editable state from a prop on purpose and carry a `svelte-ignore` with the reason.
Warnings fail the step so a new one cannot land unseen. Locally, a planted
`const planted: number = 'a'` in `SlotCard.svelte` passed `astro check` with 0 errors and failed
`check:svelte` with exit 1. A planted warning alone failed it too. Svelte linting did not come up.

**2026-10-29** - Gate proven connected in CI on PR #124. The same plant in `SlotCard.svelte` passed
"Lint landing" and "Typecheck landing", then failed the `test` job at "Typecheck landing Svelte"
(run 40890323832). Reverted, and the head tree is identical to the last green commit `6b5dbf7`
(run 40890258030).
