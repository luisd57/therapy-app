# 08 - Make the landing Schedule Block swap safe to retry

**What to build:** the landing spec that needs a Schedule Block the seed never
produces can fail, retry, or be killed without destroying the seeded schedule for
every run after it.

One spec swaps the whole schedule for a single in-person-only block and restores
the originals afterwards. The restore works on the happy path. Two ways it does
not:

**The retry path, which is the dangerous one.** The swap helper snapshots the
currently active blocks and closes over that snapshot as the restore baseline.
Continuous integration runs with one retry. If the first attempt's restore fails
or never runs, the second attempt re-enters setup and snapshots the *corrupted*
single block as though it were the originals. Its restore then faithfully writes
the corruption back. The run reports green, the schedule is permanently wrong,
and every later run sees availability that does not match the seed. The README
documents the killed-mid-run case and gives the reseed remedy, but not this one,
which is worse precisely because it looks like it worked.

**The ordering assumption.** The swap uses suite-level setup and teardown without
declaring the group serial, so isolation from the other landing specs rests
entirely on the config running one worker with parallelism off. Both are one edit
away from turning this into a race against every other spec in the suite.

**Verification has to force the losing path.** A green run is what the broken
version already produces. Make the restore fail deliberately and show the next
attempt still recovers the real seed.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A retry after a failed restore recovers the originally seeded Schedule Blocks, not whatever the previous attempt left behind
- [ ] The restore baseline cannot be captured from an already-swapped state
- [ ] The group's isolation is declared rather than inherited from the worker count, so changing parallelism does not silently break it
- [ ] The failure path is exercised deliberately, not assumed from a green run
- [ ] Landing e2e suite green
