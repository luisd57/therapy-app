# 14 - Lint the test suites

**What to build:** the mistakes that make a frontend test worthless are caught by
the linter instead of by whoever reviews the pull request.

The Playwright and Vitest ecosystems already ship the rules, so nothing here needs
writing, only enabling. The ones that map onto what the audit found: a test that
asserts nothing, a suite that quietly stops running because something was skipped
or focused, a test that branches and so proves different things on different days,
hardcoded waits and forced clicks, and raw element handles.

Confirm the exact rule names against the plugin version you install rather than
trusting this list. They are named from memory and the plugin has renamed rules
between majors.

**Mostly lock-in, with two known hits.** Neither suite contains a skipped test, a
focused test, a hardcoded wait, a forced click or an element handle, so those rules
go on green and stay that way. Two will fire immediately:

- **A test with no assertion in its body.** The login spec's first test asserts
  nothing directly: its only check sits inside the shared `loginAsTherapist`
  helper. Fix the test rather than allowlisting the helper by name. The rule is
  right, a body with no assertion reads as assertion-free to every later reader,
  and an allowlist would weaken it for every helper written afterwards.
- **A conditional inside a test body.** `modality-first.spec.ts` filters requests
  with an `if` inside a `page.on('request')` callback. That is a filter rather than
  a branch in test logic, so the rule is arguably wrong here. Either lift the
  filter out of the test or turn that one rule off with the reason written in the
  config. Do not leave it flagged and ignored.

**What this cannot catch.** A test can satisfy every rule here and still assert
nothing meaningful. Counting assertions is not weighing them, and a locator
matching the wrong element passes just as quietly. This removes a class of
mistake, not the need to think.

**Blocked by:** 10. That ticket is what brings `e2e/**` into lint scope, and the
landing app has no ESLint configuration at all today, so there is nothing to hang
these rules on until it lands.

**Status:** ready-for-agent

- [ ] Both e2e directories are linted with the Playwright rules, and the landing unit tests with the Vitest ones
- [ ] The login spec asserts something in its own body, rather than the helper being allowlisted
- [ ] The request filter in the modality spec is either restructured or exempted with its reason in the config, not left flagged
- [ ] A deliberately skipped test and a test with no assertion each fail the pipeline, proving the rules are connected
- [ ] Any other rule deliberately left off carries its reason in the config
- [ ] Both e2e suites still green, and dashboard lint and build green
