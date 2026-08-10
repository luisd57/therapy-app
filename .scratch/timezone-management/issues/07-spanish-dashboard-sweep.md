# 07 — Spanish dashboard sweep

**What to build:** The Therapist can read and use the dashboard.

She speaks only Spanish. The dashboard is currently entirely in English — page
copy, navigation, table headers, buttons, form labels, validation messages. This
makes the product unusable by its primary user, independent of anything to do
with timezones.

**This is not timezone work.** It is separated so it neither blocks nor is
blocked by the timezone fixes, and so that the two do not land as one
unreviewable change touching every template for two unrelated reasons.

Translate inline, matching the approach the public site already uses. No
internationalisation framework: message extraction and translation catalogues buy
machinery for languages that will not exist in a single-practice product, and
would themselves become a project.

Ticket 06 handles date and time rendering, including its own Spanish strings, so
these two can proceed in either order. They touch overlapping templates; whichever
lands second should expect to merge.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] The document language is Spanish
- [ ] Navigation, page titles and headings are in Spanish
- [ ] Table headers, buttons, form labels and placeholders are in Spanish
- [ ] Validation and error messages shown to the user are in Spanish
- [ ] Empty states and confirmation dialogues are in Spanish
- [ ] Terminology matches the project glossary rather than drifting to synonyms
- [ ] No internationalisation framework is introduced
- [ ] Dashboard lint and build green
