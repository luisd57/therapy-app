# 39 - The Patient list holds active Patients only

**What to build:** a test that fails when the Therapist's Patient list shows
an inactive Patient, leaves out an active one, or reports a total that
disagrees with the list.

No test stores a Patient and then reads the list (2026-10-09). The endpoint
test checks shape and paging on an empty result. The list and its total could
both flip to inactive Patients only and the suite would pass.

Ticket 23 also seeds a Patient at this endpoint, to pin the key set of a list
item. Neither ticket blocks the other. Whichever lands second builds on the
first one's fixture.

**Mutants this should kill:** the two kind 1 rows for
`src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineUserRepository.php`
in `.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] With one active and one inactive Patient stored, the list holds the active one and not the inactive one
- [ ] The total reported with that list counts the active one only
- [ ] A one-file Infection run no longer reports the two rows as escaped
