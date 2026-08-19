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
- NO Doctrine relation attributes (`OneToMany`, `ManyToOne`, `mappedBy`, `inversedBy`). Entities reference other aggregates by ID value objects; repositories resolve them. Never introduce bidirectional mappings - they buy coupling between aggregates and lazy-loading surprises, and explicit repository lookups are cheaper to reason about.
- This is an ORM-mapping rule, NOT a schema rule. Migrations still declare `FOREIGN KEY` constraints with explicit cascade semantics: referential integrity belongs in the database.
- Repositories flush; handlers NEVER call flush or manage transactions. No transaction middleware - an accepted trade-off.

## Domain Layer
Entities (User, Appointment, TherapistSchedule, ScheduleException, SlotLock, InvitationToken, PasswordResetToken), Value Objects (immutable, self-validating, private constructors - the `Id/` types plus Email, Phone, Timezone, Address, TimeSlot), Enums (backed: UserRole, AppointmentStatus, AppointmentModality, WeekDay - these are NOT Value Objects, so the private-constructor and static-factory rules cannot apply to them), Repository Interfaces (driven ports), Service Interfaces (driven ports: EmailSenderInterface, JwtTokenGeneratorInterface, PasswordHasherInterface), Domain Services (AvailabilityComputer), Parameter Objects (AvailabilityContext - public constructor by design, it carries arguments rather than modelling a value), Exceptions.

## Application Layer
Handlers (one per use case, `__invoke()` entry point), DTOs (Input/ and Output/), Application Services (orchestration, e.g. AppointmentRequestService).

## Infrastructure Layer
Persistence/Doctrine/Type (custom DBAL types for VO↔DB), Persistence/Doctrine/Repository (implementations), Security (password hasher, JWT, Redis blocklist), Email (mailer), Http/Controller (one action per class, delegate to handlers - see `## Controllers`), Http/EventSubscriber (rate limiting, security headers), Console (CLI commands).

## Controllers

One route action per class, one class per file, one test file per class:
`Http/Controller/Api/{Group}/{Resource}/{Action}Controller.php`, a `final` class whose only public
method is `__invoke()`. `AuthController::therapistLogin` became `Api/User/Auth/TherapistLoginController`.

- Full path literal in the `#[Route]`, no class-level prefix, `name:` always explicit. Route names are
  matched by `RateLimitSubscriber` and paths by `security.yaml`, so copy both verbatim when moving an
  action - a rename silently drops rate limiting or an access rule.
- Per-action `#[IsGranted]`, since there is no class left to hang it on. `RouteConventionsTest` is
  the net that catches a forgotten one.
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
6. Migration: review `doctrine:migrations:diff` output before keeping it - entities declare no relations, so Doctrine does not know about the hand-written FK constraints and indexes in `migrations/` and will propose dropping them

### New API Endpoint
1. `{Action}Controller` in `src/Infrastructure/Http/Controller/Api/{Group}/{Resource}/` - never a
   second method on an existing controller
2. Input DTO + Handler if new use case
3. Update `config/packages/security.yaml` if new access rules needed
4. Test file mirroring the controller under `tests/Integration/Infrastructure/Http/Controller/`
