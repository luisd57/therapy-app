# Therapy Practice Management

A single-therapist psychotherapy practice based in Mérida, Venezuela. Most patients
are Venezuelan diaspora, largely in Western Europe and North America, so almost
every booking crosses a timezone boundary.

## Language

### Temporal

The system holds three different kinds of temporal value. Conflating them is the
source of most timezone bugs here, so the glossary names all three.

**Instant**:
A single point on the global timeline, stored as UTC and always transmitted with
an explicit offset. Appointment start, lock expiry, `created_at`.
_Avoid_: timestamp, datetime, "the time" (ambiguous with wall-clock time)

**Wall-clock rule**:
A recurring local time with no date, such as `"08:00"` on a Schedule Block. It
becomes an Instant only when read against the Practice Timezone.
_Avoid_: time, recurring time, schedule time

**Day key**:
A bare `YYYY-MM-DD` naming a calendar day in some stated zone. Which zone is
never implicit — the same Instant has different Day keys for the therapist and
for a patient abroad.
_Avoid_: date, date string

**Practice Timezone**:
The IANA zone the therapist's Schedule Blocks are expressed in. Configured, not
per-therapist. Currently `America/Caracas`.
_Avoid_: server timezone, local timezone, default timezone

**Requester Timezone**:
The IANA zone the person booking was in, captured on the Appointment at request
time. Distinct from the patient's profile timezone, because people travel.
_Avoid_: user timezone, client timezone

**Viewer Zone**:
Whichever zone a given screen is rendering in — the visitor's own for the public
slot browser, the therapist's for the dashboard.

### Scheduling

**Therapist**:
The single admin user. Exactly one exists; the API guards against a second.
_Avoid_: admin, practitioner, doctor

**Patient**:
A registered user, created only through the invitation flow.
_Avoid_: client, customer, user

**Requester**:
An unauthenticated person browsing slots or submitting an Appointment request.
May or may not become a Patient.
_Avoid_: visitor, guest, lead

**Schedule Block**:
A recurring weekly availability window — a day of week plus a start and end
Wall-clock rule, plus which Modalities it supports. The end means *sessions must
finish by*, not *last session starts at*.
_Avoid_: availability, shift, working hours

**Schedule Exception**:
A one-off unavailability that overrides Schedule Blocks for a specific Instant
range. The therapist's pressure valve for publishing wide Schedule Blocks.
_Avoid_: holiday, block-out, time off

**Slot**:
A concrete bookable window computed from Schedule Blocks minus Schedule
Exceptions minus blocking Appointments minus active Slot Locks. Always returned
as Instants.
_Avoid_: appointment time, opening

**Start Increment**:
How often a Slot may start inside a Schedule Block. Separate from session
duration: 90-minute sessions offered every 30 minutes produce overlapping
candidates, and booking one suppresses those it overlaps.
_Avoid_: step, interval, granularity

**Slot Lock**:
A short-lived, optional hold taken while a Requester fills in the form. Does not
hide the Slot from other browsers.
_Avoid_: reservation, hold

**Appointment**:
A requested or confirmed session. Only CONFIRMED Appointments block a Slot —
several Requesters may hold REQUESTED Appointments for the same Slot, and the
therapist resolves the conflict manually.
_Avoid_: booking, session, meeting

**Modality**:
`ONLINE` or `IN_PERSON`. In-person only happens in Mérida, so it is irrelevant to
diaspora patients; Schedule Blocks declare which Modalities they support.
_Avoid_: type, format, channel
