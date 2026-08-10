# Timezone Management

Status: ready-for-agent

Branch: `feat/timezone-management` · 8 of 14 planned steps landed
Related decisions: ADR-0001, ADR-0002, ADR-0003, ADR-0004, ADR-0005

## Problem Statement

The Therapist works from Mérida, Venezuela. Most of her Patients are Venezuelan
diaspora, concentrated in Western Europe and North America. Almost every booking
therefore crosses a timezone boundary, and the system had no concept of timezone
at all.

She resolves this by hand today. In her own words she asks the person abroad to
propose a time, "teniendo en cuenta que en relación a Europa Venezuela tiene 5 o
6 horas más tempranas" — she delegates the conversion to the Patient and absorbs
the coordination cost in WhatsApp messages. That five-or-six-hour wobble is
European daylight saving, and it is exactly the kind of arithmetic people get
wrong twice a year.

Concretely, before this work:

- Every stored Instant was a naive wall-clock value whose meaning depended on one
  `date.timezone` line in a config file. Changing it would silently reinterpret
  every row in the database.
- Every API response stamped a `-04:00` offset that had never been stored,
  derived from server config at serialization time.
- Slots were grouped by the Practice's calendar day, which is the wrong calendar
  for every Patient abroad — a Friday 19:00 slot in Caracas is already Saturday
  in Madrid.
- The slot browser showed times in the visitor's browser zone while bucketing
  them by the Practice's day, so a Requester could see a slot filed under the
  wrong column with no indication of which zone either number meant.

A Requester in Madrid could book what they believed was a 15:00 session and
discover it was 09:00. There is no recovery from that for a therapy appointment.

## Solution

Make the timezone of every temporal value explicit, and automate the conversion
the Therapist currently performs by hand.

- Every **Instant** is stored as UTC and transmitted with an explicit offset.
- **Schedule Blocks** stay Wall-clock rules anchored to the **Practice
  Timezone**, so the Therapist's working day is stable in her own clock.
- Every response that carries Slots or Appointments also names the Practice
  Timezone, so no client hardcodes it.
- The public slot browser renders in the **Viewer Zone**, buckets Slots by the
  viewer's **Day key**, and says plainly which zones it is showing.
- Wherever a mistake would be costly — the selected Slot, the confirmation, every
  email — both the Requester's time and the Therapist's time appear together.
- The **Requester Timezone** is captured on the Appointment so the Therapist can
  see what time a session is for the Patient.

## User Stories

### Requester browsing availability

1. As a Requester in Madrid, I want Slot times shown in my own zone, so that I do not have to compute the difference myself.
2. As a Requester, I want the page to state which zone the times are in, so that I never have to guess whether a number is mine or the Therapist's.
3. As a Requester, I want the Therapist's zone named alongside mine, so that I understand the practice is abroad.
4. As a Requester, I want Slots grouped under the day they fall on *for me*, so that a late-evening Caracas Slot appears on the day I would actually attend it.
5. As a Requester whose browser zone is detected wrongly, I want to override the display zone, so that a VPN or a misconfigured device does not mislead me.
6. As a Requester, I want my chosen zone remembered between visits, so that I do not reset it every time.
7. As a Requester, I want week navigation to move by calendar weeks, so that a daylight-saving change does not shift the grid by an hour.
8. As a Requester, I want to be prevented from paging into weeks that have already passed, so that I do not waste time on empty grids.
9. As a Requester in Europe, I want to choose Online before browsing, so that I am not shown In-Person Slots I cannot physically attend in Mérida.
10. As a Requester, I want the Modality I browsed to be the Modality I book, so that I do not silently request the wrong kind of session.
11. As a Requester, I want to see 90-minute Slots offered every 30 minutes, so that I can pick a start that suits me rather than only the Therapist's grid.
12. As a Requester, I want a Slot to disappear once it is booked, along with every overlapping start, so that I cannot request a time that is no longer free.

### Requester booking

13. As a Requester, I want the Slot I selected shown in both my zone and the Therapist's before I submit, so that I can catch a misunderstanding while it is still cheap.
14. As a Requester, I want the confirmation screen to repeat both times, so that I have an unambiguous record.
15. As a Requester, I want my zone recorded with my request, so that the Therapist knows what time this is for me without asking.
16. As a Requester, I want my confirmation email to state the time in my own zone with the zone named, so that reading it days later outside the app is unambiguous.
17. As a Requester, I want the email to also show the Therapist's time, so that I know what hour to reference if I contact her.

