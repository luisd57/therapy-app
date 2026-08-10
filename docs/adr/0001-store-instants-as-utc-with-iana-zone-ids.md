# Store instants as UTC; name zones with IANA ids, never offsets

Status: accepted

## Decision

Every instant is stored as UTC in a Postgres `TIMESTAMP(0) WITH TIME ZONE` column
and emitted from the API as ISO-8601 with an explicit offset, always `+00:00`.
Every zone the system names is an IANA identifier (`America/Caracas`,
`Europe/Madrid`), never a numeric offset.

Wall-clock rules are the deliberate exception: `TherapistSchedule.start_time` and
`.end_time` stay `VARCHAR(5)` `"HH:MM"`. They are recurring local times, not
instants, and turning them into instants would destroy the thing that makes them
recurring. See ADR-0002.

## Why IANA rather than offsets

The therapist describes the gap to Europe as "5 o 6 horas". That wobble is
European daylight saving: Caracas is UTC−4 all year, Madrid is UTC+1 in winter
and UTC+2 in summer. A stored `-04:00`/`+02:00` pair would be wrong for roughly
half of every year on the main path — most patients are in Western Europe.

Venezuela is also not a stable offset historically: it ran UTC−04:30 from 2007 to
2016. Only the named zone gets pre-2016 instants right, which is why the
migration converts with `AT TIME ZONE 'America/Caracas'` rather than
`AT TIME ZONE '-04:00'`.

## Considered and rejected

**Naive `TIMESTAMP` plus a convention that everything is UTC.** Rejected: the
convention is invisible and unenforceable. This is what the system had before —
the only thing giving stored values meaning was one `date.timezone` line in
`php.ini`, and changing it silently reinterpreted every row.

**Doctrine's built-in `DATETIMETZ_IMMUTABLE`.** Rejected on inspection: it
requires the exact format `Y-m-d H:i:sO` and has no parse fallback, while
Postgres renders the offset as `+00`. Any session-timezone drift becomes a hard
`InvalidFormat` at read time. Replaced by a custom
`UtcDateTimeImmutableType` that normalises both directions to UTC.

**Storing a fixed offset alongside each instant.** Rejected: an offset records
what the rule *was* at one moment, not the rule. It cannot answer "what time is
this appointment next March".

## Consequences

- Doctrine's `ParameterTypeInferer` binds a bare `DateTimeImmutable` DQL
  parameter through the *naive* type, silently writing a literal with no offset.
  Every temporal query parameter therefore passes the type explicitly
  (`->setParameter('from', $from, UtcDateTimeImmutableType::NAME)`). Twenty-one
  call sites. Omitting one produces results four hours out with no error.
- `TIMESTAMP(0)` truncates sub-second precision. Fine for appointments; slightly
  sloppy for token expiry, accepted.

## Verified state of the codebase

Grepped at time of writing: **no fixed offset appears in any production logic.**
Every `-04:00` occurrence under `API/src` is either an example inside a validation
error message or a comment explaining why offsets are rejected.

Three hardcoded IANA ids do exist, all in `landing/`, all as fallback defaults
overwritten by the API's `practice_timezone` field on first response:
`src/utils/dates.ts:18`, `src/components/svelte/AppointmentFlow.svelte:13`,
`src/components/svelte/SlotBrowser.svelte:36`. These are not offsets and carry no
DST bug, but they would be wrong if the practice ever moved.
