# 13 - Make the injected clock deterministic

**What to build:** the tests that inject a clock actually control time with it,
so a time-dependent behaviour is pinned rather than re-measured on every run.

ADR-0003 gives the suite two mechanisms for controlling time: inject
`ClockInterface` so "now" is a value the test chooses, and pin fixtures to an
explicit zone. Ticket 02 is the fixture half. This is the clock half, and it is
currently built and then defeated.

**Eighteen of nineteen unit tests hand the mock the real clock.** They create a
`ClockInterface` double and tell it to return `new DateTimeImmutable()`, which is
whatever instant the test happens to run at, expressed in the +14:00 process zone.
Exactly one test pins an instant. The seam exists, the double is wired, and the
value flowing through it is the thing the seam was meant to replace.

Nothing fails today because most of those tests assert on identity or status
rather than on time. That is the problem in miniature: the tests are insulated
from the clock by not asserting anything it affects, so the injection buys
nothing, and the day someone does assert on time they inherit a non-deterministic
fixture without noticing.

**Six of forty-eight integration files freeze the clock.** The helper for it
already exists and is used correctly where it is used.

**This is not a blanket sweep, and the ticket should not become one.** Plenty of
those forty-two files test things time does not reach: a role guard, a validation
error, a 404. Freezing there adds ceremony and pins nothing. The ones that matter
are the files asserting on a date, an expiry, an ordering, or a past-versus-future
decision. Judge file by file and say in the pull request why the untouched ones
were left, so the next reader does not redo the analysis.

Note the ordering constraint the helper carries: the clock must be frozen before
the service that reads it is resolved, because handlers resolve it lazily at
dispatch.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] No test doubles `ClockInterface` and then returns the real current instant from it
- [ ] Every unit test whose assertions depend on "now" pins an explicit instant, and the assertion states an absolute value rather than one derived from that instant
- [ ] Integration files asserting on a date, an expiry or an ordering freeze the clock before the request
- [ ] Files deliberately left unfrozen are named with a reason, rather than silently skipped
- [ ] Moving the frozen instant across a boundary that should matter, such as an expiry, fails the test that covers it
- [ ] Full API suite green

## Comments

**2026-08-25** - Split out of ticket 02 when the post-split re-check showed the
scale: this is eighteen unit tests plus a judgement call over the forty-two
integration files that do not freeze, which is its own ticket rather than a bullet
on another one.
The two together are the two halves ADR-0003 describes.
