# 28 - A Schedule Block refuses a bad Wall-clock rule on create and on update

**What to build:** tests that fail when a Schedule Block accepts a start or an
end that is not a real HH:MM Wall-clock rule, or an update that leaves it with
no length.

The entity checks the shape (two digits, a colon, two digits, nothing around
them), then the range (hour up to 23, minute up to 59), then that the start is
before the end. As of 2026-10-09 most of that is unpinned:

- Update has no test with a malformed start or end, and none with a start
  equal to the end. Create pins the equal case.
- No test has junk before or after an otherwise valid value.
- No test uses a 23:xx or an xx:59 value, so refusing those would go unseen.
- The malformed cases that exist, `9:00` and `25:00` as a start, are refused
  by the start-before-end check as well, and the tests expect only the
  exception class. Take the format check away and they still pass.

**Each case must be refused by the rule it is about.** The start-before-end
check compares strings, so pick input it lets through (`25:00` as an end does),
or assert which rule did the refusing. A case two rules refuse pins neither.

**Mutants this should kill:** the ten kind 1 rows for
`src/Domain/Appointment/Entity/TherapistSchedule.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The two CastInt rows are kind 2, equivalent, and stay.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Create and update each refuse a malformed start and a malformed end, in cases only the format rule can refuse
- [ ] Junk before a valid HH:MM is refused, and so is junk after it
- [ ] An hour past 23 is refused with a valid minute, and a minute past 59 with a valid hour
- [ ] A block from 23:00 to 23:59 is accepted
- [ ] Update refuses a start equal to the end
- [ ] A one-file Infection run no longer reports the ten rows as escaped
