# 27 - Next available week stops at the lookahead

**What to build:** a test that fails when the next-available-week search looks
one week past the configured lookahead.

**Not a duplicate of:** 26, which pins what each week is told about its
Schedule Exceptions and confirmed Appointments. This one pins how many weeks
are searched. Same handler, different rule, and no test of one kills the
other's mutants.

The not-found test stubs the computer empty for every call (2026-10-09), so it
cannot tell a search of N weeks from one of N+1. A Requester would be shown a
week the practice has decided not to offer yet.

**The fixture must be free again right after the lookahead.** Block every week
inside it and leave the next one open. Schedule Exceptions are loaded for the
lookahead range, and one that runs past the edge blocks the extra week too, so
the test then passes for the wrong reason. End the block exactly where the
lookahead ends.

Take the lookahead from where the test sets it, not from a repeated literal.
Do not count calls on a stub: that pins the loop, not the answer.

**Mutants this should kill:** the kind 1 LessThan row on the lookahead loop of
`src/Application/Appointment/Handler/GetNextAvailableWeekHandler.php`, in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The rows on the two week filters are ticket 26.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] With every week inside the lookahead blocked and the week after it open, the search answers not found
- [ ] The assertion is on what the search returns, with the availability computation not stubbed
- [ ] A one-file Infection run no longer reports the row as escaped
