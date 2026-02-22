# Therapy App API

A Symfony 8.0 application implementing Pure Hexagonal Architecture with PostgreSQL for a therapy practice management system.

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

### 2. Public Appointment Request System (Scheduling Domain)

- **Availability Interface**
  - Real-time available slots computed from therapist schedule
  - Modality filtering (Online / In-Person)
  - Slot locking with TTL to prevent double-requests

- **Appointment Requests**
  - Intake form submission (name, ID, phone, email, location)
  - Automatic acknowledgment email to requester
  - Instant alert email to therapist

- **Therapist Schedule Management**
  - Recurring weekly availability blocks (configurable per day)
  - Modality support per block (online, in-person, or both)
  - Schedule exceptions / blockers (holidays, personal time)
  - Overlap validation (time-based, regardless of modality)

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
│   ├── Appointment/
│   │   ├── Entity/           # Appointment, TherapistSchedule, ScheduleException, SlotLock
│   │   ├── ValueObject/      # AppointmentId, AppointmentStatus, AppointmentModality, TimeSlot, WeekDay
│   │   ├── Repository/       # Repository interfaces (ports)
│   │   ├── Service/          # AvailabilityComputer, service interfaces
│   │   └── Exception/        # Domain exceptions
│   └── Exception/
│
├── Application/               # Use cases / Application services
│   ├── User/
│   │   ├── Handler/          # Use case handlers
│   │   └── DTO/
│   │       ├── Input/        # Input DTOs received by handlers
│   │       └── Output/       # Output DTOs returned by handlers
│   └── Appointment/
│       ├── Handler/          # Schedule, availability, appointment handlers
│       └── DTO/
│           ├── Input/        # Input DTOs received by handlers
│           └── Output/       # Output DTOs returned by handlers
│
└── Infrastructure/            # External concerns (adapters)
    ├── Persistence/
    │   └── Doctrine/
    │       ├── User/
    │       │   ├── Entity/       # Doctrine entity mappings
    │       │   ├── Mapper/       # Domain ↔ Doctrine entity mapping
    │       │   └── Repository/   # Repository implementations
    │       └── Appointment/
    │           ├── Entity/
    │           ├── Mapper/
    │           └── Repository/
    ├── Security/             # Password hasher, Token generator, JWT
    ├── Email/
    │   ├── User/             # User email sender (invitations, password reset, welcome)
    │   └── Appointment/      # Appointment email sender (acknowledgment, alert)
    ├── Http/
    │   └── Controller/Api/
    │       ├── User/         # Auth, Patient, Therapist controllers
    │       ├── Appointment/  # Public appointments, Schedule management
    │       └── HealthController.php
    └── Console/
        ├── User/             # create-therapist, cleanup-tokens
        └── Appointment/      # cleanup-slot-locks
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

- **API Health Check**: <http://localhost:8080/api/health>
- **MailHog** (email testing): <http://localhost:8025>

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
| POST | `/api/auth/logout` | Revoke JWT token (requires auth) |
| GET | `/api/appointments/available-slots` | Browse available time slots |
| POST | `/api/appointments/lock-slot` | Temporarily hold a slot |
| POST | `/api/appointments/request` | Submit appointment request |

