# 42 - A profile update sets the timezone, and keeps it when none is sent

**What to build:** tests that fail when a profile update drops the timezone it
was given, or wipes the stored one when it was given none.

Phone and address each have both tests, set and keep. Timezone has neither
(2026-10-09), so its condition can flip unseen. A Patient who sets a zone
would keep the old one, and one who only changes a phone number would lose
theirs.

**Mutants this should kill:** the kind 1 NotIdentical row for
`src/Domain/User/Entity/User.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A profile update carrying a timezone stores that timezone
- [ ] A profile update carrying no timezone leaves a stored timezone in place
- [ ] A one-file Infection run no longer reports the row as escaped
