# 01 - Wire the real Start Increment into the availability unit tests

**What to build:** the unit tests that compute availability exercise the Slot grid
the practice actually runs on, so a change to the grid can fail them.

Three of them build the slot generation rules with a Start Increment equal to the
session duration. Production offers a start every 30 minutes for a 50-minute
session, so those tests walk a back-to-back grid that has never existed. The
factory takes duration and Start Increment as two adjacent integers of the same
type, which is why passing the same value twice reads as correct and is not.

Nothing fails today because the handlers under test stub the availability
computer, so the rules they build are constructed and never consulted. That is
the defect: the wiring is asserted by nobody.

**Do this before ticket 04 in `timezone-management`.** That ticket moves sessions
to 90 minutes and explicitly keeps starts at 30. If these fixtures still say
"increment equals duration" when it lands, they will be updated to 90 and 90 and
the wrong grid survives the change that was supposed to correct it.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] No test constructs the slot generation rules with duration and Start Increment set to the same value
- [ ] The two values are named at the point of use, so a reader can tell which is which without checking the constructor signature
- [ ] Where the container is available the configured values are read rather than repeated, per the rule in `CLAUDE.md`. Pure unit tests may use literals, provided the two differ as production's do
- [ ] A test fails if duration and Start Increment are swapped at the call site, which today's fixtures cannot detect
- [ ] Full API suite green