### Therapist

18. As the Therapist, I want to define Schedule Blocks in my own local time, so that my working day does not drift against my clock.
19. As the Therapist, I want a Schedule Block's end time to mean "sessions must finish by", so that no session runs past the hour I said I stop.
20. As the Therapist, I want a lunch break expressed as a gap between two Schedule Blocks, so that I do not need a separate concept for it.
21. As the Therapist, I want Schedule Blocks to declare their Modality, so that Monday can be In-Person only while my early mornings are Online.
22. As the Therapist, I want to see each Appointment in my own time, so that my dashboard matches the clock on my wall.
23. As the Therapist, I want each Appointment to also show the Patient's local time, so that I know what hour they are experiencing.
24. As the Therapist, I want the hour difference shown explicitly, so that I do not repeat the five-or-six-hour mistake I make by hand.
25. As the Therapist, I want my daily agenda email to arrive at a sensible hour in my own morning, so that it is useful rather than arriving overnight.
26. As the Therapist, I want the agenda to cover my calendar day, so that it does not start or end mid-morning.
27. As the Therapist, I want to block individual days with Schedule Exceptions, so that publishing wide Schedule Blocks stays safe.
28. As the Therapist, I want an all-day Schedule Exception to mean my whole calendar day, so that "I am away Tuesday" blocks Tuesday and not a shifted 24 hours.
29. As the Therapist, I want the dashboard in Spanish, so that I can use the product at all.
30. As the Therapist, I want dates and times formatted the way I read them, so that the interface does not feel foreign.

### Patient (registered)

31. As a Patient, I want my usual zone stored on my profile, so that I do not re-enter it every booking.
32. As a Patient who is travelling, I want the zone I am actually in recorded on the booking, so that the session time reflects where I will be.
33. As a Patient, I want my dashboard to show times in my own zone, so that it agrees with the confirmation I received.

### API consumer

34. As an API consumer, I want every Instant returned with an explicit offset, so that I never have to guess a zone.
35. As an API consumer, I want a flat list of Slots rather than a server-grouped map, so that I can bucket by whatever calendar my user is on.
36. As an API consumer, I want the Practice Timezone in the response, so that I never hardcode it.
37. As an API consumer, I want to request an Instant window rather than calendar dates, so that my user's week is honoured rather than the Practice's.
38. As an API consumer, I want a datetime without an offset rejected, so that the server never silently guesses a zone on my behalf.
39. As an API consumer, I want a fixed offset rejected where a zone is expected, so that I am forced to supply something that survives daylight saving.
40. As an API consumer, I want an impossible calendar date rejected rather than rolled forward, so that a typo is an error and not a booking on the wrong day.

### Operator / maintainer

41. As a maintainer, I want the Practice Timezone to be configuration, so that changing it does not mean a code change.
42. As a maintainer, I want the database to reject ambiguity structurally, so that correctness does not depend on a config line.
43. As a maintainer, I want the test suite to run in a zone that is neither production's nor the Practice's, so that implicit-local assumptions fail loudly instead of passing by coincidence.
44. As a maintainer, I want "now" injectable, so that date-dependent tests are deterministic.
45. As a maintainer, I want scheduled jobs expressed in the Practice's local time, so that their schedule reads as intent rather than arithmetic.

## Implementation Decisions

### Storage and representation — ADR-0001

- Every Instant column is `TIMESTAMP WITH TIME ZONE`, normalised to UTC in both
  directions by a custom DBAL type. The stock immutable-datetime type formats
  using the value's own zone and emits no offset, so an Instant built outside the
  server zone was stored hours off.
- Doctrine's parameter type inference binds a bare datetime through that same
  naive type. **Every temporal query parameter must state its type explicitly.**
  Omitting one produces results four hours out with no error and no exception.
- Zones are IANA identifiers everywhere. Fixed offsets are rejected at the input
  boundary — they carry no daylight-saving rules and would be wrong for half of
  every year on the main path.
- Schedule Block start/end remain short strings. They are Wall-clock rules, not
  Instants; converting them would destroy what makes them recurring.

### Availability computation — ADR-0002

- Availability walks Practice-local calendar days. The weekday a Schedule Block
  applies to is read in the Practice Timezone, not from the underlying UTC
  Instant — these disagree for roughly a fifth of every day.
