# Tests control time two ways: a hostile pinned zone, and an injected clock

Status: accepted

## Decision

Both mechanisms are in use, because they answer different questions.

**A pinned process timezone catches implicit-local bugs.** `phpunit.xml.dist`
sets `date.timezone` to `Pacific/Kiritimati` (UTC+14, and a calendar day ahead).
Production runs UTC. If the suite also ran UTC, code that constructs a
`DateTimeImmutable` without naming a zone would pass by coincidence — UTC in, UTC
out. At +14 any implicit-local assumption produces a visibly wrong instant.
`tests/Unit/TimezoneGuardTest.php` asserts the setting is actually in effect, so
the guard cannot silently stop applying.

**An injected clock makes "now" deterministic.** `ClockInterface` is a
constructor dependency everywhere it is needed. Integration tests call
`ApiTestCase::freezeClock('2026-05-30 09:00:00')`, which swaps the container's
clock for a `MockClock`; the string is read as UTC unless it carries its own
offset. Unit tests either mock `ClockInterface` or, in the case of
`AvailabilityComputer`, pass `now` as an ordinary parameter — the computer takes
no clock at all, which is why its tests need no mocking framework to control time.

## Why not just one

A pinned zone alone cannot make "now" reproducible; a frozen clock alone cannot
reveal that a zone was never specified. Neither subsumes the other.

## Considered and rejected

**Running the suite at UTC to match production.** Rejected: it is precisely the
configuration under which the bug class is invisible.

**Running the suite at `America/Caracas` to match the practice.** Rejected for
the same reason in the other direction — code that implicitly assumed the
practice zone would pass.

**`Clock::set()` / a global static clock.** Rejected: the project injects
dependencies, and a global would leak between tests sharing a kernel.

## Consequences, including an honest limitation

Switching the suite to `Pacific/Kiritimati` produced **zero new failures** at the
time it was introduced — all 525 tests still passed. That was not the guard
working; it was the guard being unable to see anything. The existing date tests
were self-consistent: they built fixtures with naive `DateTimeImmutable` and then
derived their expected values by formatting those same fixtures, so both sides
shifted together and could never disagree.

The guard only started catching things once `AvailabilityComputerTest` was
rewritten to assert **hand-written absolute UTC instants** rather than
re-formatted fixtures. The lesson, and the rule for new tests here: an expected
value must come from an independent source — a literal, a worked example — never
from formatting the object under test.

Two fixtures needed pinning when the zone flipped, both because they mixed a
relative offset with a wall-clock time (`'+1 day 10:00'`), which resolves against
the process zone: `DomainTestHelper::defaultStartTime()` and
`ApiTestCase::freezeClock()`. Pure relative durations (`'-2 hours'`) are
instant-correct in any zone and were left alone.

Debugging a failure under +14 is mildly disorienting — dates in assertion output
are often a day ahead of the fixture. Accepted; the alternative is a suite that
cannot fail on the thing it exists to check.
