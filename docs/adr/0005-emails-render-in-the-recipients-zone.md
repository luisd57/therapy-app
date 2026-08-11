# Emails should render times in the recipient's zone

Status: **proposed - NOT implemented. Current behaviour is a regression.**

## Current behaviour in the code

`API/src/Infrastructure/Email/Appointment/AppointmentEmailSender.php` formats
appointment times with bare calls - `format('l, F j, Y')`, `format('g:i A')` -
at ten or more call sites, with **no `setTimezone` anywhere**.

Before this branch, instants were hydrated in PHP's default zone, which was
`America/Caracas`, so emails happened to render practice-local times. ADR-0001
moved storage to UTC and `UtcDateTimeImmutableType` now hydrates every instant in
UTC. The formatting calls were not updated.

**Every appointment email therefore currently renders UTC.** A 09:00 Caracas
session is emailed as "1:00 PM". This is a regression introduced by the storage
change in commit `495cd63` and is present in the committed code.

It is not a data problem - stored instants are correct - but every notification
the practice sends states a time four hours off, to both the patient and the
therapist.

## Proposed decision

Convert before formatting, choosing the zone by recipient:

- **Requester-facing mail** renders in the appointment's `requester_timezone`,
  falling back to the Practice Timezone when null.
- **Therapist-facing mail** renders in the Practice Timezone.
- Both print the zone alongside the time, and show the other party's time as a
  secondary line - the same dual-display rule the booking UI already follows.

## Why name the zone in the body

The recipient cannot infer it. An email is read outside the app, often days
later, with no banner to explain which clock is meant. Printing "9:00 a. m.
(Caracas)" costs nothing and removes the only remaining place in the system where
a time appears without its zone.

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

Email tests will need to assert on rendered strings including the zone label, and
`EmailRenderingTest` currently asserts none.

Needs a ticket, and it is the highest-priority one on this branch: every
notification the practice sends is currently four hours wrong. Not yet filed -
`.scratch/timezone-management/` is created by `/to-tickets`.
