# Therapy App - Authentication & User Management

A Symfony 7.1 application implementing Pure Hexagonal Architecture with PostgreSQL for a therapy practice management system.

## Features Implemented

### 1. Authentication & User Management (Security Domain)

- **Therapist Authentication (Admin)**
  - Secure JWT-based login
  - Session management via stateless JWT tokens

- **Patient Onboarding (Invitation Flow)**
  - Invitation generator in Admin panel
  - Signed, time-limited registration links
  - Account activation/registration page

- **Account Maintenance**
  - Patient login interface
  - Password reset flow via email
  - Profile management (phone, address)

## Architecture

```
src/
├── Domain/                    # Core business logic (no dependencies)
│   ├── User/
│   │   ├── Entity/           # User, InvitationToken, PasswordResetToken
│   │   ├── ValueObject/      # UserId, Email, Phone, Address, UserRole
│   │   ├── Repository/       # Repository interfaces (ports)
│   │   ├── Service/          # Domain service interfaces
│   │   └── Exception/        # Domain exceptions
│   └── Exception/
│
├── Application/               # Use cases / Application services
│   └── User/
│       ├── Command/          # Command objects
│       ├── Handler/          # Use case handlers
│       └── DTO/              # Data transfer objects
│
└── Infrastructure/            # External concerns (adapters)
    ├── Persistence/
    │   └── Doctrine/
    │       ├── Entity/       # Doctrine entity mappings
    │       └── Repository/   # Repository implementations
    ├── Security/             # Password hasher, Token generator, JWT
    ├── Email/                # Email sender implementation
    ├── Http/
    │   └── Controller/       # API controllers
    └── Console/              # CLI commands
```

### Reconstitution Pattern

Domain entities use `reconstitute()` static factory methods to create objects in a specific state without going through business logic constructors. This serves two purposes:

- **Doctrine hydration**: Rebuilding entities from database rows
- **Testing**: Creating entities in controlled states (expired tokens, inactive users, etc.)

`reconstitute()` must **never** be called in handlers or controllers. If you see it outside of repository implementations or test helpers, it's a code smell.

## Prerequisites

- Docker Desktop for Windows
- Git (optional, for version control)

## Quick Start

### Step 1: Clone/Create Project Directory

```powershell
# Create project directory
mkdir therapy-app
cd therapy-app
```

### Step 2: Copy All Files

Copy all the files from this implementation into your `therapy-app` directory.

### Step 3: Build and Start Docker Containers

```powershell
# Build containers (first time only)
docker-compose build

# Start containers
docker-compose up -d
```

### Step 4: Initialize Symfony Project

```powershell
# Enter the PHP container
docker-compose exec php bash

# Inside the container, run the initialization script
bash /var/www/html/docker/scripts/init-project.sh

# Exit the container
exit
```

### Step 5: Create Database and Run Migrations

```powershell
# Create the database
docker-compose exec php php bin/console doctrine:database:create --if-not-exists

# Run migrations
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Step 6: Clear Cache

```powershell
docker-compose exec php php bin/console cache:clear
```

### Step 7: Verify Installation

Open your browser and navigate to:
- **API Health Check**: http://localhost:8080/api/health
- **MailHog** (email testing): http://localhost:8025

## API Endpoints

### Public Endpoints (No Authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/` | API info |
| POST | `/api/therapist/setup` | Create initial therapist |
| POST | `/api/auth/therapist/login` | Therapist login |
| POST | `/api/auth/patient/login` | Patient login |
| GET | `/api/auth/invitation/validate/{token}` | Validate invitation |
| POST | `/api/auth/register` | Register patient (activate) |
| POST | `/api/auth/password/forgot` | Request password reset |
| POST | `/api/auth/password/reset` | Reset password |

### Protected Endpoints (Therapist)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/therapist/me` | Get therapist profile |
| GET | `/api/therapist/patients` | List all patients |
| POST | `/api/therapist/patients/invite` | Invite a patient |
| GET | `/api/therapist/invitations` | List pending invitations |

### Protected Endpoints (Patient)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/patient/me` | Get patient profile |
| PUT | `/api/patient/profile` | Update profile |

## Testing with Postman

### Import Collection

1. Open Postman
2. Click **Import**
3. Select the file: `postman/Therapy_App_API.postman_collection.json`

### Testing Flow

