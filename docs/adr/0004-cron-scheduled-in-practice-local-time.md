# Scheduled jobs should run on practice local time via CRON_TZ

Status: **proposed — NOT implemented.** The current behaviour is wrong; see below.

## Current behaviour in the code

`API/docker/cron/crontab` contains no `CRON_TZ` line:

```
*/15 * * * * … app:cleanup-slot-locks
0 7  * * *   … app:send-daily-agenda
0 2  * * *   … app:cleanup-tokens
```

The cron container has `TZ=UTC` (set in `docker-compose.yml`), and cron reads the
container OS zone, not `php.ini`. So the daily agenda intended for 07:00 fires at
**07:00 UTC, which is 03:00 in Caracas** — the therapist receives her day's
agenda in the middle of the night.

`SendDailyAgendaCommand.php:41` compounds this: it still computes
`date('Y-m-d')`, which now resolves in UTC rather than the Practice Timezone.

Neither is new to this branch as a *symptom* — the container was already UTC
while PHP was Caracas — but the second half is: before this work `date('Y-m-d')`
returned the Caracas date, and now it returns the UTC date.

## Proposed decision

Add `CRON_TZ=America/Caracas` at the top of the crontab, and derive the agenda
date from the injected clock converted to the Practice Timezone rather than from
`date()`.

## Why CRON_TZ rather than a UTC-shifted schedule

Writing `0 11 * * *` to mean "07:00 Caracas" encodes the offset into the schedule.
It is correct only while the offset is; it silently breaks if Venezuela changes
its rules (it has, in 2007 and 2016), and it forces a reader to do arithmetic to
learn when the job actually runs. `CRON_TZ` states the intent directly and lets
the tz database handle the rest. Debian/Vixie cron supports it.

## Considered and rejected

**Setting the whole cron container to `TZ=America/Caracas`.** Rejected: the
container also runs PHP, and the codebase depends on the process default being
UTC (ADR-0001). `CRON_TZ` scopes the change to schedule interpretation only.

**Leaving it UTC and accepting the offset.** Rejected: 03:00 is not a plausible
time to send someone their working day, and `cleanup-tokens` at 02:00 UTC is
22:00 local — inside her Wednesday-to-Sunday working window rather than safely
outside it.

## Consequences

Jobs will run at the same wall-clock hour year-round. Were the practice ever to
move to a DST zone, `CRON_TZ` would make jobs skip or repeat around transitions —
the standard cron caveat, and the reason this must stay a named zone rather than
being "fixed" back into a UTC offset later.

Needs a ticket: "Schedule cron in practice local time". Not yet filed —
`.scratch/timezone-management/` is created by `/to-tickets`.
