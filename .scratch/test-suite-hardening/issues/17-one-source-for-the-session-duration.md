# 17 - One source for the session duration

**What to build:** every part of the system that needs the session duration asks
the same place for it, so the configured value has one consumer instead of three.

Counted 2026-08-29: three services already take the slot generation rules and
read the session duration from them. Two do not, the one that takes a Slot Lock
while a Requester fills in the form and the one that creates the Appointment when
the Requester submits it. Both build their own time window from a raw duration
handed to them separately. Re-measure before acting on those counts.

The cost is that the configuration wires the same value into three different
services, while the comment above the rules factory claims it is the single
source of truth. It is not, and nothing fails when the claim stops holding. A
change to how the session duration is derived has to be made in three places and
will be made in one.

There is a second defect in the same area. The Slot Lock service ends its
constructor with two integers of the same type, the session duration and the lock
lifetime. Its test supplies both positionally, so a swap reads as correct and is
not. This is the shape ticket 01 closed for the rules factory (resolved
2026-08-29). Taking the session duration off that constructor leaves one
integer and removes the pair rather than renaming around it.

While the rules are being touched, the named constructor that builds them puts the
practice time zone between the session duration and the Start Increment. Move the
two related values next to each other. This changes no behaviour and is folded in
here because it is the same read of the same class.

**Do this before `timezone-management/04`,** which moves sessions to 90 minutes
and was open as of 2026-08-29. That ticket has to update every place that pins the
old duration. Two of those places disappear here.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] The Slot Lock and the Appointment both take their length from the rules rather than from a duration passed to them separately
- [ ] The configured session duration is wired into exactly one service
- [ ] No service takes a raw session duration alongside another integer of the same type
- [ ] A test fails if the lock lifetime and the session duration are exchanged at the point where the Slot Lock service is built
- [ ] A Slot Lock and an Appointment created through the API both cover a window of the configured duration, asserted against the configured value rather than a repeated literal
- [ ] The named constructor for the rules keeps the session duration and the Start Increment adjacent
- [ ] Full API suite green