1. **Health Check** - Verify API is running
2. **Create Therapist** - Set up initial admin account
3. **Therapist Login** - Get JWT token (auto-saved to variable)
4. **Get Therapist Profile** - Verify authentication works
5. **Invite Patient** - Send invitation email
6. **Check MailHog** - Get invitation token from email (http://localhost:8025)
7. **Set invitation_token variable** - Copy token from email link
8. **Validate Invitation** - Verify token is valid
9. **Register Patient** - Activate patient account
10. **Patient Login** - Get patient JWT token
11. **Get Patient Profile** - Verify patient auth works
12. **Update Patient Profile** - Test profile updates
13. **List Patients** - Verify therapist can see patients

### Getting the Invitation Token

After inviting a patient:
1. Go to http://localhost:8025 (MailHog)
2. Find the invitation email
3. The registration URL contains the token: `http://localhost:3000/register?token=YOUR_TOKEN_HERE`
4. Copy the token value and set it in Postman's `invitation_token` variable

## Testing

The project has a comprehensive test suite with **203 tests (373 assertions)** covering:

- **Unit Tests**: Pure logic tests for domain entities, value objects, and handlers. No database required.
- **Integration Tests**: Repository and API endpoint tests with real database interactions.
- **Transaction Isolation**: All integration tests run in transactions that rollback automatically, keeping the test database clean.

### First Time Setup (Test Database)

Create and migrate the test database (only needed once, or after `docker-compose down -v`):

**Windows** (direct docker-compose commands):

```powershell
docker-compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker-compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

**Mac/Linux** (using Makefile):

```bash
make test-db-setup
```

> The test database persists between container restarts. Only re-run if you delete Docker volumes or add new migrations.

### Running Tests

#### Run All Tests

**Windows**:

```powershell
docker-compose exec php vendor/bin/phpunit
```

**Mac/Linux**:

```bash
make test
```

#### Run by Suite

**Unit tests only (fast, no DB needed)**:

```powershell
# Windows
docker-compose exec php vendor/bin/phpunit --testsuite=Unit

# Mac/Linux
make test-unit
```

**Integration tests only (requires test DB)**:

```powershell
# Windows
docker-compose exec php vendor/bin/phpunit --testsuite=Integration

# Mac/Linux
make test-integration
```

#### Run Specific Test File

```powershell
docker-compose exec php vendor/bin/phpunit tests/Unit/Domain/Entity/UserTest.php
docker-compose exec php vendor/bin/phpunit tests/Integration/Infrastructure/Http/Controller/Api/AuthControllerTest.php
```

#### Run Specific Test Method

Using `--filter` (accepts regex, so partial names work):

```powershell
docker-compose exec php vendor/bin/phpunit --filter=testTherapistLoginSuccess
docker-compose exec php vendor/bin/phpunit --filter=testFullLogin  # matches testFullLoginThenAccessProtectedResourceFlow
```

### Test Structure

```
tests/
├── Helper/
│   ├── DomainTestHelper.php      # Factory methods for test fixtures
│   ├── IntegrationTestCase.php   # Base class for repository tests
│   └── ApiTestCase.php           # Base class for API/controller tests
├── Unit/                         # no database needed
│   ├── Domain/
│   │   ├── Entity/               # Entity behavior tests
│   │   ├── ValueObject/          # Value object validation tests
│   │   └── Exception/            # Domain exception tests
│   └── Application/
│       └── Handler/              # Use case handler tests (with mocks)
└── Integration/                  # requires test database
    └── Infrastructure/
        ├── Persistence/Doctrine/Repository/  # Repository integration tests
        └── Http/Controller/Api/              # API endpoint tests
```

### Writing Unit Tests

#### Domain Entity Tests

Use `DomainTestHelper` factory methods for creating test fixtures:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testTherapistCreatedAsActive(): void
    {
        $therapist = DomainTestHelper::createTherapist();

        $this->assertTrue($therapist->isActive());
        $this->assertTrue($therapist->getRole()->isTherapist());
    }

    public function testPatientCreatedAsInactive(): void
    {
        $patient = DomainTestHelper::createPatient();

        $this->assertFalse($patient->isActive());
        $this->assertTrue($patient->getRole()->isPatient());
    }
}
```

#### Handler Unit Tests

Use PHPUnit's `createMock()` for dependencies:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Handler;

use App\Application\User\Handler\LoginHandler;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Infrastructure\Security\JwtTokenGenerator;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    public function testTherapistLoginSuccess(): void
    {
        $therapist = DomainTestHelper::createTherapist();

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn($therapist);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('verify')->willReturn(true);

        $tokenGen = $this->createMock(JwtTokenGenerator::class);
        $tokenGen->method('generate')->willReturn('mock.jwt.token');

        $handler = new LoginHandler($userRepo, $hasher, $tokenGen);
        $result = $handler->handle($input);

        $this->assertNotEmpty($result->token);
        $this->assertEquals('mock.jwt.token', $result->token);
    }
}
```

### Writing Integration Tests

#### Repository Tests

Extend `IntegrationTestCase` for automatic transaction wrapping:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrineUserRepositoryTest extends IntegrationTestCase
{
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(UserRepositoryInterface::class);
    }

    public function testSaveAndFindById(): void
    {
        $user = DomainTestHelper::createTherapist();

        $this->repository->save($user);
        $found = $this->repository->findById($user->getId());

        $this->assertNotNull($found);
        $this->assertEquals($user->getEmail()->getValue(), $found->getEmail()->getValue());
    }
}
```

