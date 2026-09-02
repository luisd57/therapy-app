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
entity tests. Reuse the UTC instant helper rather than writing it again.

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

**Blocked by:** 02, which lands the UTC instant helper this reuses.

**Status:** ready-for-agent

- [ ] Every fixture in the three Doctrine repository test files takes a literal Instant as `now`, not the wall clock
- [ ] The daily agenda command test builds its Appointments from the instant it froze, rather than around it
- [ ] The temporary past-Instant guard leaves the whole Integration suite green, not only the controller tests
- [ ] The Therapist schedule repository test is left unchanged, with the reason stated in the pull request
- [ ] Full API suite green
