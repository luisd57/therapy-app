# 02 - Make the API fixtures fail when the zone is wrong

**What to build:** the two remaining places where a test derives its expectation
from the fixture it just built are rewritten to assert absolute Instants, so a
timezone regression fails them.

ADR-0003 states the rule: an expected value must come from an independent source,
never from formatting the object under test. Two violations survive.

The Slot value-object suite is the larger one. Every case builds a naive
wall-clock datetime, passes it in, then formats the resulting object back out and
compares the two. Both sides resolve against the process timezone and shift
together, so no case in that file can fail for any timezone the suite runs under
- including the +14:00 the suite deliberately uses to catch exactly this.

The second is a single controller fixture that combines a relative offset with a
wall-clock time. The domain test helper pins that same expression to UTC and
carries a docblock explaining why. The controller test writes the expression bare.

**A green run does not verify this ticket.** Green is what the broken versions
already produce. ADR-0003 records that moving the whole suite to +14:00 produced
zero new failures for this reason. Prove the new assertions bite by running them
against a deliberately wrong zone and watching them go red.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Every expectation in the Slot value-object suite is a hand-written absolute value, not one formatted from the object under test
- [ ] Fixtures that combine a relative offset with a wall-clock time carry an explicit zone, or go through the domain test helper that already pins one
- [ ] The rewritten assertions are shown to fail under a wrong zone, not merely to pass under the current one
- [ ] Full API suite green

## Comments

Touches a test file that `controller-per-action/05` will relocate. One line
either way, so the two are not blocked on each other - whichever lands second
carries the fix forward.
