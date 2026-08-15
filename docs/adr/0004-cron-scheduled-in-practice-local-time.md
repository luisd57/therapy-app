# Scheduled jobs run on practice local time

Status: accepted, implemented on 2026-08-15. Supersedes the CRON_TZ approach this
ADR originally proposed, which does not work on the image we run.

## The behaviour this replaced

`API/docker/cron/crontab` declared its hours with no zone, and the cron container
had `TZ=UTC`, so the daily agenda intended for 07:00 fired at 07:00 UTC, which is
03:00 in Caracas. Token cleanup at 02:00 UTC landed at 22:00 local, inside the
therapist's Wednesday-to-Sunday working window.

`SendDailyAgendaCommand` compounded it by computing `date('Y-m-d')`, which
resolves in the process zone. That was the Caracas date before ADR-0001 and the
UTC date after it.

## Decision

The cron service sets `TZ=America/Caracas`, so cron reads the practice zone and
the crontab hours mean what they say. The agenda's day key comes from the
injected clock converted to the practice zone, never from `date()`.

PHP is unaffected by the container zone: `docker/php/php.ini` pins
`date.timezone = UTC`, verified in the running container. `PGTZ=UTC` stays set
explicitly, so the Postgres session zone does not move either.

## Why not CRON_TZ, as first proposed

Debian's cron does not support it. Measured on the image we run (cron
3.0pl1-197): with `CRON_TZ=America/Caracas` at the top of the crontab, a job
scheduled for a Caracas minute two minutes away never fired, while an
every-minute control job kept firing. A plain `TZ=` line behaves the same way.
Neither string appears in the `cron` or `crontab` binaries.

This is the worst kind of failure: the crontab reads as though it is zone-aware
and the schedule silently stays UTC. `CRON_TZ` is a cronie extension, not a Vixie
one. Do not reintroduce it without re-running that probe.

## Why not a UTC-shifted hour

Writing `0 11 * * *` to mean "07:00 Caracas" encodes the offset into the
schedule. It is correct only while the offset is, breaks silently if Venezuela
changes its rules again (2007 and 2016), and forces a reader to do arithmetic to
learn when a job runs.

## Consequences

Jobs run at the same wall-clock hour year-round. Were the practice ever to move
to a zone with daylight saving, jobs would skip or repeat around transitions -
the standard cron caveat, and the reason this stays a named zone rather than
being "fixed" into a UTC offset later.

The slot-lock sweep still runs every 15 minutes around the clock. It reaps locks
on a short TTL, so confining it to off-hours would leave stale locks all day.
Only the two daily jobs are placed relative to the working window.
