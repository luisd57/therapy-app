---
paths:
  - API/src/**/*.php
---
# API Architecture: Hexagonal (Ports & Adapters)

## Layer Structure & Dependency Rule

Infrastructure → Application → Domain (never the reverse)

src/Domain/ (core business logic, no framework deps), src/Application/ (use cases, orchestration), src/Infrastructure/ (external concerns, adapters).

## ORM Pragmatism (deliberate, do not "fix")

- ORM attributes (`#[ORM\...]`) live directly on Domain entities. No separate mapping layer: the abstraction only pays off if you swap ORMs, and at this scale that isn't happening.
- Entities declare their relations: `ManyToOne` on the owning side, `OneToMany` inverse on `User`. See ADR-0007 for what this replaced and what it cost.
- Repositories flush; handlers NEVER call flush or manage transactions. No transaction middleware - an accepted trade-off.

## ORM Relations

Every relation points at `User`; `SlotLock` has none. Pin each join column with an explicit
`#[ORM\JoinColumn(name:, referencedColumnName: 'id', onDelete:)]` so the mapping states the
delete rule instead of leaving it in a migration only.

- **The entities own the schema.** `doctrine:schema:validate` passes and
  `doctrine:migrations:diff` comes back empty. Keep it that way: a new index or column default
  goes on the entity, and the migration is then generated, not hand-written. If `diff` proposes
  dropping an index, the entity is missing an `#[ORM\Index]` - do not accept the drop.
- **No `cascade`, no `orphanRemoval`, ever.** The `ON DELETE` rules in the migrations own
  deletion. A PHP-side `cascade: ['remove']` on `$appointments` would delete rows the database
  only means to null out. The cost: `UserRepositoryInterface::delete()` throws if an unflushed
  entity pointing at that user is still managed.
- **Never `fetch: 'EAGER'`.** It is global and fires on `find()` too. When a caller genuinely
  reads through an association, put `->join(...)->addSelect(...)` in that one repository method.
- **Do not iterate the inverse collections in application code.** The practice has one therapist,
  so `getSentInvitations()` and `getScheduleExceptions()` are whole tables with no `LIMIT`; both
  are `EXTRA_LAZY` so `count`/`contains`/`slice` stay in SQL. The paginated repository methods are
  the read path for user-scoped lists.
- **Repository ports still take `UserId`, not `User`** - a read should not force callers to load a
  user. Doctrine accepts the identifier value for an association field.
- `User::$id` is the one identifier that is not `readonly`. Doctrine's `ReadonlyAccessor` compares
  with `!==` when re-setting it during proxy initialization, and two `UserId` value objects holding
  the same UUID fail that. `User` is the only entity anything maps a `ManyToOne` onto, so it is the
  only one that needs this.

## Domain Layer
Entities (User, Appointment, TherapistSchedule, ScheduleException, SlotLock, InvitationToken, PasswordResetToken), Value Objects (immutable, self-validating, private constructors - the `Id/` types plus Email, Phone, Timezone, Address, TimeSlot), Enums (backed: UserRole, AppointmentStatus, AppointmentModality, WeekDay - these are NOT Value Objects, so the private-constructor and static-factory rules cannot apply to them), Repository Interfaces (driven ports), Service Interfaces (driven ports: EmailSenderInterface, JwtTokenGeneratorInterface, PasswordHasherInterface), Domain Services (AvailabilityComputer), Parameter Objects (AvailabilityContext - public constructor by design, it carries arguments rather than modelling a value), Exceptions.

## Application Layer
Handlers (one per use case, `__invoke()` entry point), DTOs (Input/ and Output/), Application Services (orchestration, e.g. AppointmentRequestService).

## Infrastructure Layer
Persistence/Doctrine/Type (custom DBAL types for VO↔DB), Persistence/Doctrine/Repository (implementations), Security (password hasher, JWT, Redis blocklist), Email (mailer), Http/Controller (one action per class, delegate to handlers - see `## Controllers`), Http/EventSubscriber (rate limiting, security headers), Console (CLI commands).

## Interfaces

Package by role, not by construct. An interface lives next to the concept it names, never in an
`Interface/` sibling namespace: the `Interface` suffix already marks the construct, so the namespace
would only repeat it. The split that would carry meaning is port vs domain logic, not interface vs
class, and `Repository/` already names the port kind worth separating.

A single-implementation interface over a `final readonly` class is a deliberate test seam, not
ceremony. PHPUnit cannot mock a final class and the toolchain carries no bypass-finals, so
`AvailabilityComputerInterface` (Domain) and `AppointmentRequestServiceInterface` (Application)
exist to give unit tests something to double. Deleting one costs either the `final` or the isolation
of the tests that mock it.

## Controllers

One route action per class, one class per file, one test file per class:
`Http/Controller/{Group}/{Resource}/{Action}Controller.php`, a `final` class whose only public
method is `__invoke()`. `AuthController::therapistLogin` became `User/Auth/TherapistLoginController`.
`{Group}` reuses the module name from `src/Domain/` where the endpoint belongs to one, so the trees
line up. `Health/` has no domain module and does not need one. There is no `Api/` segment: this
deployable serves nothing but the API, so the segment named only itself.

- Full URL literal in the `#[Route]`, no class-level prefix, `name:` always explicit. Route names are
  matched by `RateLimitSubscriber` and URLs by `security.yaml`, so copy both verbatim when moving an
  action - a rename silently drops rate limiting or an access rule. Say URL, not path, when you mean
  one: this layer names directories and URLs in the same breath.
- Per-action `#[IsGranted]`, since there is no class left to hang it on. `ProtectedRouteRolesTest`
  catches a forgotten one under `/api/therapist` or `/api/patient`, which is where roles are required
  today. Extend its prefix list if a new protected area appears.
- Constructor takes only what this action uses. Handlers stay injected as method arguments.
- A helper used by two or more actions is a trait in `Http/Controller/`. One caller means a private
  method on that controller.
- The rule splits, it does not rename. A controller already holding exactly one action stays as it is.

Why: `## Errors` in api-conventions.md requires every action to catch the domain exceptions it can
produce, so a grouped controller grows with each endpoint and has no natural stopping point. Splitting
also gives each action a test file of its own, which one shared controller test cannot.

## File Patterns

### New Use Case
1. Input DTO in `src/Application/{Domain}/DTO/Input/`
2. Handler in `src/Application/{Domain}/Handler/`
3. Output DTO in `src/Application/{Domain}/DTO/Output/` if needed

### New Entity
1. Entity in `src/Domain/{Domain}/Entity/` with `#[ORM\Entity]`, `#[ORM\Table]`, `#[ORM\Column]`
2. Repository Interface in `src/Domain/{Domain}/Repository/`
3. Custom DBAL Type in `src/Infrastructure/Persistence/Doctrine/Type/`
4. Register type in `config/packages/doctrine.yaml`
5. Repository impl in `src/Infrastructure/Persistence/Doctrine/Repository/`
6. Migration: generate it with `doctrine:migrations:diff` and keep it. Declare indexes and column defaults on the entity, never only in the migration, or the next diff proposes dropping them

### New API Endpoint
1. `{Action}Controller` placed per `## Controllers` - never a second method on an existing controller
2. Input DTO + Handler if new use case
3. Update `config/packages/security.yaml` if new access rules needed
4. Test file mirroring the controller under `tests/Integration/Infrastructure/Http/Controller/`
