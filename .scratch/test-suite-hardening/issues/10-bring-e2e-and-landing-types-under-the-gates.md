# 10 - Bring the e2e directories and the landing types under the existing gates

**What to build:** the test code and the public site's types are checked by the
same gates as everything else, so a broken locator or a type error is caught
before it reaches `main`.

Three holes, all in tooling the repo already has.

**Neither e2e directory is linted.** The dashboard lint script globs the source
directory only, so every Playwright spec and fixture is unlinted. The landing app
has no lint script at all.

**Neither e2e directory is typechecked.** The dashboard's application config
includes the source directory only, and its spec config globs a pattern matching
no files. So the e2e TypeScript is compiled by nothing except Playwright at run
time, where a type error surfaces as a failing test rather than a failing check.

**The landing typecheck exists and never runs.** There is a check script wired to
the Astro checker, and continuous integration does not call it. The build does
not typecheck, so type errors in the public site reach `main` unchallenged.

**Adopt nothing new here.** This ticket widens the scope of gates already
configured and adds the one missing call. Introducing a new tool is ticket 11.

Expect the first run to be red. Fix what it finds rather than narrowing the
globs to make it pass, and if something genuinely has to be excluded, say why in
the config.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Both e2e directories are covered by lint
- [ ] Both e2e directories are covered by a typecheck that runs outside Playwright
- [ ] The landing typecheck runs in continuous integration
- [ ] Whatever those three turn up is fixed rather than excluded, and any remaining exclusion carries its reason
- [ ] A deliberate type error in an e2e file fails the pipeline, proving the gate is connected
- [ ] Full pipeline green

## Comments

**2026-09-26** - First run: 54 lint errors in dashboard e2e, 88 in landing e2e, and 4
`astro check` errors in `src/`. All fixed, none excluded. Most were missing annotations. The
real ones: `JSON.parse(...).modality` read an unchecked `any` in three assertions, a `let`
assigned inside a route callback was narrowed to `null` so its `??` fallback was dead, and two
`.astro` components used a `getEntry` result that can be `undefined`.

Landing needed `@astrojs/check`, `typescript`, `eslint` and `typescript-eslint` to run the
gates at all, plus `@types/node` in both apps. The e2e typecheck passed locally without it only
because a stray `@types/node` sits in the home directory, above both apps.

**Gap left open: `astro check` does not typecheck `.svelte` files.** A planted type error in
`SlotCard.svelte` passed it. The components that hold most of the public site's logic are
still unchecked. Closing that means `svelte-check`, a new tool, so it is out of scope here.
