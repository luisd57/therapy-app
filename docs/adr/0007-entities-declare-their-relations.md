# Entities declare their relations

Status: accepted, implemented on 2026-08-30. Reverses the "NO Doctrine relation attributes" rule
that `.claude/rules/api-architecture.md` had carried since the API was laid out.

## The shape this replaced

Every cross-entity link was a bare UUID column typed through a custom DBAL type. `Appointment` held
`?UserId $patientId` mapped as `#[ORM\Column(type: 'user_id', nullable: true)]`, and the only place
recording that it pointed at `users.id` - or that deleting a user nulls it - was
`migrations/Version20260303000000.php`.

That bought real things, and they are worth stating because they are what we gave up:

- Aggregate boundaries were enforced by the type system. `Appointment` could not reach into `User`,
  so every cross-aggregate read was a named repository method with its cost visible at the call site.
- N+1 was not discouraged, it was impossible.
- The Unit suite needed no ORM and no database to build an entity.
- Deletion semantics lived in exactly one place.

The cost was that the schema was not discoverable from the code, and it was permanent rather than
one-off. `doctrine:migrations:diff` could not see the hand-written constraints and proposed dropping
them on every run, so every migration was hand-written and hand-reviewed;
`Version20260810120000` carries a docblock apologising for exactly this. `doctrine:schema:validate`
could never pass, so mapping drift was found by a failing test rather than by a command.

## Decision

Owning `ManyToOne` on each of the five children, inverse `OneToMany` on `User`, with the join column
pinned by an explicit `#[ORM\JoinColumn]` carrying the name and the `onDelete` rule. The `UserId`
field is replaced, not duplicated; the `getPatientId()`-style getters remain as delegates so DTOs and
repository ports did not churn.

No column changed type or name: `UserIdType` extends `GuidType`, so a join column to `User`'s
identifier is the same `UUID` column it already was.

`doctrine:schema:validate` now passes on both mapping and database, and
`doctrine:migrations:diff` reports no changes. That is the whole point of the change, and it is the
thing to check before believing this ADR still holds.

## What the first honest diff revealed

The generated diff did not touch the foreign keys at all - Doctrine matches those by table and
column rather than by name, so the hand-written constraints already satisfied the new mappings. What
it did propose was dropping six indexes and eight column defaults that existed in the database but
were never declared on an entity. That drift was always there; the FK noise had been hiding it.

Accepting the generated migration would have been a performance regression. The indexes and defaults
were moved onto the entities instead, and the migration that shipped renames five index names and
nothing else. **The rule this establishes: if `diff` proposes dropping an index, the entity is
missing an `#[ORM\Index]`.**

## Considered and rejected

**Unidirectional `ManyToOne` only, no collections on `User`.** Recommended at the time and rejected
by choice: the ask was for the classic shape. The collections are the part that carries ongoing risk
(see Consequences).

**Keeping the `UserId` field and adding a read-only association over the same column**
(`insertable: false, updatable: false`). Much smaller diff, but it duplicates state and is not the
shape anyone means by "declare the relation".

**`fetch: 'EAGER'` to sidestep N+1.** Rejected outright: it is global, fires on `find()` by id, and
cannot be switched off per query.

## Consequences

**N+1 is now possible where it previously was not.** Nothing does it today - `Appointment`
denormalises `fullName`, `email`, `phone`, `city` and `country` onto itself, so it never needs the
patient's row, and the only consumer that reads through an association
(`ResetPasswordHandler`) got *faster*, losing a `findById()`. `EntityRelationsTest` pins this by
asserting the patient proxies are still uninitialized after the whole page has been mapped to DTOs,
so adding a `getPatient()->getFullName()` to `AppointmentOutputDTO` fails the build.

**Three collections are unbounded and must not be iterated.** The practice has one therapist, so
`$therapist->getSentInvitations()` and `getScheduleExceptions()` are whole tables with no `LIMIT`
that read like plain getters. Both are `EXTRA_LAZY`, which keeps `count`/`contains`/`slice` in SQL
but does not stop a `foreach`. The paginated repository methods remain the read path.

**The two domain modules now reference each other.** `User` imports three
`App\Domain\Appointment\Entity\*` classes where the dependency used to run one way. No runtime cost,
but the invariant a reader could previously assume is now only a convention. PHPStan (ticket 11) will
not catch it - core PHPStan has no dependency-direction rules, so this needs deptrac or a custom rule.

**`User::$id` is no longer `readonly`.** It is the only entity anything maps a `ManyToOne` onto, so
the only one Doctrine builds proxies for. Initializing a proxy re-sets the identifier, and
`ReadonlyAccessor` compares with `!==` - two `UserId` value objects holding the same UUID fail that
and it throws. No setter exists, so the field is still immutable in practice. Any future entity that
becomes an association target will need the same treatment.

**Deleting a `User` is sharper than it looks.** No `cascade` or `orphanRemoval` is configured
anywhere, deliberately: the database's `ON DELETE` rules own deletion, and a PHP-side
`cascade: ['remove']` on `$appointments` would delete rows the database only means to null out. The
catch is that Doctrine validates the associations of everything else currently managed, so
`UserRepositoryInterface::delete()` throws if an unflushed entity pointing at that user is still in
the unit of work. Nothing in production deletes users today. The port documents it.

**Five handlers gained a `findById` and a `UserNotFoundException` path.** Where the id comes from the
authenticated principal the exception cannot fire over HTTP, so per `## Errors` in
`api-conventions.md` those actions add no catch block.
