# 05 - Cover the Doctrine DBAL types

> Frozen record, resolved 2026-09-15.

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

**Status:** resolved

**Resolved by:** [PR #87](https://github.com/luisd57/therapy-app/pull/87)

- [x] Each custom type has a test covering conversion to the database value and back
- [x] The stored representation is asserted directly, not only the round trip
- [x] The UTC Instant type is asserted to store UTC regardless of the zone of the value handed to it
- [x] Null handling is covered for every type that permits it
- [x] A malformed stored value raises the conversion error rather than producing a broken object
- [x] Full API suite green

## Comments

**2026-09-15** - The UTC Instant type was checked by mutation, not by a green suite: dropping the
UTC conversion on write or read, declaring a naive column, and dropping the parse error's cause each
turn a test red. The column declaration had no test at all before review found it. 700 tests
before, 777 after.

The malformed-value criterion was a production change. The nine types backed by a value object
threw the value object's bare `InvalidArgumentException`. They now throw Doctrine's
`ValueNotConvertible` with that exception as the cause. `HashedStringType` is one-way and has no
malformed case.

Two things came out of the work, both left alone.

The value object types store a plain string as-is. That looks like a hole and is not: every
repository `find()` passes `$id->getValue()`, and `findByEmail` passes the email string, so the
tests pin it. Only a raw string straight from a request would skip normalisation.

A stored Instant with no offset is read in the PHP process zone, with no error. Postgres always
sends an offset for `TIMESTAMP WITH TIME ZONE`, so it cannot happen while the column-declaration
test holds.
