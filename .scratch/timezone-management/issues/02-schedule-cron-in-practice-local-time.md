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

- [x] Scheduled jobs are declared in the Practice Timezone, not as a UTC-shifted hour
- [x] The daily agenda fires in the Therapist's morning
- [x] The agenda's Day key comes from the injected clock converted to the Practice Timezone, not from the process clock
- [x] The agenda covers the Therapist's calendar day, not a UTC day
- [ ] Token and Slot Lock cleanup run outside her working window in local terms
- [x] The crontab reads as intent - a reader can see when a job runs without computing an offset
- [x] A test pins the agenda's day-boundary behaviour with a frozen clock
- [x] Full API suite green

## Comments

**2026-08-15, [PR #27](https://github.com/luisd57/therapy-app/pull/27) merged.** Seven
of the eight criteria are met and verified. Left open: Slot Lock cleanup cannot run
outside the working window. It sweeps every 15 minutes because locks expire on a
short TTL, so confining it to off-hours would leave stale locks all day. Token
cleanup does now run outside the window, at 02:00 practice-local. The criterion needs
either a reword covering the daily jobs only, or a decision to accept the sweep as
continuous - hence this ticket stays open rather than resolved.

Two findings worth carrying forward. `CRON_TZ` does not work on this image: Debian
cron ignores it, measured, so the crontab hours depend on the container `TZ`. And the
jobs were not running at all before this work, for two reasons unrelated to zones - a
CRLF crontab that sh rejected, and a `when@prod` Doctrine block referencing cache
pools that were never declared. Both are fixed. See ADR-0004.
