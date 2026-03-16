---
paths:
  - API/src/**/*.php
---
# API Coding Conventions

## PHP Style
- `declare(strict_types=1);` in every file
- Constructor Property Promotion: always
- Readonly properties for immutable data
- `final` for classes not intended for inheritance
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

## API Responses
- Use `ApiResponseTrait` for consistent envelope format
- Pagination: `?page=1&limit=20` (defaults: page=1, limit=20, max 100)
