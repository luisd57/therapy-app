# Recurrence is anchored to the therapist's local time; appointments are absolute

Status: accepted

## First, a correction to the framing

This system has **no recurring appointments**. Grepping `API/src` for recurrence,
RRULE, series or repeat concepts returns nothing but comments about schedule
blocks. What recurs is the **Schedule Block** - a weekly availability window.
Each Appointment is a one-off booking of a single Instant.

So the question "is a recurring appointment anchored to the therapist's local
time or the client's" decomposes into two separate answers.

## Decision

**Schedule Blocks are anchored to the Practice Timezone.** A block stores
`"08:00"` as a Wall-clock rule and becomes an Instant only when read against
`PRACTICE_TIMEZONE`. "Monday 08:00" means 08:00 for the therapist, forever,
whatever any patient's zone is doing.

**Appointments are anchored to nothing** - they are absolute Instants. Once
booked, an Appointment does not move for any reason.

**A client's zone is presentation only.** It is captured
(`appointments.requester_timezone`) so the therapist can see what time a session
is for the patient, and used to render. It never participates in computing
availability.

## What happens at a DST transition

This fires twice a year on the main path, since most patients are in Western
Europe and North America and Venezuela observes no DST.

**Nothing shifts, and nothing needs to.** Because both sides of the render are
IANA-based - the stored zone id plus `Intl.DateTimeFormat` / PHP `DateTimeZone` -
the offset is resolved *for the instant being displayed*, not for the moment of
booking. A patient in Madrid who books a November session in October sees it
rendered at its November offset immediately, and sees the same wall-clock time
after the transition. There is no drift to correct.

The visible consequence is that the therapist's wall clock is stable and the
patient's is not: an 08:00 Caracas slot is 14:00 in Madrid in summer and 13:00 in
winter. That asymmetry is intended. The therapist publishes her availability in
her own working hours; absorbing the shift is the patient's side of the
arrangement, which is exactly what she already does by hand.

## Considered and rejected

**Anchoring Schedule Blocks to UTC.** Rejected: her working day would drift
against her own clock, and the block boundaries encode real constraints - a lunch
break at 12:00 and being finished by 19:30.

**Anchoring recurrence to the client's zone.** Rejected, and it is not
expressible anyway: a Schedule Block belongs to the therapist and is shared by
all patients, who are in different zones.

**Storing the offset captured at booking time.** Rejected: it would freeze the
wrong answer for any session on the other side of a transition.

## Consequences and known latent hazard

Inside `AvailabilityComputer`, block *boundaries* are materialised in the
Practice Timezone, but slot *stepping* then happens in UTC - the increment is a
physical duration. If the Practice Timezone ever gained DST, wall-clock slot
starts would shift after a transition inside a long block. Unreachable while the
practice is in `America/Caracas` (no DST since 2016). Deliberate, and flagged in a
comment at the stepping site so a future zone change is a conscious decision.

## Unintended: `is_all_day` does not snap to a practice-local day

The intended behaviour was that a Schedule Exception flagged `is_all_day` would
be normalised to practice-local midnight-to-midnight. **The code does not do
this.** `is_all_day` is a pure passthrough boolean - set on the DTO, stored on the
entity, echoed in the output, and read by nothing. The stored range is whatever
Instants the client sent.

The practical effect is that "all day" means only what the caller's own range
happened to mean, so an all-day block submitted by a client in another zone
covers the wrong 24 hours. Availability itself is still correct, because
`ScheduleException::overlapsTimeSlot` compares Instants - the defect is confined
to what a caller can express.

Needs a ticket: "Snap `is_all_day` schedule exceptions to a practice-local day".
Not yet filed - `.scratch/timezone-management/` is created by `/to-tickets`.
