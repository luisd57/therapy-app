---
paths:
  - API/tests/**/*.php
---
# API Testing Conventions

## Test Infrastructure

Everything below lives in `API/tests/Helper/`. Check it before writing a fixture by hand.

Base classes:
- **DomainTestHelper**: Factory methods for domain objects in controlled states. Use instead of calling constructors directly.
- **IntegrationTestCase**: Extends KernelTestCase with automatic transaction wrapping. Use for repository tests.
- **ApiTestCase**: Extends WebTestCase with transaction isolation, `jsonRequest()`, `createTherapistAndGetToken()` / `createPatientAndGetToken()`. Use for controller tests.
- Exception: a controller test that touches neither the database nor auth extends `WebTestCase` directly, since transaction wrapping would buy it nothing. `Controller/Health/` and `ProtectedRouteRolesTest` are the cases. Say why in a comment at the top of the class, so the next reader doesn't "fix" it back.

Traits:
- **FreezesClock**: `freezeClock($now)` swaps the container's clock for a frozen one. Call it before the test resolves the clock-using service. `$now` is read as UTC unless it carries an offset.
- **SeedsAuthFixtures**: persists a therapist, an activated patient or an invitation, with credentials matching the `ApiTestCase` defaults.
- **SeedsTherapistSchedule**: `createTherapistWithSchedule()` persists a therapist plus a Monday 08:00-18:00 Schedule Block supporting both Modalities, so Slot queries return something.
- **SeedsAppointment**: `createTestAppointment()` persists one Appointment in a given status.
- **KeepsBlocklistAcrossRequests**: puts the JWT blocklist on storage that outlives a request. Needed because the test container's `cache.app` is an `ArrayAdapter` that resets between requests.

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
