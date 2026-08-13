# 09 - Update and run the landing end-to-end suite

**What to build:** The public reservation flow is verified end to end against the
timezone-aware browser - and confirmed green rather than assumed.

**The suite's colour is currently mostly a function of the weekday.** It was
expected to break when the Slot grid gained 30-minute Start Increments and the
response stopped grouping Slots by date. It did not, because it is coupled to the
flow rather than to times: it asserts that a Slot button exists - matched on the
text "min", which matches any duration - and that a reservation completes.

It does fail on some days, but for an unrelated reason: the modality defect in
ticket 05, which fires only when the first offered Slot falls in an
In-Person-only Schedule Block. **Ticket 05 must land before this ticket's runs
mean anything** - otherwise a red suite is ambiguous between "this ticket's new
assertions failed" and "it was a Thursday evening".

So there is presently **no assertion anywhere** that the zone banner names both
zones, or that the selected Slot and the confirmation show the Practice time
alongside the Requester's. Those are the behaviours a Requester abroad actually
depends on, and they are the substance of this ticket. Adding them is not
optional cleanup on top of a green suite; it is the reason the suite being green
does not yet mean anything about timezones.

The suite depends on seeded schedule data, which is why it is blocked by ticket
08, and on the Slot grid, which is why it is blocked by ticket 04 - until both
land it exercises a grid the practice will never run on.

**Blocked by:** 04 - Switch sessions to 90 minutes; 05 - Modality-first browsing;
08 - Seed the Therapist's real schedule.

**Status:** ready-for-agent

- [ ] The zone banner is asserted to name the Viewer Zone and the Practice Timezone
- [ ] The selected Slot screen is asserted to show both times
- [ ] The confirmation screen is asserted to show both times
- [ ] At least one spec asserts a rendered Slot time, so a zone regression can fail the suite
- [ ] Existing specs updated for the Modality flow that ticket 05 reshapes
- [ ] A Requester can complete a reservation end to end
- [ ] The suite runs green against the real seeded schedule and 90-minute sessions
- [ ] Any failure traced to this work is fixed, not skipped or retried around
