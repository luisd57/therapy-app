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

## Environment Variables

Key environment variables in `.env`:

```env
DATABASE_URL=postgresql://therapy_user:therapy_pass@postgres:5432/therapy_db
JWT_PASSPHRASE=therapy_jwt_passphrase
JWT_TOKEN_TTL=3600
MAILER_DSN=smtp://mailhog:1025
APP_FRONTEND_URL=http://localhost:3000
INVITATION_TOKEN_TTL=86400
PASSWORD_RESET_TOKEN_TTL=3600
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
├── .env                       # Environment variables
├── docker-compose.yml         # Docker Compose config
├── Makefile                   # Make commands
└── run.bat                    # Windows batch commands
```

## License

MIT