- Wall-clock rules are materialised against an explicit zone, with all
  unspecified fields reset including microseconds, because requested Slots are
  matched to computed Slots by equality.
- Slot **length** and Slot **Start Increment** are separate rules. A 90-minute
  session offered every 30 minutes produces overlapping candidates; booking or
  locking one suppresses every candidate it overlaps. The existing half-open
  overlap predicate already handles this and needs no change.
- A Slot is offered only if it fits entirely inside its Schedule Block.
- Results are clipped to the requested half-open Instant window.

### API contract

- `available-slots` and `next-available-week` return a **flat list of Slots**
  plus the Practice Timezone. Server-side grouping by date was removed: the only
  calendar the server can group by is the Practice's.
- Window parameters are Instants, `to` exclusive. Week bounds in
  `next-available-week` are likewise Instants, not calendar dates.
- Every emitted Instant is UTC with an explicit offset, produced through one
  shared formatter rather than per-DTO formatting.
- Datetime input must carry `Z` or a numeric offset, and is round-trip validated
  so an impossible date fails rather than rolling forward.
- Range validation compares Instants, never strings — lexically an offset-bearing
  string can sort opposite to its position on the timeline.
- Appointments carry an optional Requester Timezone; Users carry an optional
  profile timezone. Both nullable with no backfill, because the zone of an
  existing Requester is genuinely unknown and defaulting it would fabricate data.

### Clients

- The public browser detects the Viewer Zone, allows an override, persists the
  choice, and states both zones in a banner.
- Slots are bucketed client-side by the viewer's Day key.
- The requested window is padded by a day on each side and trimmed by bucketing,
  which avoids converting local midnight back to an Instant. The widest real
  offset is under a day, so the padding always covers the viewer's week.
- Day-key arithmetic is calendar arithmetic, never millisecond arithmetic, which
  drifts across daylight-saving transitions.
- Times are formatted in the viewer's locale — Spanish copy throughout, but a
  reader in Spain sees 24-hour and one in Venezuela sees 12-hour.
- Dual display appears on the selected Slot, the confirmation, all emails, and
  therapist-facing rows; the Slot grid itself shows viewer time only, with the
  banner carrying the zone information.

### Scheduled jobs and email — ADR-0004, ADR-0005

Both are currently **defects in committed code**, not merely unfinished work:

- The daily agenda is scheduled at 07:00 container time, which is UTC, so it
  fires at 03:00 for the Therapist. Fix by declaring the schedule in the Practice
  Timezone rather than by shifting the hour, so the crontab reads as intent.
- The agenda's date comes from the process clock rather than the injected clock
  converted to the Practice Timezone.
- Email templates format Instants without converting them. Since storage moved to
  UTC, **every appointment email now states a time four hours off**, to both
  parties. Fix by converting per recipient and naming the zone in the body.

## Testing Decisions

A good test here asserts **externally observable behaviour** through a public
seam, and its expected values come from an independent source — a hand-written
absolute Instant, a worked example — never from re-formatting the object under
test. That distinction is not academic on this branch: the suite was moved to a
hostile timezone and produced zero new failures, because the existing date tests
built fixtures naively and derived expectations by formatting those same
fixtures, so both sides shifted together and could never disagree. See ADR-0003.

### Seams

Seven of the eight seams already exist; only the dashboard formatting seam is new.

1. **Availability computation** (unit) — Practice-zone materialisation, weekday
   resolution, Start Increment behaviour, block-fit, overlap suppression, window
   clipping. The highest-value seam: this is where a Wall-clock rule becomes an
   Instant.
2. **Slot and Schedule Exception overlap** (unit) — equality and overlap are
   Instant-based; an all-day exception must block the Practice-local day it
   covers and leave the next one alone.
3. **Public availability endpoints** (integration) — flat shape, Practice
   Timezone present, UTC output regardless of the caller's offset.
4. **Lock and request endpoints** (integration) — the same Instant expressed with
   different offsets must collide rather than double-book; offset-less input and
   fixed-offset zones are 422.
5. **Repository round-trip** (integration) — an Instant written from a non-UTC
   zone reads back identical, and a range expressed in the Practice zone matches
   a row written in UTC. This is the cheapest possible detector for a missing
   query-parameter type, which otherwise fails silently.
