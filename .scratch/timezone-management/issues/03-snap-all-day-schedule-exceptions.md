# 03 — Snap all-day Schedule Exceptions to a practice day

**What to build:** When the Therapist marks a Schedule Exception as all-day, it
blocks her whole calendar day.

This is a **defect fix**. The all-day flag is currently a passthrough boolean —
set on the request, stored on the entity, echoed in the response, and read by
nothing. The stored range is whatever Instants the caller happened to send, so
"all day" means only what the caller's own day meant. A client in another zone
submitting an all-day Exception blocks a shifted 24 hours.

Availability itself stays correct today, because Schedule Exception overlap is
compared as Instants. The defect is confined to what a caller can express — but
that is exactly the surface the schedule manager UI will sit on when it is built.

Normalise an all-day Schedule Exception to Practice-local midnight through to the
following Practice-local midnight, so the flag means something.

See ADR-0002 for the anchoring decision this follows from.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] An all-day Schedule Exception covers exactly one Practice-local calendar day
- [ ] A non-all-day Schedule Exception stores the submitted Instant range unchanged
- [ ] An all-day Exception submitted from a far-away zone blocks the Therapist's day, not the caller's
- [ ] The Slot most at risk — one late in the Practice's evening — is correctly blocked, and the equivalent Slot on the following day is not
- [ ] Multi-day all-day ranges snap on both ends
- [ ] A test pins the snapping: an all-day Exception submitted from a far-away zone blocks the Practice-local day, and the equivalent Slot on the following day survives
- [ ] Full API suite green
