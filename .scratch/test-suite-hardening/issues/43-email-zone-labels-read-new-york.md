# 43 - Email zone labels read "New York"

**What to build:** a test that fails when an email names a zone with its
underscore, "New_York" where a reader expects "New York".

Emails name the zone a time was rendered in by its city (ADR-0005). IANA
writes a city of several words with underscores, and the label swaps them for
spaces. No test uses such a zone (2026-10-09). They are ordinary input here:
America/New_York, America/Los_Angeles, America/Argentina/Buenos_Aires.

**Mutants this should kill:** the kind 1 UnwrapStrReplace row for
`src/Infrastructure/Email/Appointment/RenderedTime.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] An email to a Requester whose timezone is America/New_York labels the time "New York", with no underscore
- [ ] A one-file Infection run no longer reports the row as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