6. **Landing date utilities** (unit, Vitest) — bucketing, calendar arithmetic
   across a daylight-saving transition, zone labelling, offset difference.
7. **Email rendering** (unit) — a seam that exists but currently asserts nothing
   about times, which is exactly why the UTC regression reached committed code.
   Must be extended to assert rendered times and zone labels.
8. **Both Playwright suites** (e2e) — the reservation flow end to end.

**New seam, confirmed with the developer:** a single shared date-formatting
utility in the dashboard, unit-tested, mirroring the landing utilities. The delta
calculation and zone conversion get pinned; templates stay covered by e2e only.
Deliberately not a full component-testing setup — that is its own project and
would block the timezone fixes behind it.

### Prior art

The availability unit tests are the reference for asserting absolute UTC
Instants. The repository round-trip tests are the reference for persistence
canaries. The landing date-utility tests are the reference for the new dashboard
formatting tests. Integration tests run inside a rolled-back transaction and pin
"now" through the frozen-clock helper before issuing any request, because
handlers resolve the clock lazily at dispatch.

## Out of Scope

- **Recurring Appointments.** They do not exist in this system and are not being
  added. Schedule Blocks recur; Appointments are one-off Instants.
- **A "propose another time" flow.** The Therapist currently negotiates Online
  times ad hoc. Formalising negotiation is a scheduling-negotiation feature with
  its own lifecycle and would double the blast radius. She publishes fixed
  windows instead.
- **Per-therapist timezone.** The Practice Timezone is configuration behind a
  provider port. Promoting it to a database column is a one-class change if a
  second practitioner ever exists.
- **A dashboard i18n framework.** Spanish is inline, matching the public site.
  Message extraction and translation catalogues buy machinery for languages that
  will not exist.
- **Dashboard component tests.** Only the formatting utility gets a unit seam.
- **Historical backfill of Requester Timezone.** Unknowable; left null.
- **The schedule manager UI.** Still unbuilt, and should be built against this
  model rather than retrofitted.

## Further Notes

### Verification status — read before trusting any test result

Everything ran green in continuous integration on the merge: the API PHPUnit
suite, both Playwright suites, dashboard lint and build, and the landing Vitest
suite.

**A green Playwright run does not mean timezone behaviour is verified, and it
would be a mistake to read it that way.** The suites were expected to fail and
did not, for a reason worth recording: they are coupled to the *flow*, not to
times. They assert that a Slot button exists — matched on the text "min", which
matches any duration — and that a reservation completes. They never referenced
the grouped-by-date response shape that was removed.

So the passes confirm nothing regressed structurally. They confirm nothing about
zones. There is currently **no assertion anywhere** that the zone banner names
both zones, or that the selected Slot and confirmation show the Practice time
alongside the Requester's. Adding those is the substance of ticket 09, and it is
not made redundant by the suites being green.

Two related caveats remain live: the seeded schedule is still the old generic
Monday-to-Friday pattern rather than the Therapist's real hours (ticket 08), and
session length is still 50 minutes rather than 90 (ticket 04). Both suites
therefore currently exercise a Slot grid that is not the one the practice will
run on.

### The Therapist's real schedule

Confirmed directly with her. Every Schedule Block end means "sessions must finish
by", which is what makes her stated capacity and her stated finishing times
consistent at 90 minutes:

| Day | Blocks | Last start | Modality |
|---|---|---|---|
| Monday | 08:00–12:00, 13:30–19:30 | 18:00 | In-Person |
| Tuesday | 06:30–10:30 | 09:00 | Both |
| Wednesday–Sunday | 07:00–12:00, 13:30–20:30 | 19:00 | Both |

Lunch is the gap between blocks and applies every working day. She expects to be
booked at weekends. Her "only two consultations fit" on Tuesday describes
**capacity**, not offered starts — with 30-minute increments Tuesday offers six
candidate starts and still caps at two non-overlapping sessions.

### Known defects in committed code

Two items in this spec are regressions rather than gaps, and should be
prioritised accordingly: email times are four hours wrong for both parties, and
the daily agenda fires at 03:00 for the Therapist. Both are described in ADR-0005
and ADR-0004.

A third, smaller: the all-day flag on a Schedule Exception is a passthrough
boolean that nothing reads, so "all day" means only what the caller's own range
happened to mean. Availability itself stays correct; the defect is confined to
what a caller can express.