### Protected Endpoints (Therapist)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/therapist/me` | Get therapist profile |
| GET | `/api/therapist/patients` | List all patients |
| POST | `/api/therapist/patients/invite` | Invite a patient |
| GET | `/api/therapist/invitations` | List pending invitations |
| GET | `/api/therapist/schedule` | List schedule blocks |
| POST | `/api/therapist/schedule` | Create schedule block |
| PUT | `/api/therapist/schedule/{id}` | Update schedule block |
| DELETE | `/api/therapist/schedule/{id}` | Delete schedule block |
| GET | `/api/therapist/schedule/exceptions` | List schedule exceptions |
| POST | `/api/therapist/schedule/exceptions` | Add schedule exception |
| DELETE | `/api/therapist/schedule/exceptions/{id}` | Remove exception |
| GET | `/api/therapist/appointments` | List appointments (optional `?status=` filter) |
| GET | `/api/therapist/appointments/{id}` | Get appointment details |
| POST | `/api/therapist/appointments` | Book appointment (manual creation) |
| POST | `/api/therapist/appointments/{id}/confirm` | Confirm appointment |
| POST | `/api/therapist/appointments/{id}/complete` | Complete appointment |
| POST | `/api/therapist/appointments/{id}/cancel` | Cancel appointment |
| PATCH | `/api/therapist/appointments/{id}/payment` | Update payment verification |

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
5. **Create Schedule** - Set up weekly availability blocks
6. **Invite Patient** - Send invitation email
7. **Check MailHog** - Get invitation token from email (<http://localhost:8025>)
8. **Set invitation_token variable** - Copy token from email link
9. **Validate Invitation** - Verify token is valid
10. **Register Patient** - Activate patient account
11. **Patient Login** - Get patient JWT token
12. **Get Patient Profile** - Verify patient auth works
13. **Update Patient Profile** - Test profile updates
14. **List Patients** - Verify therapist can see patients
15. **Browse Available Slots** - Query public availability
16. **Lock Slot** - Temporarily hold a slot
17. **Submit Appointment Request** - Complete the intake form
18. **Check MailHog** - Verify acknowledgment + therapist alert emails
19. **Logout** - Revoke the JWT token

### Getting the Invitation Token

After inviting a patient:

1. Go to <http://localhost:8025> (MailHog)
2. Find the invitation email
3. The registration URL contains the token: `http://localhost:3000/register?token=YOUR_TOKEN_HERE`
4. Copy the token value and set it in Postman's `invitation_token` variable

## Testing

The project has a comprehensive test suite covering unit and integration tests:

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
docker-compose exec php vendor/bin/phpunit tests/Unit/Domain/User/Entity/UserTest.php
docker-compose exec php vendor/bin/phpunit tests/Integration/Infrastructure/Http/Controller/Api/User/AuthControllerTest.php
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
│   │   ├── User/
│   │   │   ├── Entity/           # User, InvitationToken, PasswordResetToken tests
│   │   │   └── ValueObject/      # UserId, Email, Phone, Address, UserRole tests
│   │   ├── Appointment/
│   │   │   ├── Entity/           # Appointment, Schedule, Exception, SlotLock tests
│   │   │   └── ValueObject/      # AppointmentId, Status, Modality, TimeSlot, WeekDay tests
│   │   ├── Service/              # Domain service tests (AvailabilityComputer)
│   │   └── Exception/            # Domain exception tests
│   └── Application/
│       ├── User/Handler/         # User use case handler tests (with mocks)
│       └── Appointment/Handler/  # Appointment handler tests (with mocks)
└── Integration/                  # requires test database
    └── Infrastructure/
        ├── Persistence/Doctrine/
        │   ├── User/Repository/          # User repository integration tests
        │   └── Appointment/Repository/   # Appointment repository integration tests
        └── Http/Controller/Api/
            ├── User/                     # Auth, Patient, Therapist controller tests
            └── Appointment/              # Public appointment, Schedule controller tests
```

### Writing Unit Tests

#### Domain Entity Tests

Use `DomainTestHelper` factory methods for creating test fixtures:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\Entity;

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

Use PHPUnit's `createMock()` with intersection types for dependencies, wired in `setUp()`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\Handler\JwtTokenGeneratorInterface;
use App\Application\User\Handler\LoginHandler;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private JwtTokenGeneratorInterface&MockObject $jwtTokenGenerator;
    private LoginHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->jwtTokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);
        $this->handler = new LoginHandler(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtTokenGenerator,
        );
    }

    public function testHandleTherapistLoginSuccess(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();

        $this->userRepository->method('findByEmail')->willReturn($therapist);
        $this->passwordHasher->method('verify')->willReturn(true);
        $this->jwtTokenGenerator->method('generate')->willReturn('jwt-token-123');

        $result = $this->handler->handleTherapistLogin('therapist@example.com', 'password');

        $this->assertSame('jwt-token-123', $result->token);
        $this->assertSame('therapist@example.com', $result->user->email);
        $this->assertSame('ROLE_THERAPIST', $result->user->role);
    }
}
```

### Writing Integration Tests

#### Repository Tests

Extend `IntegrationTestCase` for automatic transaction wrapping:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserRole;
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
        $this->assertTrue($user->getId()->equals($found->getId()));
        $this->assertSame('therapist@example.com', $found->getEmail()->getValue());
    }

    public function testFindActivePatientsExcludesInactivePatientsAndTherapists(): void
    {
        $therapist = DomainTestHelper::createTherapist(email: 'act-t@example.com');
        $activePatient = DomainTestHelper::createActivePatient(email: 'act-ap@example.com');
        $inactivePatient = DomainTestHelper::createPatient(email: 'act-ip@example.com');
        $this->repository->save($therapist);
        $this->repository->save($activePatient);
        $this->repository->save($inactivePatient);

        $result = $this->repository->findActivePatients();

        $emails = $result->map(fn($u) => $u->getEmail()->getValue())->toArray();
        $this->assertContains('act-ap@example.com', $emails);
        $this->assertNotContains('act-t@example.com', $emails);
        $this->assertNotContains('act-ip@example.com', $emails);
    }
}
```

> **Transaction isolation**: Each test runs in a database transaction that rolls back in `tearDown()`. No test data persists between tests.

#### API/Controller Tests

Extend `ApiTestCase` for HTTP client + authentication helpers:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Tests\Helper\ApiTestCase;

final class AuthControllerTest extends ApiTestCase
{
    public function testTherapistLoginSuccess(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->assertNotEmpty($token);
        $this->assertResponseIsSuccessful();
    }

    public function testTherapistLoginWrongPasswordReturns401(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'email' => 'therapist@test.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testFullLoginThenAccessProtectedResourceFlow(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('therapist@test.com', $data['data']['email']);
    }

    private function seedTherapist(): void
    {
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);
        $repo = self::getContainer()->get(UserRepositoryInterface::class);

        $therapist = User::createTherapist(
            id: UserId::generate(),
            email: Email::fromString('therapist@test.com'),
            fullName: 'Test Therapist',
            hashedPassword: $hasher->hash('password123'),
        );
        $repo->save($therapist);
    }
}
```

> **Auth helpers**: `createTherapistAndGetToken()` and `createPatientAndGetToken()` seed data directly via repositories (not API endpoints) for isolation, then call the login endpoint to get a real JWT token. For tests that need a user without a token, use private `seedTherapist()`/`seedInvitation()` methods that persist directly via the container's repositories.

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

# Clean up expired slot locks
docker-compose exec php php bin/console app:cleanup-slot-locks
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
| API | <http://localhost:8080> | Main API |
| PostgreSQL | localhost:5432 | Database |
| MailHog UI | <http://localhost:8025> | Email testing interface |
| MailHog SMTP | localhost:1025 | SMTP server |
| Redis | localhost:6379 | JWT blocklist & cache |

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
