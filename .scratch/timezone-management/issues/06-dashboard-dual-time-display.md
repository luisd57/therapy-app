# 06 — Dashboard date-formatting seam and dual-time display

**What to build:** The Therapist sees every Appointment in her own time, the
Patient's time beside it, and the hour difference stated explicitly.

She reasons in deltas — she describes Europe as "5 o 6 horas" ahead — and that
wobble is European daylight saving. The delta is precisely where she makes the
mistake by hand, so the interface should compute it rather than leave it to her.

The dashboard currently has no date-formatting logic at all: bare date pipes with
no zone and no locale, rendering in English in the browser's zone. This ticket
introduces **one shared date-formatting utility with unit tests** — the single new
test seam agreed for this work, mirroring the utility the public site already
has. Locale configuration for dates belongs here too, since dates cannot render
in Spanish without it.

Deliberately not a full component-testing setup. That is its own project and
would block this behind it. Templates stay covered by end-to-end tests; the
formatting and delta logic — the part most likely to be wrong — gets pinned by
unit tests.

Any user-facing string this ticket introduces is written in Spanish, so it does
not depend on ticket 07.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] A shared date-formatting utility exists, with unit tests, covering conversion into a named zone and the delta between two zones
- [ ] The delta is correct on both sides of a European daylight-saving transition — five hours in winter, six in summer
- [ ] Appointment rows show the Practice Timezone time as primary
- [ ] Appointment rows show the Patient's time as secondary, derived from the Requester Timezone, falling back to the Practice Timezone when null
- [ ] The hour delta is shown alongside, with the Patient's zone named
- [ ] Dates render in Spanish
- [ ] Existing date displays route through the shared utility rather than bare pipes
- [ ] Dashboard lint and build green
