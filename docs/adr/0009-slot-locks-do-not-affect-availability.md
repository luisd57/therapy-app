# Slot Locks do not affect availability

Status: accepted. In force since 2026-02-27, when commit `6568cfc` stopped the two slot-browsing
handlers passing active locks. Recorded here on 2026-10-06, moved out of the glossary.

## Decision

A Slot Lock is taken while a Requester fills in the request form. It does not hide the Slot from
other browsers and it does not block an Appointment request. Several Requesters may ask for the
same Slot by design, and the Therapist resolves the conflict manually when confirming.

So every caller of `AvailabilityComputer` passes an empty `activeLocks` collection on purpose:
`GetAvailableSlotsHandler`, `GetNextAvailableWeekHandler` and `AppointmentRequestService`. The
filtering itself stays in place with no production caller: `isHeldByLock()`, the `activeLocks`
field on `AvailabilityContext` and `SlotLockRepositoryInterface::findActiveByDateRange()`.

Locks do conflict with each other. `LockSlotHandler` rejects a lock over a window that overlaps an
active one.

The reason is the one written in `API/Product-Requirements.md#slot-lock-token-flow`. Commit
`6568cfc` has no message body, so nothing closer to the decision records it.

The client brainstorm (`API/Product-brainstorm-with-client.md`) never mentions locks. Its
"Notes 06/10/2025" section does describe the model this follows: the Therapist agrees a time with
the person by phone, then enters it herself, and that entry is what blocks the time. Its earlier
text has the calendar change as soon as an interested party picks a time, which those notes
replace.

## Considered and rejected

**Subtracting active locks from availability.** This is what the word "lock" suggests, and it is
how the two browsing handlers worked until `6568cfc`. Rejected because it contradicts the rule
above: a Slot someone is looking at must stay requestable by everyone else.

**Deleting the lock filtering.** Kept as scaffolding in case the client changes their mind.
Turning it back on means giving the two browsing handlers the lock repository again and passing
`findActiveByDateRange()` at all three call sites.

## Consequences

**The empty collection reads like a bug.** It is not. Do not pass the active locks in, and do not
remove the filtering as unused, without reopening this ADR.

**No test fails if a caller starts passing locks.**
`AvailabilityComputerTest::testActiveLockSuppressesOverlappingSlotsAndExpiredOneDoesNot` keeps the
unused filtering working. Nothing pins the other side, that a locked Slot is still listed and
still requestable over HTTP.

**A lock token proves nothing about availability.** The token is optional on a request. When one
is sent it must exist, be unexpired and match the Slot and Modality, and it is consumed. The
availability check that follows ignores locks, and a Requester with no token can request the same
Slot while it is locked. The landing form retries without the token on `INVALID_LOCK_TOKEN`.