> **Transaction isolation**: Each test runs in a database transaction that rolls back in `tearDown()`. No test data persists between tests.

#### API/Controller Tests

Extend `ApiTestCase` for HTTP client + authentication helpers:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api;

use App\Tests\Helper\ApiTestCase;

final class TherapistControllerTest extends ApiTestCase
{
    public function testMeAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEquals('therapist@test.com', $data['data']['email']);
    }

    public function testMeUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/me');

        $this->assertResponseStatusCodeSame(401);
    }
}
```

> **Auth helpers**: `createTherapistAndGetToken()` and `createPatientAndGetToken()` seed data directly via repositories (not API endpoints) for isolation, then call the login endpoint to get a real JWT token.

### Key Testing Patterns

**Transaction Isolation**
- All integration tests run in database transactions
- Transactions are automatically rolled back in `tearDown()`
- No test data persists between tests
- Test database remains clean

**Test Helpers**
- `DomainTestHelper`: Factory methods for creating domain objects in any state (active/inactive users, valid/expired/used tokens, boundary conditions)
- `IntegrationTestCase`: Automatic transaction wrapping for repository tests
- `ApiTestCase`: Transaction wrapping + HTTP client helpers + JWT auth token generation

**Kernel Reboot Disabled**
- `ApiTestCase` calls `$this->client->disableReboot()` to keep the same Symfony kernel across multiple HTTP requests
- This ensures transaction isolation works correctly with JWT authentication
- Without this, each request would get a new EntityManager that can't see uncommitted transaction data

## Console Commands

```powershell
# Create a therapist via CLI
docker-compose exec php php bin/console app:create-therapist "email@example.com" "Dr. Name" "password"

# Clean up expired tokens
docker-compose exec php php bin/console app:cleanup-tokens
```

## Development Commands

```powershell
# Using run.bat (Windows)
run.bat up              # Start containers
run.bat down            # Stop containers
run.bat shell           # Enter PHP container
run.bat db-migrate      # Run migrations
run.bat cache-clear     # Clear Symfony cache
run.bat sf cache:clear  # Run any Symfony command

# Using docker-compose directly
docker-compose logs -f php    # View PHP container logs
docker-compose restart        # Restart all containers
```

## Troubleshooting

### "Connection refused" errors

```powershell
# Ensure containers are running
docker-compose ps

# Restart containers
docker-compose restart
```

### JWT Token errors

```powershell
# Regenerate JWT keys
docker-compose exec php bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:therapy_jwt_passphrase
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:therapy_jwt_passphrase
exit
```

### Database errors

```powershell
# Reset database
docker-compose exec php php bin/console doctrine:database:drop --force
docker-compose exec php php bin/console doctrine:database:create
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Cache issues

```powershell
docker-compose exec php php bin/console cache:clear
docker-compose exec php php bin/console cache:warmup
```

## Services

| Service | URL | Description |
|---------|-----|-------------|
| API | http://localhost:8080 | Main API |
| PostgreSQL | localhost:5432 | Database |
| MailHog UI | http://localhost:8025 | Email testing interface |
| MailHog SMTP | localhost:1025 | SMTP server |

## Project Structure

```
therapy-app/
├── config/                    # Symfony configuration
│   ├── packages/             # Bundle configurations
│   ├── routes/               # Route configurations
│   └── services.yaml         # Service definitions
├── docker/                    # Docker configuration
│   ├── nginx/                # Nginx config
│   ├── php/                  # PHP Dockerfile & config
│   └── scripts/              # Setup scripts
├── migrations/                # Database migrations
├── postman/                   # Postman collection
├── public/                    # Web root
├── src/                       # Application source
│   ├── Application/          # Use cases
│   ├── Domain/               # Business logic
│   └── Infrastructure/       # External concerns
├── docker-compose.yml         # Docker Compose config
├── Makefile                   # Make commands
└── run.bat                    # Windows batch commands
```

## License

MIT
