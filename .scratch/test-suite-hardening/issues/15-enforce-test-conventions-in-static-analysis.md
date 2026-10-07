# 15 - Enforce the test conventions in static analysis

**What to build:** the API testing conventions that are currently prose become
rules that fail the build, so a new test cannot reintroduce a defect this effort
removed.

Custom PHPStan rules over `API/tests/`, deliberately chosen over a guard test that
greps its own source. Regex reads text. PHPStan reads the syntax tree, so it sees
a naive `DateTimeImmutable` construction regardless of how the line is formatted
and cannot be fooled by a mention in a docblock. That distinction is not academic
here: an audit of this suite reported the wrong figure twice by matching text that
looked right.

Rules worth writing, roughly in value order. Confirm what PHPStan can express
against the version ticket 11 installs, rather than assuming this list is buildable
as written:

- A `ClockInterface` double whose `now()` returns a `DateTimeImmutable` built with
  no arguments. This is the whole of ticket 13's defect, expressed as a shape.
- A single-argument `DateTimeImmutable` built from a string literal that carries no
  offset. ADR-0003's rule, mechanised.
- `assertEquals` and `assertNotEquals` anywhere. The suite has none today and the
  reason is written down: the date comparator sees the instant, not the offset.
- `markTestSkipped` and `markTestIncomplete`, so the suite cannot shrink quietly.
- `sleep` and `usleep`.

**The first two rules would land red today.** Measured 2026-09-22: twenty clock
stubs return the real instant, and roughly 96 `DateTimeImmutable` string literals
in `API/tests/` carry no offset, led by `ScheduleExceptionTest` and the three Doctrine
repository tests. Ticket 13 clears the stubs. Ticket 02 cleared only the Slot
value-object suite, and no ticket yet owns the remaining literals, so the second
rule needs one before it can land. Add each rule as the final act of the ticket
that clears its violations, and no allowlist is needed. That also avoids the trap the
`PENDING_CONVERSION` list hit, where emptying a loop-and-assert ratchet leaves a
zero-assertion test that `failOnRisky` then fails.

**This ticket depends on a decision ticket 11 has not made yet.** Ticket 11 allows
the tests directory to be either in analysis scope or deliberately excluded. If it
excludes `API/tests/`, these rules have nowhere to run and that call has to be
revisited before this ticket can start.

**What this cannot catch.** Shapes, not meaning. A tautology like the Slot
value-object one ticket 02 fixed would pass every rule above, because its defect is that both sides of a comparison
move together, which is a fact about what the test means rather than how it is
written. One test file per production class is also out of reach here, being file
layout rather than syntax. Prose and review still carry those.

**Blocked by:** 11

**Status:** ready-for-agent

- [x] The clock-stub rule and the naive-literal rule each exist and are scoped to the tests directory
- [x] Each rule is added by the ticket that clears its existing violations, so none lands against a red suite and no allowlist is introduced
- [x] The banned-assertion and banned-function rules cover `assertEquals`, `assertNotEquals`, `markTestSkipped`, `markTestIncomplete`, `sleep` and `usleep`
- [x] Every rule carries a message naming the convention and where it is written down, not just the violation
- [ ] Introducing each violation deliberately fails the pipeline, one at a time, proving no rule is inert
- [ ] Full pipeline green

## Comments

**2026-09-27** - Unblocked. Ticket 11 kept `API/tests/` in scope at level 10, so the rules have
somewhere to run. See ADR-0008. `phpstan.dist.neon` has no `rules:` section yet, and
`reportIgnoresWithoutComments` is on, so any exemption a rule needs must carry its reason inline.

**2026-10-07** - Built on PHPStan 2.2.16. Four rule classes in `API/tests/PHPStan/Rule/`, each with a
`RuleTestCase` in the Unit suite: `BannedTestCallRule`, `UnpinnedClockStubRule`,
`HandWrittenClockRule` and `NaiveDateTimeLiteralRule`. All are scoped by namespace to `App\Tests`.

The first three landed green, there being nothing to clear. The naive-literal rule found 96 literals
in 14 files, matching the estimate above. This ticket cleared them itself, in a commit before the
rule: 91 became `utc()`, the four `'tomorrow 09:00:00'` became a literal a day after the pinned
clock, and `TimezoneGuardTest` keeps its one on purpose behind the only ignore. No allowlist.

Clearing them was not neutral. Six `AppointmentRequestServiceTest` methods went red, because the
test sent a wire string with no offset and built its fixture the same way, so both were read at
+14:00 and agreed. The controllers already refuse such a string. The wire strings carry an offset
now. `PatientRequestAppointmentHandlerTest` and `RequestAppointmentHandlerTest` still pass a naive
wire string to a mocked service, which no rule here sees.

The clock rule also rejects `willReturnCallback`, `createConfiguredMock`, `createConfiguredStub` and
a hand-written clock class, none of which the suite uses, so one shape is left to check. A stub fed
through a variable still gets past it. The fix for that is the zero-argument rule ticket 18 asks
for, which is red on 167 sites today and needs its own ticket after 18.

Each of the eight plants (six banned names, one unpinned stub, one naive literal) failed
`phpstan analyse` locally, one at a time, each with its own message. The red run in CI is recorded
in the pull request.
