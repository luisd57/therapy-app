# 13 - Make the injected clock deterministic

> Frozen record, resolved 2026-09-28.

**What to build:** the tests that inject a clock actually control time with it, so
a time-dependent behaviour is pinned rather than re-measured on every run.

ADR-0003 gives the suite two independent mechanisms, and says neither subsumes the
other: inject `ClockInterface` so "now" is a value the test chooses, and pin the
process timezone so a missing zone shows up. Ticket 02 covers the expectations
that cannot fail. This ticket covers the injection, which is currently wired and
then defeated.

**Twenty of twenty-three clock stubs hand back the real instant** (re-measured
2026-09-22, unchanged since 2026-08-30). Across 22 unit files, a `ClockInterface`
double is created and told to return
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
spread. `AddScheduleExceptionHandlerTest` is the only other file that does it
throughout. `ResetPasswordHandlerTest` does both, pinning in one test and taking
the real clock in another, which is the mixed state to expect across the suite.

**Eight of fifty-four integration files call the freeze helper, measured
2026-09-22** (six of fifty on 2026-08-30). The helper exists and is used correctly
where it is used. Beware of judging this
by whether a file mentions the helper: several call it inside one test method while
other methods in the same file remain wall-clock coupled. Check the method, not the
file.

**This is not a blanket sweep, and the ticket should not become one.** Plenty of
the remaining files test things time does not reach: a role guard, a validation
error, a 404. Freezing there adds ceremony and pins nothing. The ones that matter
assert on a date, an expiry, an ordering, or a past-versus-future decision. Judge
method by method and say in the pull request why the untouched ones were left, so
the next reader does not redo the analysis.

Note the ordering constraint the helper carries: the clock must be frozen before
the service that reads it is resolved, because handlers resolve it lazily at
dispatch.

**A green run does not verify this ticket.** These twenty tests split two ways and
the suite cannot tell you which is which. Some already depend on time and will go
red the moment a pinned instant is wrong. Others assert only on identity or
status, and pinning their clock changes no outcome whatsoever. Move the instant
after pinning it and confirm something goes red. Where nothing does, name the test
in the pull request rather than counting it done: the clock stays injected, since
the handler requires it, but nothing about that test is any more pinned than
before.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** PR pending

- [x] No test doubles `ClockInterface` and then returns the real current instant from it. All 23 stubs return a literal instant
- [x] Every unit test whose assertions depend on "now" pins an explicit instant, and states an absolute expected value rather than one derived from that instant. Went further than asked: each of the 20 handlers now also asserts its now-derived output (a stamp, an expiry, the instant handed to the availability computer) against a literal
- [x] Integration test methods asserting on a date, an expiry or an ordering freeze the clock before the request, judged per method rather than per file. 15 methods in 6 files, with their fixtures moved to literal instants
- [x] Methods deliberately left unfrozen are named with a reason, rather than silently skipped. In the pull request, per file
- [x] Each pinned test is shown to go red when the instant moves, and any that cannot are named in the pull request rather than counted as done. Every one of the 20 unit files and 6 integration files goes red under a moved instant. The tests that stay green are named in the pull request
- [x] Full API suite green. 825 tests, 2956 assertions

## Comments

**2026-08-25** - Split out of ticket 02 when the post-split re-check showed the
scale: twenty stubs plus a judgement call across the integration suite is its own
ticket rather than a bullet on another one. Entities taking `now` as a constructor
argument are ticket 02's business, not this one's, since there is no clock to
inject there.

**2026-08-30** - Re-measured after PR #74. The integration denominator moved from
48 to 50 across PRs #72 and #74. The six freezing files are the same six.
`EntityRelationsTest`, added by #74, asserts on identity and nullity rather than on
dates, so it is one of the files that correctly needs no freeze. The unit-side
counts are unchanged at 20 of 23 stubs across 22 files, pinned in
`AddScheduleExceptionHandlerTest`, `GetNextAvailableWeekHandlerTest` and one of the
two in `ResetPasswordHandlerTest`.

**2026-09-02** - The entities this ticket disclaims above now have a home. Ticket
18 covers the fifteen integration tests that pass a wall-clock `now` to an entity
constructor against a fixture Slot dated June 2026. They are out of scope here for
the reason already stated, there being no clock to inject, and were out of scope
for ticket 02 because its criterion names controller fixtures only.

**2026-09-28** - Resolved. Measured on the branch: 0 of 23 unit clock stubs return the
real instant, and 16 of 58 integration files call the freeze helper, up from 10.

Pinning a stub is not enough on its own. The token fixtures in `DomainTestHelper`
expired relative to the wall clock, so against a clock pinned in the past an "expired"
token read as valid. `createExpiredInvitation`, `createExpiredPasswordResetToken` and
`SeedsAuthFixtures::seedInvitation` now take a `now`, and every pinned test passes them
a literal rather than the pinned clock itself. Keep them separate literals: a fixture
that follows the clock moves with it, and the red proof below could not see anything.

The red proof moved only the stub or the `freezeClock` argument and left the fixtures
where they were: unit stubs to 12:30, 10:30 and two days later, integration freezes to
07:30 and two days later. All 26 files went red at least once.

One integration method must stay on the real clock.
`ResetPasswordControllerTest::testResetInvalidatesSessionsIssuedBeforeIt` compares a
JWT `iat`, which Lexik takes from `time()`, with a cutoff the handler takes from the
container clock. No frozen instant satisfies both. The whole JWT path ignores the
container clock, which is why freezing never breaks a login in these tests.

The three Slot Lock repository methods that read the clock got literal instants here,
because the freeze needs them. The file's other three methods are still ticket 18's.
