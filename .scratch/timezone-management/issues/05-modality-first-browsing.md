# 05 - Modality-first browsing on the public site

**What to build:** A Requester chooses Online or In-Person before seeing Slots,
and the Modality they browsed is the Modality they book.

**This is an active defect, not a latent one.** It was filed as a nice-to-have
bundled with modality-first browsing; it is in fact one of two defects making CI
red, and it breaks a real user path.

**It is not the only one.** This ticket previously claimed to be the sole cause
of a red suite. Ticket 12 is the other, and the two mask each other: 05 fires on
days where the first offered Slot is In-Person only, 12 fires from Friday
afternoon through Sunday, when the next available Slot falls outside the week the
grid renders. Fixing either alone leaves the suite red on the other's days, so
neither is done until both are.

The browser opens on an "all modalities" filter, which applies no filter when
fetching, then silently books as Online. So a Requester who browsed everything
and picked a Slot from an In-Person-only Schedule Block submits an Online
request, and the API correctly refuses it with `SLOT_NOT_AVAILABLE`. The
Requester sees "este horario ya no está disponible" for a Slot that was never
available to them in the first place.

**Evidence.** CI run 31750161621 (PR #14, a docs-only change) failed two landing
e2e specs on exactly this. It ran Thursday 18:33 practice-local; Thursday's
seeded blocks had already ended, so the first offered Slot was Friday
08:00-12:00, which the seed marks `supportsOnline: false`. The grid showed it,
the flow booked it as Online, the API refused, and "Solicitud recibida" never
appeared.

It is **date-dependent**, which is why it hid: PRs #11 and #12 ran on days where
the first offered Slot happened to support Online. Nothing changed between those
runs and the failing one except the calendar.

Secondary, and the reason the fix is shaped this way: In-Person only happens in
Mérida, so for a Requester in Madrid those Slots are noise that makes the grid
look fuller than it usefully is.

Make Modality an explicit up-front choice, preselecting Online when the detected
Viewer Zone is not the Practice Timezone. A visitor browsing from abroad almost
certainly wants Online; a visitor in Venezuela may want either.

While in this area, the Practice Timezone is hardcoded as a fallback default in
several places on the public site. These are IANA identifiers rather than
offsets, so they carry no daylight-saving bug, but they are overwritten by the
value the API reports and should not be duplicated.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #39](https://github.com/luisd57/therapy-app/pull/39)

- [x] Modality is an explicit choice before Slots render
- [x] Online is preselected when the Viewer Zone differs from the Practice Timezone
- [x] The Modality shown in the grid is the Modality submitted - no silent substitution
- [x] Switching Modality refetches Slots
- [x] Hardcoded Practice Timezone fallbacks are reduced to a single definition
- [x] A test pins the preselection: Online is chosen when the Viewer Zone differs from the Practice Timezone, and not forced when they match
- [x] A test pins that the browsed Modality is the one submitted, so the silent substitution cannot come back
- [x] A Requester can never select a Slot whose Schedule Block does not support the Modality being booked
- [x] The landing e2e suite passes on a day where the first offered Slot is In-Person only - the case that exposed this (made reachable on any day rather than waited for, see Comments)
- [x] Landing unit suite green
- [x] Landing build green

## Comments

**2026-08-19** - The last criterion was not met by waiting for a Thursday.
Availability is computed server-side from the real clock, so no browser-side
control (`timezoneId`, `page.clock`) can reach that day, and freezing the API's
clock would apply to lock expiry, `created_at` and the cron container too.

`e2e/in-person-only-schedule.spec.ts` moves the data instead: it logs in as the
Therapist, swaps the active Schedule Blocks for a single In-Person-only block,
and restores them afterwards. With one block in the schedule it is the first
offered Slot on every day of the week, so the case runs on every CI run rather
than one evening a week. Verified green in the `e2e` job of run 32248456451.

Two defects surfaced during review that the ticket had not named, both the same
substitution by another route: a failed refetch left the previous Modality's
Slots on screen and clickable, and switching Modality mid-load changed the label
without refetching. Both fixed, the first pinned by a test.
