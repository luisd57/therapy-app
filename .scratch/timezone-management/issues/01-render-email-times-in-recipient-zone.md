# 01 - Render email times in the recipient's zone

**What to build:** Every email the practice sends states the correct time for the
person reading it, and names the zone it is in.

This is a **defect fix, not a new feature**. Since Instant storage moved to UTC,
the email templates format without converting, so every appointment email
currently states a time four hours off - to the Patient and to the Therapist
alike. A Requester who books 09:00 in Caracas is told "1:00 PM".

Requester-facing mail renders in the Appointment's Requester Timezone, falling
back to the Practice Timezone when it is null. Therapist-facing mail renders in
the Practice Timezone. Both name the zone in the body and show the other party's
time as a secondary line, matching the dual-display rule the booking screens
already follow. An email is read outside the app, often days later, with no
banner to explain which clock is meant.

See ADR-0005 for the decision and the alternatives rejected.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Appointment request, confirmation, and cancellation mail render times in the Requester Timezone when one is recorded
- [ ] The same mail falls back to the Practice Timezone when the Requester Timezone is null
- [ ] Therapist-facing mail, including the daily agenda, renders in the Practice Timezone
- [ ] Every rendered time is accompanied by its zone, readable without app context
- [ ] Each mail shows the other party's time as a secondary line
- [ ] The existing email rendering seam asserts on rendered times and zone labels - it currently asserts neither, which is how this regression reached committed code
- [ ] A test covers the null Requester Timezone fallback
- [ ] A test fails if the conversion is ever removed again
- [ ] Full API suite green
