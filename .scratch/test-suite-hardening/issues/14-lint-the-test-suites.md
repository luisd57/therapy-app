# 14 - Lint the test suites

> Frozen record, resolved 2026-10-06.

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

Ticket 10 comes first: it is what brings `e2e/**` into lint scope, and the landing
app has no ESLint configuration at all today, so there is nothing to hang these
rules on until it lands.

**Blocked by:** 10

**Status:** resolved

**Resolved by:** [PR #103](https://github.com/luisd57/therapy-app/pull/103)

- [x] Both e2e directories are linted with the Playwright rules, and the landing unit tests with the Vitest ones
- [x] The login spec asserts something in its own body, rather than the helper being allowlisted
- [x] The request filter in the modality spec is either restructured or exempted with its reason in the config, not left flagged
- [x] A deliberately skipped test and a test with no assertion each fail the pipeline, proving the rules are connected
- [x] Any other rule deliberately left off carries its reason in the config
- [x] Both e2e suites still green, and dashboard lint and build green

## Comments

**2026-10-06** - Installed `eslint-plugin-playwright` 2.12.1 and `@vitest/eslint-plugin` 1.6.27.
First run: one error, the login spec's `expect-expect`. Nothing else fired in either app.

**The request filter was never flagged.** In this plugin version `no-conditional-in-test`
ignores a conditional inside a nested callback, so the `if` in the `page.on('request')` handler
passes. An `if`, a ternary or an `&&` directly in a test body does fail. The modality spec is
unchanged and no exemption was written.

**Most of the rules this ticket is about ship as `warn`.** `expect-expect`, `no-skipped-test`,
`no-wait-for-timeout`, `no-force-option` and `no-element-handle` in the Playwright preset, and
`no-disabled-tests` in the Vitest one. A warning does not fail the pipeline, so both configs
raise every enabled rule to `error` from the plugin's own rule map. That makes the preset's
style rules (`prefer-hooks-on-top`, `consistent-spacing-between-blocks`) blocking too.

`test.fixme` passes `no-skipped-test` by default. `disallowFixme` is on in both configs.
Vitest's `no-conditional-in-test` is outside its recommended set and is turned on by name.

**Gate proven connected in CI on PR #103.** A planted `test.skip` in `dashboard/e2e/` failed
the `test` job at "Lint dashboard" on `playwright/no-skipped-test` (run 37521212105). A planted
assertion-free test in `landing/e2e/` and an `it.skip` in `landing/src/utils/` failed it at
"Lint landing" on `playwright/expect-expect` and `vitest/no-disabled-tests` (run 37522106670).
All three removed.

**Gaps left open,** each probed and passing lint today:

- `it.todo`, `it.skipIf(true)` and `it.runIf(false)` in the landing unit tests.
- A ternary in a unit test. The Vitest rule only catches `if`.
- A raw `setTimeout` wait in e2e. Only `page.waitForTimeout` is caught.
- An inline `eslint-disable` comment.
- A `.js` or `.mjs` spec in either e2e directory, as ticket 10 recorded.
- The dashboard has no unit specs, so `dashboard/src/**/*.spec.ts` has no test rules yet.
