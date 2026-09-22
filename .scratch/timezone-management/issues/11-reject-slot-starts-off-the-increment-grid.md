# 11 - Reject Slot starts that are not on the increment grid

**What to build:** A Slot Lock or Appointment request can only name a Slot start
the system actually offers.

Taking a Slot Lock does not currently check that the requested Instant is a legal
Slot start. A client can lock an arbitrary time - 09:07, say - and hold it. The
Appointment request path does verify the Instant against computed Slots, so this
does not produce a bookable Appointment, but it does let a caller hold a window
that was never offered. Locks never hide Slots from the grid, but they conflict
with each other, so while it is active every attempt to lock an overlapping
genuine Slot is refused.

Pre-existing rather than introduced here, and previously judged orthogonal. It is
listed now because Start Increments made it materially more exposed: the grid is
denser, so an off-grid lock overlaps more real Slot starts than it used to.

Lowest priority in this effort. Nothing depends on it, and it blocks nothing.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Taking a Slot Lock validates that the requested Instant is an offered Slot start
- [ ] An off-grid Instant is rejected with the same error the request path already uses for an unavailable Slot
- [ ] A legal Slot start still locks successfully
- [ ] The check honours Start Increment rather than assuming a back-to-back grid
- [ ] A test covers an Instant that falls inside a Schedule Block but between two offered starts
- [ ] Full API suite green
