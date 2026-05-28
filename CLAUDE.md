1. Ask, don't assume. If something is unclear, ask before writing a single line. Never make silent assumptions about intent, architecture, or requirements.

2. Simplest solution first. Always implement the simplest thing that could work. Do not add abstractions or flexibility that weren't explicitly requested.

3. Don't touch unrelated code. If a file or function is not directly part of the current task, do not modify it, even if you think it could be improved.

4. Flag uncertainty explicitly. If you are not confident about an approach or technical detail, say so before proceeding. Confidence without certainty causes more damage than admitting a gap.

# Therapy Practice Management System

Single-therapist practice. Visitors and patients can browse slots and submit appointment requests. Therapist manages schedules, confirms/cancels appointments, onboards patients via invitation-only registration. Payments verified manually.

## Workflow Rules

When the user says "/done" or indicates a feature/milestone is complete:
1. Update the "Implementation Status" section at the bottom of this file
2. If the work revealed reusable patterns or gotchas, suggest updating auto-memory (but ask first)
3. Do NOT update .claude/rules/ files unless explicitly asked

## Project Structure

- `API/` — Symfony 8.0 backend (PHP 8.4, PostgreSQL 16, Redis 7)
- `landing/` — Public-facing Astro + Svelte website (slot browser, appointment requests)
- `dashboard/` — Angular therapist/patient dashboard (schedule, appointments, patients)

## Dev Environment

```bash
docker-compose up -d                          # Start all containers
docker-compose exec php bash                  # Shell into PHP container
docker-compose exec php vendor/bin/phpunit    # Run all tests
```

| Service   | URL                          |
|-----------|------------------------------|
| API       | http://localhost:8080/api     |
| Frontend  | http://localhost:4321         |
| MailHog   | http://localhost:8025         |
| pgAdmin   | http://localhost:5050         |

## Domain Terminology

- **Therapist**: Single admin user (ROLE_THERAPIST). Exactly one.
- **Patient**: Registered user (ROLE_PATIENT), created via invitation flow.
- **Visitor / Requester**: Unauthenticated person browsing slots or submitting a request.
- **Appointment Modality**: `ONLINE` or `IN_PERSON`.
- **Schedule Block**: Recurring weekly availability window (day of week, start/end time, supported modalities).
- **Schedule Exception**: One-off unavailability that overrides schedule blocks.
- **Slot**: Concrete bookable time window computed from schedule blocks − exceptions − confirmed appointments. Duration: `APPOINTMENT_DURATION_MINUTES` (default 50 min).
- **Slot Lock**: Optional concurrency hint. Does NOT hide slots from the browser.

## Appointment Status Lifecycle

```
REQUESTED ──> CONFIRMED ──> COMPLETED
    |              |
    v              v
CANCELLED      CANCELLED
```

- Only CONFIRMED appointments block a slot. REQUESTED appointments do NOT block — multiple visitors can request the same slot.
- COMPLETED and CANCELLED are terminal states.

## API Response Envelope

```json
{"success": true, "data": {...}}
{"success": false, "error": {"code": "...", "message": "..."}}
{"success": true, "data": [...], "pagination": {"page": 1, "limit": 20, "total": 42, "total_pages": 3}}
```

- Auth: JWT via httpOnly cookie (browser) or Bearer token (API clients)
- Dates: ISO-8601 throughout

## Key Business Rules

- Single-therapist system. API guards against creating a second therapist.
- Patient registration is invitation-only (time-limited token via email).
- Slot availability = schedule blocks − exceptions − confirmed appointments.
- Multiple visitors CAN request the same slot. Therapist resolves conflicts manually.
- Payment verification is a manual boolean toggle, not an automated gateway.

## On-Demand Documentation

These files are NOT loaded automatically. Reference them with @ when needed:
- `@API/docs/database-schema.md` — entity relationships, column details
- `@API/Product-Requirements.md` — feature specs and implementation status
- `@API/postman/Therapy_App_API.postman_collection.json` — API contract with example requests/responses

## Implementation Status

### API: DONE (all endpoints implemented and tested)
### Landing: IN PROGRESS (slot browser + request form)
### Dashboard:
- DONE: Therapist login, logout, patient login, patient registration, forgot/reset password, role-based navigation, patient manager (invite/resend/revoke + patients list), Playwright E2E suite for invitation flow (containerized)
- NEXT: Appointment queue, appointment list
- TODO: Schedule manager, exception manager, therapist profile, patient area
### CI: DONE (GitHub Actions — API PHPUnit + dashboard lint+build + landing build; main protected, PRs required, `test` check must pass)
