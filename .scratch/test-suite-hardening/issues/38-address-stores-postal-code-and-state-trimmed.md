# 38 - Address stores postal code and state trimmed

**What to build:** a test that fails when an Address keeps the padding around
its postal code or its state.

Street, city and country are trimmed and pinned. The two optional fields are
trimmed too, and no test passes a padded one (2026-10-09).

**Mutants this should kill:** the two kind 1 rows for
`src/Domain/User/ValueObject/Address.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] An Address created with a padded postal code returns it without the padding
- [ ] An Address created with a padded state returns it without the padding
- [ ] A one-file Infection run no longer reports the two rows as escaped
