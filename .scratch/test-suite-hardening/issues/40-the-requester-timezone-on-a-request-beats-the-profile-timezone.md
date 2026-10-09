# 40 - The Requester Timezone on a request beats the profile timezone

**What to build:** a test that fails when a Patient's Appointment request is
stored with the timezone from their profile although the request carried its
own.

People travel. The Requester Timezone is the zone the person was in when they
asked, and the profile timezone is only the fallback for a request that
carries none. No test sets both (2026-10-09), so the two can swap priority
unseen. A Patient asking from abroad would then get their emails in the time
of their home zone.

**Mutants this should kill:** the kind 1 Coalesce row for
`src/Application/Appointment/Handler/PatientRequestAppointmentHandler.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A Patient whose profile holds one timezone sends a request carrying another, and the Appointment carries the one from the request
- [ ] The two zones differ from each other and from the Practice Timezone, so no fallback can produce the expected value
- [ ] A one-file Infection run no longer reports the row as escaped
