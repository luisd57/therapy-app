# 15 - Write down how landing components get tested

**What to build:** the landing app has no jsdom and no `@testing-library/*`, so
Svelte components are only reachable through Playwright. That is a deliberate
choice, but nothing records it, so every ticket that touches a component
re-argues it from scratch.

The decision is made: no component test infrastructure. Logic worth pinning gets
extracted into pure functions and unit-tested with Vitest; behaviour that only
exists once the component is mounted gets an e2e spec, stubbing the API with
`page.route` when the seeded schedule cannot produce the case. Ticket 12 is the
worked example of both halves.

Write it into `.claude/rules/testing-policy.md` so it loads with every session.
Keep it to the handful of lines that an agent would otherwise get wrong: where the
seam goes, and that reaching for jsdom or a testing-library dependency is not the
move. State the reason, per the documentation-style rule - a bare prohibition gets
helpfully undone.

Route stubbing is new to the landing suite as of ticket 12 and deserves a sentence
of its own: it is for cases the real seeded schedule cannot reach, not a general
substitute for talking to the API.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] `.claude/rules/testing-policy.md` states the landing component-testing approach and why
- [ ] The note on when API stubbing is appropriate in landing e2e is there
- [ ] No new test dependencies are added to the landing app
- [ ] The addition stays short enough that a reader does not skip it
