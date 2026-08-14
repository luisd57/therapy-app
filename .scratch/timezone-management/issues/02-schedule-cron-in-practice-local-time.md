# 02 - Schedule cron in practice local time

**What to build:** The Therapist's scheduled jobs run at the hour she expects in
her own clock.

This is a **defect fix**. The scheduled-job container runs UTC, and cron reads
the container zone rather than the PHP configuration, so the daily agenda
intended for 07:00 fires at 03:00 in Caracas - she receives her working day in
the middle of the night. Token cleanup at 02:00 lands at 22:00 local, inside her
Wednesday-to-Sunday working window rather than safely outside it.

Declare the schedule in the Practice Timezone rather than shifting the hour by a
hardcoded offset. An offset-shifted schedule is correct only while the offset is,
breaks silently if Venezuela changes its rules again, and forces a reader to do
arithmetic to learn when a job actually runs.

Separately, the daily agenda derives its Day key from the process clock, which
now resolves in UTC. It must come from the injected clock converted to the
Practice Timezone, so the agenda covers the Therapist's calendar day.

See ADR-0004 for the decision and the alternatives rejected.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Scheduled jobs are declared in the Practice Timezone, not as a UTC-shifted hour
- [ ] The daily agenda fires in the Therapist's morning
- [ ] The agenda's Day key comes from the injected clock converted to the Practice Timezone, not from the process clock
- [ ] The agenda covers the Therapist's calendar day, not a UTC day
- [ ] Token and Slot Lock cleanup run outside her working window in local terms
- [ ] The crontab reads as intent - a reader can see when a job runs without computing an offset
- [ ] A test pins the agenda's day-boundary behaviour with a frozen clock
- [ ] Full API suite green
