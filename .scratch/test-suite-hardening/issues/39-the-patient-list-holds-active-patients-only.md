# 39 - The Patient list holds active Patients only

**What to build:** a test that fails when the Therapist's Patient list shows
an inactive Patient, leaves out an active one, or reports a total that
disagrees with the list.

No test stores a Patient and then reads the list (2026-10-09). The endpoint
test checks shape and paging on an empty result. The list and its total could
both flip to inactive Patients only and the suite would pass.

**The fixture has to be uneven.** With one active and one inactive Patient,
a total that counts the inactive ones is also 1 and the assertion passes on the
wrong answer. Store two active and one inactive.

Ticket 23 also seeds a Patient at this endpoint, to pin the key set of a list
item. Neither ticket blocks the other. Whichever lands second builds on the
first one's fixture.

**Mutants this should kill:** the two kind 1 rows for
`src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineUserRepository.php`
in `.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] With two active Patients and one inactive Patient stored, the list holds the two active ones and not the inactive one
- [ ] The total reported with that list is 2
- [ ] A one-file Infection run no longer reports the two rows as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
