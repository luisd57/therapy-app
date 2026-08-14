# 04 - Switch sessions to 90 minutes

**What to build:** Slots reflect the Therapist's real session length of one hour
thirty, rather than the 50 minutes the system was configured with.

**This is not timezone work.** It is a configuration value change, separated so
it neither blocks nor is blocked by the timezone fixes. The Start Increment half
of this pair - offering starts every 30 minutes - already shipped and is live.

The value appears in several environment configurations and they must move
together, including the ones continuous integration reads. A mismatch between
environments produces a Slot grid that differs between local and CI with no
obvious cause.

Session length is not a free parameter: it interacts with every Schedule Block
boundary. At 90 minutes her Tuesday window of 06:30 to 10:30 offers six candidate
starts and caps at two non-overlapping sessions, which is what she described.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Session duration is 90 minutes in every environment configuration, including continuous integration
- [ ] Slot length in API responses is 90 minutes
- [ ] Slots continue to be offered every 30 minutes
- [ ] A Slot is still only offered when a full session fits inside its Schedule Block
- [ ] Test fixtures that hardcode the old duration are updated rather than worked around
- [ ] Any test asserting specific Slot times reflects the new grid
- [ ] Full API suite green
