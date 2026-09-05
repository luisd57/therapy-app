Delegate to a subagent only for large, genuinely independent investigations. Don't delegate work
you can finish in a handful of tool calls, and don't use subagents to double-check your own work.

## Process Skills

All from the **mattpocock-skills** plugin. Invoke them; do NOT restate their guidance here.

| Situation | Skill |
|---|---|
| Idea too big for one session, route unclear | `wayfinder` |
| Plan or decision that needs stress-testing | `grilling` |
| Conversation has settled, needs writing up | `to-spec`, then `to-tickets` |
| Question dialogue can't answer | `prototype` |
| Implementing anything | `tdd` |
| Something broken, throwing, or slow | `diagnosing-bugs` |
| Before opening a PR | `code-review` |
| Terminology or an ADR | `domain-modeling` |

`to-spec`, `to-tickets` and `wayfinder` are user-invocable only. Ask for them rather than writing
a spec or ticket by hand. `docs/agents/` and the ADRs assume these flows.

Testing expectations are in `.claude/rules/testing-policy.md`.

Keep this file and `.claude/rules/` focused on project facts the plugin doesn't cover.

# Therapy Practice Management System

Single-therapist practice. Requesters and Patients browse Slots and submit Appointment requests. The Therapist manages schedules, confirms/cancels Appointments, and onboards Patients via invitation-only registration. Payments verified manually.

## Project Structure

- `API/` - Symfony 8.0 backend (PHP 8.4, PostgreSQL 16, Redis 7)
- `landing/` - Public-facing Astro + Svelte website (slot browser, appointment requests)
- `dashboard/` - Angular therapist/patient dashboard (schedule, appointments, patients)

## Dev Environment

```bash
docker-compose up -d                          # Start all containers
docker-compose exec php bash                  # Shell into PHP container
docker-compose exec php vendor/bin/phpunit    # Full API suite
```

| Service   | URL                          |
|-----------|------------------------------|
| API       | http://localhost:8080/api     |
| Frontend  | http://localhost:4321         |
| MailHog   | http://localhost:8025         |
| pgAdmin   | http://localhost:5050         |

## Appointment Status Lifecycle

```
request() ──> REQUESTED ──> CONFIRMED ──> COMPLETED
                  |          ^      |
                  v          |      v
              CANCELLED   book()  CANCELLED
```

- Two entry points, not one. `request()` starts at REQUESTED. `book()` starts at CONFIRMED and never passes through REQUESTED - it is the therapist entering an appointment for a patient who phoned. Do not assume a CONFIRMED appointment has a REQUESTED history.
- Only CONFIRMED appointments block a slot. REQUESTED appointments do NOT block - multiple visitors can request the same slot.
- COMPLETED and CANCELLED are terminal states.

## API Response Envelope

```json
{"success": true, "data": {...}}
{"success": false, "error": {"code": "...", "message": "..."}}
{"success": true, "data": [...], "pagination": {"page": 1, "limit": 20, "total": 42, "total_pages": 3}}
```

Auth: JWT via httpOnly cookie (browser) or Bearer token (API clients). Dates: ISO-8601 throughout.

## Key Business Rules

- Single-therapist system. API guards against creating a second therapist.
- Patient registration is invitation-only (time-limited token via email).
- Multiple Requesters CAN request the same Slot. Therapist resolves conflicts manually.
- Payment verification is a manual boolean toggle, not an automated gateway.
- Session duration is `APPOINTMENT_DURATION_MINUTES`. Read it, never hardcode the number, and never assume it equals the Slot start increment - they are separate rules (see `CONTEXT.md`).

## On-Demand Documentation

These files are NOT loaded automatically. Reference them with @ when needed:
- `@API/Product-Requirements.md` - the original client-discussion synthesis, frozen 2026-05-26. Historical only, requirements have moved since. For current status use `docs/STATUS.md`
- `@API/postman/Therapy_App_API.postman_collection.json` - API contract with example requests/responses

## Adding Project-Specific Rules

Where a new convention goes, and how to write it: `.claude/rules/documentation-style.md`.

## Status

Current per-component status: `docs/STATUS.md`. Read it when the state of an unfinished component matters; the `/done` skill updates it.

## Agent skills

Issue tracker (local markdown in `.scratch/`), triage labels, and domain-doc layout: see `docs/agents/`. Decisions are `docs/adr/`.

`CONTEXT.md` is the glossary - every domain term with the wordings to avoid. Read it before naming anything.
