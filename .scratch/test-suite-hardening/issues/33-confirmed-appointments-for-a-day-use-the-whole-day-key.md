# 33 - Confirmed Appointments for a day use the whole Day key, end exclusive

**What to build:** a test that fails when the lookup of confirmed Appointments
for one day drops an Appointment at either end of that day, or takes one from
the day before or the day after.

The daily agenda is built from this lookup. The day is the Therapist's, so the
caller passes a Day key read in the Practice Timezone, and the window runs
from that day's midnight, inclusive, to the next midnight, exclusive.

The existing fixtures sit well inside the day or well outside it (2026-10-09):
the evening before at 23:00, the latest in-day one at 22:00, nothing on either
midnight and nothing confirmed after the day ends. Either end of the window
can move by a minute or by an hour and nothing fails.

Four confirmed fixtures settle it: the last minute of the day before, the
day's midnight, the day's last minute, and the next midnight. The lookup
returns the middle two.

Give every fixture an explicit offset and write the expected Instants in UTC
(ADR-0003). A fixture built in the same zone the assertion formats in proves
nothing.

**Mutants this should kill:** the seven kind 1 rows for
`src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineAppointmentRepository.php`
in `.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A confirmed Appointment starting at the day's midnight is returned
- [ ] One starting in the day's last minute is returned
- [ ] One starting in the last minute of the day before is not returned
- [ ] One starting at the next midnight is not returned
- [ ] The day is named in the Practice Timezone, and every expected Instant is written in UTC
- [ ] A one-file Infection run no longer reports the seven rows as escaped
