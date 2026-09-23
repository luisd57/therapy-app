# 06 - Cover the console commands

> Frozen record, resolved 2026-09-23.

**What to build:** the commands that seed and clean up are verified, so a break
in one is reported by the API suite instead of by a confusing failure somewhere
downstream.

Four of the five have no test. Only the daily agenda command has one.

Continuous integration depends directly on two of the untested four: the e2e job
creates the Therapist and seeds the Schedule Blocks by invoking them before
either Playwright suite runs. If either changes shape, both e2e suites fail on
missing availability or a failed login, and nothing in the output points at the
command. The landing suite already fails fast with a message naming the seed
command as the remedy, which is a workaround for exactly this blind spot.

The two cleanup commands run on a schedule against real rows. Their selection
logic is the part worth pinning: a cleanup that deletes too much is far worse
than one that deletes nothing, and neither boundary is currently asserted.

The seeding command has a force mode that deactivates existing blocks and
reseeds. Its non-force mode is documented to fail when blocks already exist.
Cover both, since the difference is what protects a real schedule.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #90](https://github.com/luisd57/therapy-app/pull/90)

- [x] Each command has a test driving it through the console tester rather than calling the handler directly
- [x] Creating the Therapist is covered, including the guard against creating a second one
- [x] Seeding is covered in both its normal and its force mode, and the normal mode is asserted to refuse rather than duplicate when blocks already exist
- [x] Each cleanup command is asserted to remove only rows past their expiry, with a row just inside the boundary left alone
- [x] Exit codes are asserted, since the e2e job in continuous integration depends on them
- [x] Full API suite green

## Comments

**2026-09-23** - Every criterion was checked by mutation, not by a green suite. Each of these
turns a test red: `<` to `<=` in any `deleteExpired()`, binding its `now` without the UTC type,
adding `OR isUsed` / `OR isRevoked` to a token cleanup, swapping the two cleanup counts, dropping
the seed force guard, deactivating only the first existing block, changing one seeded block, and
turning any create-therapist FAILURE into SUCCESS. 777 tests before, 788 after.

The wall-time binding only fails because the cleanup tests freeze the clock with a `+14:00`
offset. Frozen in UTC, the wall time already is UTC and the mutation survives.

The second-therapist criterion was a production change. `CreateTherapistCommand` never caught
`TherapistAlreadyExistsException`, so `CommandTester` threw. The real console already exited 1,
so CI's exit code did not change, only the output.

One mutation survives and is left alone: dropping the `save()` inside the force loop. A later save
flushes the managed block anyway.
