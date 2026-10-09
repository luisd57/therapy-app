# 37 - A Schedule Exception starting when a Slot ends does not block it

**What to build:** a test that fails when a Schedule Exception blocks the Slot
that ends at the exact Instant the exception starts.

A Schedule Exception and a Slot are both Instant ranges with an exclusive end,
so two that only touch share no time. The existing adjacent test covers one
side, the exception ending where the Slot starts. The other side is unpinned
(2026-10-09): an exception from 10:00 could take the Slot that ends at 10:00
with it, and the Therapist loses a Slot they never closed.

**Mutants this should kill:** the kind 1 LessThan row for
`src/Domain/Appointment/Entity/ScheduleException.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The UnwrapTrim row on that file is ticket 36.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A Schedule Exception starting at the Instant a Slot ends does not overlap that Slot
- [ ] The same exception starting one minute earlier does overlap it
- [ ] A one-file Infection run no longer reports the row as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
