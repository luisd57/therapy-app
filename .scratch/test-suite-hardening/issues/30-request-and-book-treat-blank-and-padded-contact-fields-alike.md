# 30 - request() and book() treat blank and padded contact fields alike

**What to build:** tests that fail when an Appointment accepts a name, city or
country made only of whitespace, or stores one with its padding, on either
entry point.

`request()` and `book()` carry the same three checks and the same three trims,
written twice. The tests cover them unevenly (2026-10-09):

- `request()`: whitespace-only is tested for the name, not for the city or the
  country.
- `book()`: only the empty string is tested. Nothing checks that it stores the
  trimmed value, which `request()` does pin.

`book()` is the Therapist typing in an Appointment for someone who phoned, so
padded input is the likely case there, not the rare one.

**Mutants this should kill:** the eight kind 1 rows for
`src/Domain/Appointment/Entity/Appointment.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The FalseValue row on `reconstitute()` is kind 2 and stays.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] `request()` refuses a whitespace-only city, and a whitespace-only country, each with the other fields valid
- [ ] `book()` refuses a whitespace-only name, city and country, each with the other fields valid
- [ ] An Appointment made by `book()` from a padded name, city and country returns all three without the padding
- [ ] A one-file Infection run no longer reports the eight rows as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
