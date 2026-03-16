---
paths:
  - API/src/**/*.php
---
# API Architecture: Hexagonal (Ports & Adapters)

## Layer Structure & Dependency Rule

Infrastructure → Application → Domain (never the reverse)

src/Domain/ (core business logic, no framework deps), src/Application/ (use cases, orchestration), src/Infrastructure/ (external concerns, adapters).

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
6. Migration via `php bin/console doctrine:migrations:diff`

### New API Endpoint
1. Route method in controller in `src/Infrastructure/Http/Controller/Api/`
2. Input DTO + Handler if new use case
3. Update `config/packages/security.yaml` if new access rules needed
