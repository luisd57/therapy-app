# 36 - A Schedule Exception stores its reason trimmed

**What to build:** a test that fails when a Schedule Exception keeps the
padding around its reason.

The one test that reads as if it covers this passes no reason at all, so there
is nothing for the trim to remove (2026-10-09).

**Mutants this should kill:** the kind 1 UnwrapTrim row for
`src/Domain/Appointment/Entity/ScheduleException.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The LessThan row on that file is ticket 37.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A Schedule Exception created with a padded reason returns the reason without the padding
- [ ] One created with a whitespace-only reason returns an empty reason
- [ ] A one-file Infection run no longer reports the row as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
