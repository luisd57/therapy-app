---
paths:
  - API/src/Infrastructure/**/*.php
  - API/config/**/*.yaml
---
# API Security & Infrastructure

## Authentication
- JWT via `lexik/jwt-authentication-bundle` with `jti` claim for Redis-backed revocation
- Transport: single httpOnly cookie `THERAPY_JWT` (`Path=/api`, `SameSite=Lax`) for browsers; Bearer token for API clients. One session per browser — login (either role) replaces the cookie. Lexik's built-in cookie extractor reads it; Authorization header is the fallback.
- Cookie managed by `JwtCookieManager` (name in `JwtCookieManager::COOKIE_NAME`). `JWT_COOKIE_SECURE` controls Secure flag. Logout clears the cookie + revokes the token's jti.
- CORS with `allow_credentials: true`, scoped to `^/api/`, origin from `APP_FRONTEND_URL`

## Access Control
- Therapist setup: CLI only via `app:create-therapist` (no HTTP endpoint)
- All therapist endpoints: class-level `#[IsGranted('ROLE_THERAPIST')]` on `TherapistController`
- Public endpoints: `/api/appointments/` (unauthenticated)

## Rate Limiting
- Login: 5/min, public: 10/min via `RateLimitSubscriber`
- Covers: login, forgot-password, lock-slot, request-appointment, validate-invitation, register, reset-password

## Token Security
- Invitation, password reset, and slot lock tokens stored hashed (SHA-256); raw only at creation time

## Environment Variables
- `DATABASE_URL`, `JWT_PASSPHRASE`, `APP_FRONTEND_URL`
- `INVITATION_TOKEN_TTL` (default: 86400), `PASSWORD_RESET_TOKEN_TTL` (default: 3600)
- `APPOINTMENT_DURATION_MINUTES` (default: 50), `SLOT_LOCK_TTL` (default: 600)
- `REDIS_URL` (default: redis://:password@redis:6379), `JWT_TOKEN_TTL` (default: 3600)
- `JWT_COOKIE_SECURE` (default: false for dev), `TRUSTED_PROXIES` (default: REMOTE_ADDR)
