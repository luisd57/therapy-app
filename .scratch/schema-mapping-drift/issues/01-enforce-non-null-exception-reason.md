# 01 - Enforce a non-null exception reason in the database

**What to build:** A Schedule Exception's reason is always a string in the
database, never absent, matching the invariant the application already enforces.

This is a **defect fix**, and a narrow one. The entity maps `reason` as
non-nullable and types it `string`, but `Version20260215000000` created the column
as `VARCHAR(500) DEFAULT ''` with no `NOT NULL`. Nothing in the application can
write a NULL - every write path is typed `string` and defaults to empty - so that
side is already closed.

The exposure is on the read path. A NULL arriving from outside the application,
through a manual SQL session or a restore from an odd dump, fails entity
hydration with a TypeError. Because `reason` is a promoted readonly constructor
property, that failure takes the whole entity rather than one field, so every
query touching the row breaks - including the availability computation that
subtracts Schedule Exceptions from the public Slot grid. A single bad row would
empty the grid rather than degrade one view.

The database is the side that is wrong, and it looks like an oversight rather
than a decision: the very next column in the same `CREATE TABLE` is
`is_all_day BOOLEAN NOT NULL DEFAULT FALSE`, which is the shape `reason` should
have had. Modelling the reason as always a string and sometimes empty avoids the
usual ambiguity between NULL and empty string, and the application already
commits to it.

**Fix the original migration in place rather than stacking a corrective one.**
The series is six migrations long and has only ever run in development, so it is
still malleable, and a migration whose only job is to patch another migration in
the same unreleased series is debt taken on for no benefit. Add the missing
`NOT NULL` to the column definition and rebuild.

Do not generate the change with `doctrine:schema:update --force`. Entities here
declare no relation attributes by design, so the generated diff also proposes
dropping every hand-written foreign key constraint. See the ORM Pragmatism rule
in `.claude/rules/api-architecture.md`.

Rebuilding drops local data. That is acceptable here because the application is
far from production and no deployed database depends on this series, but it does
mean both the development and the separate test database need recreating, and
anyone else working the repo has to do the same. Continuous integration builds
from the migrations on every run, so it needs nothing.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** https://github.com/luisd57/therapy-app/pull/65

- [x] The column is declared `NOT NULL` in the migration that creates it, keeping the empty-string default so inserts that omit it still succeed
- [x] The change is made by editing the existing migration, not by adding a new one
- [x] No foreign key constraint or index is dropped as a side effect
- [x] A rebuilt database reports the column as non-nullable
- [x] `MappingMatchesSchemaTest` gains an assertion for this column that fails if the mapping and the database disagree again
- [x] The schema diff no longer proposes `ALTER reason SET NOT NULL`
- [x] `API/docs/database-schema.md` records the column as non-nullable and drops the note that the entity is stricter than the DDL
- [x] Full API suite green against the rebuilt database
