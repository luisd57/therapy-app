# Emails should render times in the recipient's zone

Status: accepted, implemented on 2026-08-15.

## The behaviour this replaced

`API/src/Infrastructure/Email/Appointment/AppointmentEmailSender.php` formatted
appointment times with bare calls - `format('l, F j, Y')`, `format('g:i A')` -
at ten or more call sites, with **no `setTimezone` anywhere**.

Before this branch, instants were hydrated in PHP's default zone, which was
`America/Caracas`, so emails happened to render practice-local times. ADR-0001
moved storage to UTC and `UtcDateTimeImmutableType` now hydrates every instant in
UTC. The formatting calls were not updated.

**Every appointment email therefore rendered UTC.** A 09:00 Caracas session was
emailed as "1:00 PM". The regression came in with the storage change in commit
`495cd63`.

It was not a data problem - stored instants were correct - but every notification
the practice sent stated a time four hours off, to both the patient and the
therapist.

## Decision

Convert before formatting, choosing the zone by recipient:

- **Requester-facing mail** renders in the appointment's `requester_timezone`,
  falling back to the Practice Timezone when null.
- **Therapist-facing mail** renders in the Practice Timezone.
- Both print the zone alongside the time, and show the other party's time as a
  secondary line - the same dual-display rule the booking UI already follows.

## Why name the zone in the body

The recipient cannot infer it. An email is read outside the app, often days
later, with no banner to explain which clock is meant. Printing "9:00 AM
(Caracas)" costs nothing and removes the only remaining place in the system where
a time appears without its zone.

Email copy is English throughout, so the rendered time is too. The dashboard's
Spanish sweep does not reach the mail templates.

## Considered and rejected

**Rendering everything in the Practice Timezone.** Simplest, and it is what the
system did by accident before. Rejected: it pushes the conversion back onto the
patient, which is the manual step this work exists to remove.

**Rendering everything in UTC and letting the reader convert.** Rejected outright
- that is the current broken behaviour, and no patient thinks in UTC.

**Using the patient profile's timezone rather than the appointment's.** Rejected:
the appointment records the zone the person was actually in when they booked, and
people travel. Profile zone is the fallback, not the source.

## Consequences

`EmailRenderingTest` asserted nothing about times; it now pins the rendered time,
the zone label, and the other party's line for each mail.

The secondary line is dropped when both parties read the same clock - repeating
the same time under a different label reads as a bug.

The daily agenda's date now names a day in the practice zone, so
`SendDailyAgendaHandler` anchors the parsed date there rather than in the process
zone. The rest of that job's scheduling is ADR-0004.
