# 05 - Cover the Doctrine DBAL types

**What to build:** every custom type that converts a Value Object to a column and
back is verified in both directions, so a conversion bug fails a unit test rather
than surfacing as a corrupted row.

Ten of the eleven types have no direct test. Only the hashed string type does.
The rest are reached, if at all, through a repository test that happens to store
and reload an entity, which exercises the round trip but never the edges: a null,
a malformed stored value, or a value that is already the target type.

The UTC Instant type is the one to start with. ADR-0001 makes it the single place
the whole store-as-UTC decision is enforced, and it currently has no unit test at
all. Its behaviour is only observable today through an appointment repository
test, which means a change to it is reported as a repository failure with no
indication of the real cause.

Round-tripping a value is necessary but not sufficient. A type that dropped the
zone on the way in and reapplied the process zone on the way out would round-trip
cleanly and still be wrong, which is the tautology ADR-0003 warns about. Assert
the stored representation directly, not only that what comes back matches what
went in.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each custom type has a test covering conversion to the database value and back
- [ ] The stored representation is asserted directly, not only the round trip
- [ ] The UTC Instant type is asserted to store UTC regardless of the zone of the value handed to it
- [ ] Null handling is covered for every type that permits it
- [ ] A malformed stored value raises the conversion error rather than producing a broken object
- [ ] Full API suite green
