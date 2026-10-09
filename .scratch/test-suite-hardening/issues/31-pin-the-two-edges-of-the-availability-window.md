# 31 - Pin the two edges of the availability window

**What to build:** tests that fail when the availability computation offers a
Slot starting exactly at the end of the window it was asked for, or one
starting exactly now.

Both edges are deliberate:

- **The window end is exclusive.** Callers pass the start of the next week as
  the end, so an inclusive end would list that Slot in both weeks.
- **A Slot starting at the current Instant is past.** Only a later start is
  still open.

No test has a candidate starting exactly at the window end, and none puts
`now` on a Slot start (2026-10-09). A fixture a minute either side of an edge
says nothing about the edge, so land a candidate start exactly on it.

Take the candidate starts from the rules the test passes in. Do not assume the
Start Increment equals the session duration.

**Mutants this should kill:** the two kind 1 rows for
`src/Domain/Appointment/Service/AvailabilityComputer.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The seven kind 2 rows on that file are equivalent and stay.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A candidate Slot starting exactly at the window end is not returned, and the candidate before it is
- [ ] A candidate Slot starting exactly at `now` is not returned, and the next candidate is
- [ ] A one-file Infection run no longer reports the two rows as escaped
