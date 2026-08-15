# 03 - Snap all-day Schedule Exceptions to a practice day

**What to build:** When the Therapist marks a Schedule Exception as all-day, it
blocks her whole calendar day.

This is a **defect fix**. The all-day flag is currently a passthrough boolean -
set on the request, stored on the entity, echoed in the response, and read by
nothing. The stored range is whatever Instants the caller happened to send, so
"all day" means only what the caller's own day meant. A client in another zone
submitting an all-day Exception blocks a shifted 24 hours.

Availability itself stays correct today, because Schedule Exception overlap is
compared as Instants. The defect is confined to what a caller can express - but
that is exactly the surface the schedule manager UI will sit on when it is built.

Normalise an all-day Schedule Exception to Practice-local midnight through to the
following Practice-local midnight, so the flag means something.

See ADR-0002 for the anchoring decision this follows from.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #30](https://github.com/luisd57/therapy-app/pull/30)

- [x] An all-day Schedule Exception covers exactly one Practice-local calendar day (holds for any range inside one Practice day; a straddling range blocks both - criterion interpreted on 2026-08-15, see Comments)
- [x] A non-all-day Schedule Exception stores the submitted Instant range unchanged
- [x] An all-day Exception submitted from a far-away zone blocks the Therapist's day, not the caller's
- [x] The Slot most at risk - one late in the Practice's evening - is correctly blocked, and the equivalent Slot on the following day is not
- [x] Multi-day all-day ranges snap on both ends
- [x] A test pins the snapping: an all-day Exception submitted from a far-away zone blocks the Practice-local day, and the equivalent Slot on the following day survives
- [x] Full API suite green

## Comments

**2026-08-15, [PR #30](https://github.com/luisd57/therapy-app/pull/30) merged.** The snap
moves an all-day start back to practice-local midnight and the end forward to the next
one, leaving an end already on midnight alone because the range is half-open. It lives in
`ScheduleException::create` rather than the handler, so the flag cannot be set without it.

The first criterion was **interpreted rather than met word for word**, recorded here so the
change is visible. It asks for "exactly one Practice-local calendar day", which is true for
any submitted range sitting inside one Practice day. A caller whose own 24 hours straddle
two Practice-local days blocks both, because both are days the range touches. That follows
from the fifth criterion (snap at both ends) combined with never shrinking a blocked window,
and no rule satisfies both criteria for a straddling range. Confirmed with the developer:
block both days, since shrinking would leave a Slot open on a day the Therapist is away.
ADR-0002 carries the reasoning and a test pins the behaviour. No code changed as a result.

Availability was never wrong before this. `overlapsTimeSlot` compares Instants, so the
defect was confined to what a caller could express.

One finding worth carrying forward, fixed in the same PR because the create response is how
the criteria were verified. `ScheduleExceptionOutputDTO`, `SlotLockOutputDTO` and
`InvitationOutputDTO` formatted instants with a bare `ATOM` call on whatever zone the value
carried, so the create and lock endpoints echoed the caller's offset while the list response
for the same row came back UTC. All three now use `InstantFormatter`, which had no test of
its own and has one now.

Three pre-existing items were flagged and left alone: practice-local midnight is computed
inline in `AvailabilityComputer`, `GetNextAvailableWeekHandler` and
`AppointmentRequestService`; `create()` now takes seven parameters with start and end still
travelling as a raw pair; and `HealthController` still formats a bare `ATOM` timestamp in the
server zone.

Landing e2e was red on the merge run for ticket 12's reason (Saturday). It reproduces on
`main` and is unrelated to this work.
