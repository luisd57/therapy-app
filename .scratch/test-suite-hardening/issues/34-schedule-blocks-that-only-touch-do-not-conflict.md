# 34 - Schedule Blocks that only touch do not conflict

**What to build:** tests that fail when the Therapist cannot put two Schedule
Blocks back to back on the same day, when creating one or when updating one.

A block's end means sessions must finish by then, so 08:00 to 10:00 and 10:00
to 12:00 share no time. Both handlers allow that today. No test has two blocks
on one day that do not overlap (2026-10-09): the success test has no existing
block, and the conflict test has a true overlap. A rule that refused touching
blocks, or any second block on the day, would pass the suite.

**The rule is written twice,** one private copy in the handler that creates a
block and one in the handler that updates it. Nothing ties them together, so
each needs its own cases. Merging the copies is not this ticket.

**Mutants this should kill:** the three kind 1 rows for
`src/Application/Appointment/Handler/SetTherapistScheduleHandler.php` and the
three for
`src/Application/Appointment/Handler/UpdateTherapistScheduleHandler.php`, in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Creating a block that starts exactly when an existing block on that day ends succeeds
- [ ] Creating a block that ends exactly when an existing block on that day starts succeeds
- [ ] Updating a block so it touches another block on that day succeeds, once on each side
- [ ] A one-file Infection run on each handler no longer reports its three rows as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
