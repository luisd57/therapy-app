# 06 - Cover the console commands

**What to build:** the commands that seed and clean up are verified, so a break
in one is reported by the API suite instead of by a confusing failure somewhere
downstream.

Four of the five have no test. Only the daily agenda command has one.

Two of the untested four are load-bearing for continuous integration: the e2e job
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

**Status:** ready-for-agent

- [ ] Each command has a test driving it through the console tester rather than calling the handler directly
- [ ] Creating the Therapist is covered, including the guard against creating a second one
- [ ] Seeding is covered in both its normal and its force mode, and the normal mode is asserted to refuse rather than duplicate when blocks already exist
- [ ] Each cleanup command is asserted to remove only rows past their expiry, with a row just inside the boundary left alone
- [ ] Exit codes are asserted, since the e2e job in continuous integration depends on them
- [ ] Full API suite green
