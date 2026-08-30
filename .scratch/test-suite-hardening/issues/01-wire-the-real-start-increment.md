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

**Status:** resolved

**Resolved by:** [PR #72](https://github.com/luisd57/therapy-app/pull/72)

- [x] No test that stands in for the configured grid constructs the slot generation rules with duration and Start Increment set to the same value. Reworded 2026-08-29: as first written this could not be ticked. `AvailabilityComputerTest` builds equal values at 16 sites, 11 of them explicit and 5 through the increment's default, and every one of them pins the back-to-back grid contract on purpose. That grid is a supported configuration, not the defect this ticket names
- [x] The two values are named at the point of use, so a reader can tell which is which without checking the constructor signature
- [x] Where the container is available the configured values are read rather than repeated, per the rule in `CLAUDE.md`. Pure unit tests may use literals, provided the two differ as production's do
- [x] A test fails if duration and Start Increment are swapped at the call site, which today's fixtures cannot detect. Verified by breaking it: swapping the two constructor parameters fails the new unit test while the three handler tests stay green, and swapping the two parameter values in `services.yaml` fails the new integration test. Note the limit, at the three handler call sites a swap is now unlikely rather than detectable, because named arguments prevent it and the stubbed availability computer still cannot observe it
- [x] Full API suite green
