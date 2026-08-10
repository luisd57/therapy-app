# 05 — Modality-first browsing on the public site

**What to build:** A Requester chooses Online or In-Person before seeing Slots,
and the Modality they browsed is the Modality they book.

Two problems today. First, the browser opens on an "all modalities" filter and
then silently books as Online — a Requester who browsed everything and picked a
Slot from an In-Person-only Schedule Block submits an Online request. Second,
In-Person only happens in Mérida, so for a Requester in Madrid those Slots are
noise that makes the grid look fuller than it usefully is.

Make Modality an explicit up-front choice, preselecting Online when the detected
Viewer Zone is not the Practice Timezone. A visitor browsing from abroad almost
certainly wants Online; a visitor in Venezuela may want either.

While in this area, the Practice Timezone is hardcoded as a fallback default in
several places on the public site. These are IANA identifiers rather than
offsets, so they carry no daylight-saving bug, but they are overwritten by the
value the API reports and should not be duplicated.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Modality is an explicit choice before Slots render
- [ ] Online is preselected when the Viewer Zone differs from the Practice Timezone
- [ ] The Modality shown in the grid is the Modality submitted — no silent substitution
- [ ] Switching Modality refetches Slots
- [ ] Hardcoded Practice Timezone fallbacks are reduced to a single definition
- [ ] Landing unit suite green
- [ ] Landing build green
