---
paths:
  - API/tests/**/*.php
---
# API Testing Conventions

## Test Infrastructure

All of it lives in `API/tests/Helper/`. Read the list before writing a fixture by hand.

Base classes:
- **DomainTestHelper**: Factory methods for domain objects in controlled states. Use instead of calling constructors directly. Entity factories take the related `User` object, not a `UserId` - see ADR-0007.
- **IntegrationTestCase**: Extends KernelTestCase with automatic transaction wrapping. Use for repository tests.
- **ApiTestCase**: Extends WebTestCase with transaction isolation, `jsonRequest()`, `createTherapistAndGetToken()` / `createPatientAndGetToken()`. Use for controller tests.
- Exception: a test needing neither the database nor auth skips these base classes, since transaction wrapping would buy it nothing. Use `WebTestCase` when it still drives HTTP (`Controller/Health/`, `EventSubscriber/SecurityHeadersSubscriberTest`) and `KernelTestCase` when it works off the container rather than a request (`Application/Appointment/Service/SlotGenerationRulesFactoryTest`, `Http/ProtectedRouteRolesTest`, `Http/RateLimitedRouteSetTest`). Say why in a comment at the top of the class, so the next reader doesn't "fix" it back.

Traits:
- **UsesUtcInstants**: `utc($dateTime)` builds a fixture instant read as UTC, and `assertInstantIs($expectedUtc, $actual)` compares one against a hand-written literal. Never build the expectation by formatting the object under test - it then shifts with the process zone on both sides and agrees with any implementation. See ADR-0003.
- **FreezesClock**: `freezeClock($now)` swaps the container's clock for a frozen one. Call it before the test resolves the clock-using service. `$now` is read as UTC unless it carries an offset. For an entity that takes `now` as an ordinary constructor argument, pass a literal instead - it never reads the container's clock.
- **SeedsAuthFixtures**: seeds a therapist, an activated patient or an invitation. Credentials match the `ApiTestCase` defaults, so a seeded user logs in with them.
- **SeedsTherapistSchedule**: seeds a therapist plus a Schedule Block wide enough that Slot queries return something.
- **SeedsAppointment**: seeds one Appointment. Takes REQUESTED or CONFIRMED only, and throws on a terminal status rather than silently seeding REQUESTED.
- **KeepsBlocklistAcrossRequests**: puts the JWT blocklist on storage that outlives a request. See the `ArrayAdapter` entry in `dev-gotchas.md` for why it is needed.
- **KeepsRateLimitsAcrossRequests**: the same swap for both rate limiters, so a sliding window still holds the earlier requests' hits. Call it before the test's first request - the container refuses to replace a service it has already built.

Data:
- **RateLimitedRoutes**: the rate-limited routes and their ceilings, as `DataProviderExternal` providers plus `urlFor()`, `ceilingFor()`, `names()` and `highestCeiling()`. Not a trait - both rate limit test files read it, and neither should own the list.

Adding or changing a file in `Helper/` updates this list in the same pull request. An undocumented
helper gets reimplemented: `createTherapistWithSchedule` was copy-pasted into two controller tests
before it became a trait.

## Key Rules
- All integration tests run in transactions that rollback in `tearDown()` - no data persists.
- Kernel reboot disabled in API tests for transaction isolation across multiple HTTP requests.
- `reconstitute()` is for test helpers ONLY - never in handlers or controllers.

## Running Tests
```bash
docker-compose exec php vendor/bin/phpunit                    # All tests
docker-compose exec php vendor/bin/phpunit --testsuite=Unit   # Unit only (no DB)
docker-compose exec php vendor/bin/phpunit --testsuite=Integration
```
