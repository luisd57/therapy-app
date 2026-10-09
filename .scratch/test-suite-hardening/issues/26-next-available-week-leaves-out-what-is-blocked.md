# 26 - Next available week leaves out what is blocked

**What to build:** a test that fails when the next-available-week search stops
passing a week's Schedule Exceptions or confirmed Appointments on to the
availability computation.

The handler loads both once for the whole lookahead, then gives each week only
the ones that touch it. Nothing checks that step (2026-10-09). The unit test
stubs the computer and an empty Schedule Exception list, and the controller
test seeds neither. A filter that drops every in-week Schedule Exception, or
every in-week confirmed Appointment, passes the suite. The Requester would then
be offered Slots the Therapist has closed or already given away.

**Use a seam that runs the real availability computation.** A stubbed computer
returns what the test told it to, whatever context it was handed, and asserting
on that context would be asserting internal state. Assert on the week and the
Slots that come back.

Freeze the clock and give every fixture Instant an explicit offset.

**Mutants this should kill:** the eight kind 1 rows on the two week filters of
`src/Application/Appointment/Handler/GetNextAvailableWeekHandler.php`, in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The LessThan row on the lookahead loop is ticket 27. The six kind 2
rows sit on the same two statements and are equivalent, so they stay. Do not
put an ignore comment there: it would hide the kind 1 rows as well.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] With a Schedule Exception covering every Slot of the current week, the search returns the following week, not the current one
- [ ] With one Slot of the returned week under a confirmed Appointment, that Slot is not among the Slots returned, and a Slot that does not overlap it is
- [ ] Both are asserted on what the search returns, with the availability computation not stubbed
- [ ] A one-file Infection run no longer reports the eight rows as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
