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
Entities (User, Appointment, TherapistSchedule, ScheduleException, SlotLock, InvitationToken, PasswordResetToken), Value Objects (immutable, self-validating: UserId, Email, Phone, Address, UserRole, AppointmentId, AppointmentStatus, AppointmentModality, TimeSlot, WeekDay), Repository Interfaces (driven ports), Service Interfaces (driven ports: EmailSenderInterface, JwtTokenGeneratorInterface, PasswordHasherInterface), Domain Services (AvailabilityComputer), Parameter Objects (AvailabilityContext), Exceptions.

## Application Layer
Handlers (one per use case, `__invoke()` entry point), DTOs (Input/ and Output/), Application Services (orchestration, e.g. AppointmentRequestService).

## Infrastructure Layer
Persistence/Doctrine/Type (custom DBAL types for VO↔DB), Persistence/Doctrine/Repository (implementations), Security (password hasher, JWT, Redis blocklist), Email (mailer), Http/Controller (thin, delegate to handlers), Http/EventSubscriber (rate limiting, security headers), Console (CLI commands).

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
1. Route method in controller in `src/Infrastructure/Http/Controller/Api/`
2. Input DTO + Handler if new use case
3. Update `config/packages/security.yaml` if new access rules needed
