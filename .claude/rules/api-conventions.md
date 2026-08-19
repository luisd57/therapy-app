---
paths:
  - API/src/**/*.php
---
# API Coding Conventions

## PHP Style
- `declare(strict_types=1);` in every file
- Constructor Property Promotion: always
- Readonly properties for immutable data
- `final` for classes not intended for inheritance (Doctrine entities are NEVER `final` - proxying)
- Always declare return types

## Method Parameters
- Non-primitive parameter names must match their type name (camelCase):
  - ✅ `function login(string $email, UserRole $userRole)`
  - ❌ `function login(string $email, UserRole $expectedRole)`
- Common types use descriptive names: `$from`, `$to`, `$ttlSeconds`
- No single-letter variable names, including in closures

## Collections
- Use `Doctrine\Common\Collections\ArrayCollection` instead of arrays
- Convert to array only at boundaries (API responses)

## Naming Conventions

| Type           | Pattern                    | Example                    |
|----------------|----------------------------|----------------------------|
| Controller     | `{Action}Controller` *     | `TherapistLoginController` |
| Handler        | `{Action}{Entity}Handler`  | `InvitePatientHandler`     |
| Input DTO      | `{Action}{Entity}InputDTO` | `InvitePatientInputDTO`    |
| Output DTO     | `{Entity}OutputDTO`        | `UserOutputDTO`            |
| Interface      | `{Name}Interface`          | `UserRepositoryInterface`  |
| Custom DBAL    | `{VO}Type`                 | `EmailType`, `UserIdType`  |

\* Applies to controllers this convention splits. One that already held a single action keeps its
existing name, so `PatientAppointmentController` is not a breach.

## Handlers
- One handler = one file = one public action via `__invoke()`. Controllers follow the same shape, so
  both layers read alike (see `## Controllers` in api-architecture.md)
- `__invoke()` receives a single InputDTO parameter named `$dto`
- Call explicitly: `$this->handler->__invoke(new FooInputDTO(...))`
  - ❌ `($this->handler)(new FooInputDTO(...))`

## DTOs
- Input DTOs: `DTO/Input/`, suffixed `InputDTO`
- Output DTOs: `DTO/Output/`, suffixed `OutputDTO`
- All DTOs are `final readonly class`
- Name the static factory for what it maps: `fromEntity()` from a single entity, `fromValueObject()` from a VO. A DTO built from computed results, or needing repositories to compose, is assembled by its handler or an Application Service - don't add a factory that only aliases the constructor
- `toArray()` on every Output DTO that goes into a response. Omit it on internal carriers the controller destructures - e.g. an auth result whose token goes to an httpOnly cookie, not the body

## Value Objects
- Static factories: `fromString()`, `create()`, `generate()`
- Private constructors, immutable (readonly), self-validating

## Validation (deliberate, do not "fix")
- NO `#[Assert]` attributes on DTOs, NO `#[MapRequestPayload]`. Controllers validate the decoded array via `ValidatesRequestTrait` (422, `details` = field → first message). Value Objects are the real guard - attribute validation duplicates their rules and drifts out of sync with them.

## Errors (deliberate, do not "fix")
- NO kernel exception listener. Each action catches the specific domain exceptions it can produce - a central listener has to guess, and turns every unmapped exception into a 500 nobody notices.

## API Responses
- Use `ApiResponseTrait` for consistent envelope format
- Pagination: `?page=1&limit=20` (defaults: page=1, limit=20, max 100)
