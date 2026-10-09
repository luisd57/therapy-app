# 44 - Two active Slot Locks under one candidate do not break the lookup

**What to build:** a test that fails when the lookup for an active Slot Lock
over a window throws because two locks match it.

Slots can start more often than they last, so candidates overlap (Start
Increment in `GLOSSARY.md`). Two Requesters can hold locks on windows that do
not touch each other while a third candidate overlaps both. The lookup for
that third window matches two rows. It asks for at most one, returns a lock,
and the Requester is told the Slot is taken. Without the limit the query fails
as non-unique and the Requester gets a 500.

No test has two matching locks (2026-10-09).

State the windows as Instants or build them from the configured session
duration. Do not write the duration as a literal, and do not assume it equals
the Start Increment.

**Mutants this should kill:** the kind 1 IncrementInteger row for
`src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineSlotLockRepository.php`
in `.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] With two active Slot Locks that do not overlap each other, a lookup over a window overlapping both returns one of them and does not throw
- [ ] The test does not depend on which of the two comes back
- [ ] A one-file Infection run no longer reports the row as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
