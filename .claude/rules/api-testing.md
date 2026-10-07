---
paths:
  - API/tests/**/*.php
---
# API Testing Conventions

## Test Infrastructure

All of it lives in `API/tests/Helper/`. Read the list before writing a fixture by hand.

Base classes:
- **DomainTestHelper**: Factory methods for domain objects in controlled states. Use instead of calling constructors directly. Entity factories take the related `User` object, not a `UserId` - see ADR-0007.
- **IntegrationTestCase**: Extends KernelTestCase with automatic transaction wrapping. Use for repository tests. `consoleApplication()` gives console tests the booted kernel's application.
- **ApiTestCase**: Extends WebTestCase with transaction isolation, `jsonRequest()`, `createTherapistAndGetToken()` / `createPatientAndGetToken()`. Use for controller tests.
- Exception: a test needing neither the database nor auth skips these base classes, since transaction wrapping would buy it nothing. Use `WebTestCase` when it still drives HTTP (`Controller/Health/`, `EventSubscriber/SecurityHeadersSubscriberTest`) and `KernelTestCase` when it works off the container rather than a request (`Application/Appointment/Service/SlotGenerationRulesFactoryTest`, `Http/ProtectedRouteRolesTest`, `Http/RateLimitedRouteSetTest`). Say why in a comment at the top of the class, so the next reader doesn't "fix" it back.

Traits:
- **RollsBackTestTransaction**: the `tearDown()` both base classes share, rolling back the transaction their `setUp()` began. Owns `$entityManager`.
- **UsesUtcInstants**: `utc($dateTime)` builds a fixture instant read as UTC, and `assertInstantIs($expectedUtc, $actual)` compares one against a hand-written literal. Never build the expectation by formatting the object under test - it then shifts with the process zone on both sides and agrees with any implementation. See ADR-0003.
- **FreezesClock**: `freezeClock($now)` swaps the container's clock for a frozen one. Call it before the test resolves the clock-using service. `$now` is read as UTC unless it carries an offset. For an entity that takes `now` as an ordinary constructor argument, pass a literal instead - it never reads the container's clock. A fixture the frozen code compares against needs a literal too, or it drifts with the wall clock: the `DomainTestHelper` token factories and `seedInvitation()` take a `now`.
- **SeedsAuthFixtures**: seeds a therapist, an activated patient or an invitation. Credentials match the `ApiTestCase` defaults, so a seeded user logs in with them.
- **SeedsTherapistSchedule**: seeds a therapist plus a Schedule Block wide enough that Slot queries return something.
- **SeedsAppointment**: seeds one Appointment. Takes REQUESTED or CONFIRMED only, and throws on a terminal status rather than silently seeding REQUESTED.
- **KeepsBlocklistAcrossRequests**: puts the JWT blocklist on storage that outlives a request. See the `ArrayAdapter` entry in `dev-gotchas.md` for why it is needed.
- **KeepsRateLimitsAcrossRequests**: the same swap for both rate limiters, so a sliding window still holds the earlier requests' hits. Call it before the test's first request - the container refuses to replace a service it has already built.

Data:
- **Json**: `at()`, `arrayAt()` and `stringAt()` read a path in decoded JSON and fail the test on a missing key or wrong type. Use them instead of `$data['data']['id']`, which PHPStan rejects as offset access on `mixed` (ADR-0008).
- **RateLimitedRoutes**: the rate-limited routes and their ceilings, as `DataProviderExternal` providers plus `urlFor()`, `ceilingFor()`, `names()` and `highestCeiling()`. Not a trait - both rate limit test files read it, and neither should own the list.

Adding or changing a file in `Helper/` updates this list in the same pull request. An undocumented
helper gets reimplemented: `createTherapistWithSchedule` was copy-pasted into two controller tests
before it became a trait.

## Key Rules
- All integration tests run in transactions that rollback in `tearDown()` - no data persists.
- Kernel reboot disabled in API tests for transaction isolation across multiple HTTP requests.
- `reconstitute()` is for test helpers ONLY - never in handlers or controllers.

## Enforced by PHPStan

Rules in `API/tests/PHPStan/Rule/`, registered in `phpstan.dist.neon`. They only look at code under
`App\Tests`, and they read shapes, not meaning.

- No `assertEquals` or `assertNotEquals`. The date comparator sees the instant, not the offset, so a wrong zone passes. Use `assertSame`, or `assertInstantIs` for an instant.
- No `markTestSkipped` or `markTestIncomplete`. A skipped test lets the suite shrink without failing. Fix it or delete it.
- No `sleep` or `usleep`. Waiting on the wall clock is slow and flaky. Pin time instead.
- A `ClockInterface` double returns a pinned instant, never `new DateTimeImmutable()`. Stub it with `willReturn()` on `createMock()` or `createStub()`, or use `MockClock`. `willReturnCallback`, `createConfiguredMock` and a hand-written clock class are rejected because the rule cannot read the instant through them.
- A one-argument `new DateTimeImmutable('...')` literal names its zone, or goes through `utc()`. Without one it is read at the suite's +14:00. A pure relative duration (`'-1 hour'`) is exempt. See ADR-0003.

Not caught: a stub fed through a variable, zero-argument `new DateTimeImmutable()` outside a clock
stub, and mutable `DateTime`. An exemption is an inline `@phpstan-ignore` with its reason (ADR-0008).
The fixtures in `tests/Unit/PHPStan/Rule/Fixture/` break the rules on purpose and are excluded from
analysis.

## Running Tests
```bash
docker-compose exec php vendor/bin/phpunit                    # All tests
docker-compose exec php vendor/bin/phpunit --testsuite=Unit   # Unit only (no DB)
docker-compose exec php vendor/bin/phpunit --testsuite=Integration
```
