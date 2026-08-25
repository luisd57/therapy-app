# 14 - Lint the test suites

**What to build:** the mistakes that make a frontend test worthless are caught by
the linter instead of by whoever reviews the pull request.

The Playwright and Vitest ecosystems already ship the rules. Nothing here needs
writing, only enabling: `expect-expect` for a test that asserts nothing,
`no-skipped-test` and `no-focused-test` for a suite that quietly stops running,
`no-conditional-in-test` for a test that branches and therefore proves different
things on different days, `no-wait-for-timeout` and `no-force-option` for the two
classic sources of flake, `no-element-handle` and `valid-expect`.

**Both suites pass every one of these today.** Zero `.skip`, zero `.only`, zero
`.fixme`, zero `waitForTimeout`, zero `page.pause`, zero `force: true`, zero
element handles. So this is not a migration. It is locking a state that is already
good and will not stay that way by itself.

**One rule will bite, and it should.** `expect-expect` flags the login spec, whose
only assertion lives inside the shared `loginAsTherapist` helper. Add an assertion
to the test body rather than allowlisting the helper by name. The rule is right:
a test body with no assertion in it reads as assertion-free to every future
reader, and an allowlist would weaken the rule for every helper written after it.

**What this cannot catch.** A test can satisfy every rule here and still assert
nothing meaningful. `expect-expect` counts assertions, it does not weigh them, and
a locator matching the wrong element passes just as quietly. This removes a class
of mistake, not the need to think.

**Blocked by:** 10. That ticket is what brings `e2e/**` into lint scope, and the
landing app has no ESLint configuration at all today, so there is nothing to hang
these rules on until it lands.

**Status:** ready-for-agent

- [ ] Both e2e directories are linted with the Playwright rules, and the landing unit tests with the Vitest ones
- [ ] The login spec asserts something in its own body, rather than the helper being allowlisted
- [ ] A deliberately skipped test and a test with no assertion each fail the pipeline, proving the rules are connected
- [ ] Any rule deliberately left off carries its reason in the config
- [ ] Both e2e suites still green, and dashboard lint and build green
