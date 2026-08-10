# 09 — Update and run the landing end-to-end suite

**What to build:** The public reservation flow is verified end to end against the
timezone-aware browser — and confirmed green rather than assumed.

**This suite has not been run in the session that produced this work.** It was
written against a Slot grid that has since changed to 30-minute Start Increments,
against a response shape that no longer groups Slots by date, and against a
browser that now carries a zone banner, a Modality choice and dual-time display.
Failures should be assumed to belong to this branch. Treat the first run as
diagnostic, not as a regression signal.

The suite depends on seeded schedule data, which is why it is blocked by ticket
08, and on the Slot grid, which is why it is blocked by ticket 04. It exercises
the Modality flow that ticket 05 reshapes.

Beyond repairing existing assertions, add coverage for the behaviour a Requester
abroad depends on: that the zone banner names both zones, and that the selected
Slot and the confirmation both show the Practice time as well as the Requester's.

**Blocked by:** 04 — Switch sessions to 90 minutes; 05 — Modality-first browsing;
08 — Seed the Therapist's real schedule.

**Status:** ready-for-agent

- [ ] Existing specs updated for the new Slot grid, response shape and Modality flow
- [ ] The zone banner is asserted to name the Viewer Zone and the Practice Timezone
- [ ] The selected Slot screen is asserted to show both times
- [ ] The confirmation screen is asserted to show both times
- [ ] A Requester can complete a reservation end to end
- [ ] The suite runs green in its containerised environment
- [ ] Any failure traced to this branch is fixed, not skipped or retried around
