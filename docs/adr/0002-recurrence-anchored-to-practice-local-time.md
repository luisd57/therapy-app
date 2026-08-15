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

## `is_all_day` snaps to practice-local days

An all-day Schedule Exception is normalised in `ScheduleException::create`: the
start moves back to practice-local midnight, and the end forward to the next
practice-local midnight. An end already on midnight stays put - the range is
half-open, so rounding it up would add a day the caller never covered. This makes
the flag mean the therapist's calendar day rather than the caller's, which is the
same anchoring the Schedule Blocks above use.

Snapping is in the entity, not the handler, so the flag cannot be set without it.
`create()` therefore takes the practice zone; `reconstitute()` does not, because a
stored row is already snapped.

Two consequences worth stating. The rule only ever widens a range, so an all-day
Exception blocks at least what the caller asked for - shrinking would turn a
presentation defect into an availability one. And a caller whose own 24 hours
straddle two practice-local days blocks both of them, since both are days the
submitted range touches.

Until 2026-08-15 the flag was a passthrough boolean that nothing read, so "all
day" meant only what the caller's own range happened to mean. Availability was
never wrong - `overlapsTimeSlot` compares Instants - but the caller could not
express a day off in the therapist's terms. Fixed by ticket 03.
