# 01 - One source for the session length

**What to build:** every part of the system that needs the session length asks the
same place for it, so the configured value has one consumer instead of three.

Three services already take the slot generation rules and read the session length
from them. Two do not: the one that takes a Slot Lock while a Requester fills in
the form, and the one that creates the Appointment when the Requester submits it.
Both build their own time window from a raw duration handed to them separately.

The cost is that the configuration wires the same value into three different
services, while the comment above the rules factory claims it is the single
source of truth. It is not, and nothing fails when the claim stops holding. A
change to how the session length is derived has to be made in three places and
will be made in one.

There is a second defect in the same area. The Slot Lock service ends its
constructor with two integers of the same type: the session length and the lock
lifetime. Its test supplies both positionally, so a swap reads as correct and is
not. This is the shape `test-suite-hardening/01` closed for the rules factory.
Taking the session length off that constructor leaves one integer and removes the
pair rather than renaming around it.

While the rules are being touched, the named constructor that builds them puts the
practice time zone between the session length and the Start Increment. Move the
two related values next to each other. This changes no behaviour and is folded in
here because it is the same read of the same class.

**Do this before `timezone-management/04`,** which moves sessions to 90 minutes.
That ticket has to update every place that pins the old duration. Two of those
places disappear here.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] The Slot Lock and the Appointment both take their length from the rules rather than from a duration passed to them separately
- [ ] The configured session length is wired into exactly one service
- [ ] No service takes a raw session length alongside another integer of the same type
- [ ] A test fails if the lock lifetime and the session length are exchanged at the point where the Slot Lock service is built
- [ ] A Slot Lock and an Appointment created through the API both cover a window of the configured length, asserted against the configured value rather than a repeated literal
- [ ] The named constructor for the rules keeps the session length and the Start Increment adjacent
- [ ] Full API suite green
