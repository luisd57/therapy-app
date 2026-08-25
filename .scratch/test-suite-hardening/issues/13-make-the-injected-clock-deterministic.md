# 13 - Make the injected clock deterministic

**What to build:** the tests that inject a clock actually control time with it, so
a time-dependent behaviour is pinned rather than re-measured on every run.

ADR-0003 gives the suite two independent mechanisms, and says neither subsumes the
other: inject `ClockInterface` so "now" is a value the test chooses, and pin the
process timezone so a missing zone shows up. Ticket 02 covers the expectations
that cannot fail. This ticket covers the injection, which is currently wired and
then defeated.

**Twenty of twenty-three clock stubs hand back the real instant.** Across 22 unit
files, a `ClockInterface` double is created and told to return
`new DateTimeImmutable()`, which is whatever instant the suite happens to run at,
expressed in the +14:00 process zone. Only three supply a value the test controls.

Nothing fails today because most of those tests assert on identity or status
rather than on time. That is the problem in miniature: the tests are insulated
from the clock by not asserting anything it affects, so the injection buys
nothing, and the day someone does assert on time they inherit a non-deterministic
fixture without noticing.

**The codebase already argues this ticket.** `GetNextAvailableWeekHandlerTest`
pins its instant and says why in a comment directly above the stub: a real "now"
would make the expected date depend on when the suite runs. That is the pattern to
spread. `AddScheduleExceptionHandlerTest` and the password-reset handler test are
the other two that already do it.

**Six of forty-eight integration files freeze the clock.** The helper for it
exists and is used correctly where it is used. Beware of judging this by whether a
file mentions the helper: several call it inside one test method while other
methods in the same file remain wall-clock coupled. Check the method, not the file.

**This is not a blanket sweep, and the ticket should not become one.** Plenty of
the remaining files test things time does not reach: a role guard, a validation
error, a 404. Freezing there adds ceremony and pins nothing. The ones that matter
assert on a date, an expiry, an ordering, or a past-versus-future decision. Judge
method by method and say in the pull request why the untouched ones were left, so
the next reader does not redo the analysis.

Note the ordering constraint the helper carries: the clock must be frozen before
the service that reads it is resolved, because handlers resolve it lazily at
dispatch.

**A green run does not verify this ticket, and here the trap is sharper than
usual.** Pinning an instant in all twenty stubs and watching the suite stay green
proves nothing, because most of those tests do not assert on time in the first
place. That is the whole reason they pass today. Swapping a real clock for a
frozen one in a test that never observes the clock changes no outcome and pins no
behaviour, so the work can be done in full and be worth nothing.

The check that separates the two: after pinning, move the instant somewhere that
should matter and confirm something goes red. A stub nobody can make fail by
changing "now" was not worth pinning, and either the test needs an assertion that
depends on time or the injection should come out. Say which, per test.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] No test doubles `ClockInterface` and then returns the real current instant from it
- [ ] Every unit test whose assertions depend on "now" pins an explicit instant, and states an absolute expected value rather than one derived from that instant
- [ ] Integration test methods asserting on a date, an expiry or an ordering freeze the clock before the request, judged per method rather than per file
- [ ] Methods deliberately left unfrozen are named with a reason, rather than silently skipped
- [ ] Moving the frozen instant across a boundary that should matter, such as an expiry, fails the test that covers it
- [ ] Every test whose clock was pinned is shown to fail when the pinned instant moves, or is recorded as one where pinning bought nothing and the injection came out instead
- [ ] Full API suite green

## Comments

**2026-08-25** - Split out of ticket 02 when the post-split re-check showed the
scale: twenty stubs plus a judgement call across the integration suite is its own
ticket rather than a bullet on another one. Entities taking `now` as a constructor
argument are ticket 02's business, not this one's, since there is no clock to
inject there.
