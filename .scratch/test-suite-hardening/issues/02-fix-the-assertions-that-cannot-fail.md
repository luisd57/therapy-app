# 02 - Make the assertions that cannot fail actually fail

**What to build:** the assertions that hold no matter what the code does are
rewritten to pin absolute values, so a timezone regression or a wrong constant
fails the suite.

ADR-0003 states the rule: an expected value must come from an independent source,
never from formatting the object under test. Four places break it, in three
different ways.

**The Slot value-object suite, wholesale.** Every case builds a naive wall-clock
datetime, passes it in, then formats the resulting object back out and compares
the two. Both sides resolve against the process timezone and shift together, so no
case in that file can fail for any timezone the suite runs under, including the
+14:00 the suite deliberately uses to catch exactly this.

**One assertion that cannot fail at all.** The password-update test on the User
entity asserts the updated Instant is greater than or equal to the old one.
`updatePassword` at worst leaves it unchanged, so the comparison holds even if the
field is never written. The password half of that test is fine. The other half is
inert.

**Three expiry sandwiches.** The Slot Lock, Invitation Token and Password Reset
Token entity tests each read the wall clock before and after creating the entity,
then assert the expiry falls between those two readings plus the TTL. The code
computes the same sum from the same source, so the sandwich cannot disagree. It
would still catch a wrong TTL constant, which makes this weak rather than vacuous.

Note the mechanism before reaching for the frozen-clock helper: these three
entities take `now` as an ordinary constructor argument rather than reading
`ClockInterface`. ADR-0003 covers that case explicitly. The fix is to pass a
literal Instant and assert an exact expiry against it, not to freeze a clock the
entity never consults.

**One rotted fixture.** The therapist booking controller test books a Slot dated
June 2026 and does not freeze the clock. That date was in the future when written
and is now months past. It passes because that path has no past-Instant guard. Add
such a guard, which is a reasonable thing to want, and the test breaks for a reason
unrelated to the change. Several other controller files carry the same date, and
they vary in whether the relevant test pins the clock, so treat the date itself as
the problem rather than this one file.

**A green run does not verify this ticket.** Green is what the broken versions
already produce. ADR-0003 records that moving the whole suite to +14:00 produced
zero new failures for this reason. Prove the new assertions bite by running them
against a deliberately wrong zone and watching them go red.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Every expectation in the Slot value-object suite is a hand-written absolute value, not one formatted from the object under test
- [ ] The password-update test asserts the Instant actually moved, so deleting the assignment fails it
- [ ] Each expiry is asserted as an exact Instant derived from a literal `now`, not as a range built from the wall clock
- [ ] Deleting or halving any TTL constant fails a test that names it
- [ ] No controller fixture depends on a hardcoded date still being in the future
- [ ] The rewritten assertions are shown to fail under a wrong zone, not merely to pass under the current one
- [ ] Full API suite green

## Comments

**2026-08-25** - Rescoped and retitled after PRs #55 to #62. The bare
`'+1 day 10:00'` fixture this ticket originally targeted is gone: the controller
split removed it, and the domain test helper's pinned version is now the only use
of that expression. The three findings added above came from re-checking the suite
against `main` after the split. The original title named the zone, but only the
first finding is about zones, so it now names the shared property instead. The
clock-injection half of ADR-0003 moved to ticket 13.
