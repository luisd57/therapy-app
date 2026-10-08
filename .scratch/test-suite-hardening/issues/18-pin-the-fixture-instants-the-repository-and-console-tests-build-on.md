# 18 - Pin the fixture Instants the repository and console tests build on

**What to build:** the integration tests that save an entity straight to the
database stop depending on a hardcoded date still being in the future, so a
past-Instant guard can be added without breaking them for an unrelated reason.

Fifteen tests build an entity with `now: new DateTimeImmutable()` and a Slot dated
June 2026. The `now` comes from the wall clock, the Slot does not, and June 2026
stopped being in the future. Nothing fails today because no such guard exists. Add
one, which is a reasonable thing to want, and these fail.

**This is the gap between tickets 02 and 13.** Ticket 02 fixed the same defect but
its acceptance criterion says "controller fixture", so it stopped at the
controllers. Ticket 13 disclaims these in its own comment: entities taking `now` as
an ordinary constructor argument are not the injected-clock problem, because there
is no clock to inject. Neither ticket owns them, which is why they are still here.

**Freezing the clock is the wrong fix for the fourteen repository tests.** They do
not read `ClockInterface` at all, so the freeze helper never reaches the fixture.
Each one needs a literal Instant passed as `now`, the way ticket 02 fixed the
entity tests. Reuse `UsesUtcInstants::utc()` from `API/tests/Helper/` (there as of
2026-09-22, added by ticket 02) rather than writing it again.

**The console test fails for a different reason and needs a different fix.** The
daily agenda command test that pins the Therapist's Day key does freeze the clock,
correctly, and the assertion it makes is sound. Its confirmed-Appointment helper is
what bypasses the freeze, building each Appointment on the wall clock at four call
sites. Thread the frozen instant through that helper rather than editing the test
body.

**Leave the Therapist schedule repository test alone.** It holds seven wall-clock
constructions and survived the probe below, because a Schedule Block carries
Wall-clock rules rather than Instants. Nothing in it can be in the past. Say so in
the pull request so the next reader does not redo the analysis.

**Neither of ticket 15's proposed rules would catch this.** The first sees only
`ClockInterface` doubles, and the second only a single-argument `DateTimeImmutable`
built from a string literal. These are zero-argument constructions, so a rule that
would catch them is worth proposing to 15 while this ticket is open.

**A green run does not verify this ticket, for the reason ticket 02 records.**
Green is what the broken version already produces. Verify by adding a temporary
guard that refuses an Appointment, a Slot Lock or a Schedule Exception starting
before `now`, running the Integration suite, then removing the guard. That probe
produced the counts below.

**Measured 2026-09-02 on the branch for ticket 02.** Fourteen failures across the
Appointment repository test (6 of its 10), the Schedule Exception repository test
(4 of 5) and the Slot Lock repository test (4 of 6), plus the one console test. All
127 controller tests passed. Re-measure before picking this up.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Every fixture in the three Doctrine repository test files takes a literal Instant as `now`, not the wall clock
- [ ] The daily agenda command test builds its Appointments from the instant it froze, rather than around it
- [ ] The temporary past-Instant guard leaves the whole Integration suite green, not only the controller tests
- [ ] The Therapist schedule repository test is left unchanged, with the reason stated in the pull request
- [ ] Full API suite green

**2026-09-28** - Ticket 13 froze the clock in the Slot Lock repository test and gave
literal instants to the three methods whose query reads it:
`testFindActiveByTimeSlotReturnsActiveLock`, `testFindActiveByTimeSlotIgnoresExpiredLock`
and `testDeleteExpiredRemovesOnlyExpiredLocks`. Their Slot start times are still naive
June 2026 strings, so they stay in scope here along with the file's other three
methods. Re-measure before picking this up.

**2026-10-07** - Ticket 15 gave every naive string literal in the three repository tests a zone, so
the Slot start times above are no longer in scope here. The wall-clock `now` arguments still are.
Ticket 15 also could not write the rule this ticket proposes: a ban on zero-argument
`new DateTimeImmutable()` in `App\Tests` is red on 167 sites, including the Therapist schedule
repository test this ticket says to leave alone. It needs a ticket of its own once this one lands,
and a way to say "time does not matter here" that the rule can allow.

**2026-10-08** - Re-measured before starting: the probe failed 15 Integration tests, split 8 in the
Appointment repository test, 4 in the Schedule Exception one, 2 in the Slot Lock one and 1 console
test. The split moved because more fixture dates have passed since September.

The console criterion cannot be met as written. The test freezes the clock at 2026-06-16 02:00 UTC
and saves an Appointment at 09:00 on the therapist's 15 June, which is before that instant.
Threading the frozen instant through the helper would build an Appointment requested after its own
start, the exact fixture this ticket removes. The helper takes a pinned literal before every Slot
instead. Read the second checkbox as "the helper no longer reads the wall clock".

Still open after this ticket: the rule against zero-argument `new DateTimeImmutable()` in
`App\Tests` that the 2026-10-07 comment describes. It needs its own ticket.
