---
paths:
  - API/src/**/*.php
---
# API Coding Conventions

## PHP Style
- `declare(strict_types=1);` in every file
- Constructor Property Promotion: always
- Readonly properties for immutable data
- `final` for classes not intended for inheritance (Doctrine entities are NEVER `final` — proxying)
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
| Handler        | `{Action}{Entity}Handler`  | `InvitePatientHandler`     |
| Input DTO      | `{Action}{Entity}InputDTO` | `InvitePatientInputDTO`    |
| Output DTO     | `{Entity}OutputDTO`        | `UserOutputDTO`            |
| Interface      | `{Name}Interface`          | `UserRepositoryInterface`  |
| Custom DBAL    | `{VO}Type`                 | `EmailType`, `UserIdType`  |

## Handlers
- One handler = one file = one public action via `__invoke()`
- `__invoke()` receives a single InputDTO parameter named `$dto`
- Call explicitly: `$this->handler->__invoke(new FooInputDTO(...))`
  - ❌ `($this->handler)(new FooInputDTO(...))`

## DTOs
- Input DTOs: `DTO/Input/`, suffixed `InputDTO`
- Output DTOs: `DTO/Output/`, suffixed `OutputDTO`
- All DTOs are `final readonly class`
- Output DTOs include static `fromEntity()` factory and `toArray()` method

## Value Objects
- Static factories: `fromString()`, `create()`, `generate()`
- Private constructors, immutable (readonly), self-validating

## Validation (deliberate, do not "fix")
- NO `#[Assert]` attributes on DTOs, NO `#[MapRequestPayload]`. Controllers validate the decoded array via `ValidatesRequestTrait` (422, `details` = field → first message). Value Objects are the real guard — attribute validation duplicates their rules and drifts out of sync with them.

## Errors (deliberate, do not "fix")
- NO kernel exception listener. Each action catches the specific domain exceptions it can produce — a central listener has to guess, and turns every unmapped exception into a 500 nobody notices.

## API Responses
- Use `ApiResponseTrait` for consistent envelope format
- Pagination: `?page=1&limit=20` (defaults: page=1, limit=20, max 100)
