---
paths:
  - API/tests/**/*.php
---
# API Testing Conventions

## Test Infrastructure
- **DomainTestHelper**: Factory methods for domain objects in controlled states. Use instead of calling constructors directly.
- **IntegrationTestCase**: Extends KernelTestCase with automatic transaction wrapping. Use for repository tests.
- **ApiTestCase**: Extends WebTestCase with transaction isolation, `jsonRequest()`, `createTherapistAndGetToken()` / `createPatientAndGetToken()`. Use for controller tests.

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
