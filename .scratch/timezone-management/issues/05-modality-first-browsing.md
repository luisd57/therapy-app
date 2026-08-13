# 05 - Modality-first browsing on the public site

**What to build:** A Requester chooses Online or In-Person before seeing Slots,
and the Modality they browsed is the Modality they book.

**This is an active defect, not a latent one.** It was filed as a nice-to-have
bundled with modality-first browsing; it is in fact the only thing currently
making CI red, and it breaks a real user path.

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

**Status:** ready-for-agent

- [ ] Modality is an explicit choice before Slots render
- [ ] Online is preselected when the Viewer Zone differs from the Practice Timezone
- [ ] The Modality shown in the grid is the Modality submitted - no silent substitution
- [ ] Switching Modality refetches Slots
- [ ] Hardcoded Practice Timezone fallbacks are reduced to a single definition
- [ ] A test pins the preselection: Online is chosen when the Viewer Zone differs from the Practice Timezone, and not forced when they match
- [ ] A test pins that the browsed Modality is the one submitted, so the silent substitution cannot come back
- [ ] A Requester can never select a Slot whose Schedule Block does not support the Modality being booked
- [ ] The landing e2e suite passes on a day where the first offered Slot is In-Person only - the case that exposed this
- [ ] Landing unit suite green
- [ ] Landing build green
