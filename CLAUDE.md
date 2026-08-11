Deliver what was asked, at the scope intended. Make routine judgment calls yourself, and check
in only when different readings of the request would lead to materially different work. If the
request seems mistaken or a better approach exists, say so in a sentence and continue with the
task as asked rather than quietly narrowing or widening it.

Keep responses focused and brief, and lead with the outcome. Match written documents to what
the task needs - no filler sections or redundant summaries.

Delegate to a subagent only for large, genuinely independent investigations. Don't delegate work
you can finish in a handful of tool calls, and don't use subagents to double-check your own work.

## Process Skills (Superpowers)

Brainstorming, TDD, and systematic debugging come from the Superpowers plugin - invoke those
skills; do NOT restate their guidance here. Keep this file and `.claude/rules/` focused on
project facts and conventions Superpowers doesn't cover.

# Therapy Practice Management System

Single-therapist practice. Visitors and patients can browse slots and submit appointment requests. Therapist manages schedules, confirms/cancels appointments, onboards patients via invitation-only registration. Payments verified manually.

## Project Structure

- `API/` - Symfony 8.0 backend (PHP 8.4, PostgreSQL 16, Redis 7)
- `landing/` - Public-facing Astro + Svelte website (slot browser, appointment requests)
- `dashboard/` - Angular therapist/patient dashboard (schedule, appointments, patients)

## Dev Environment

```bash
docker-compose up -d                          # Start all containers
docker-compose exec php bash                  # Shell into PHP container
make test                                     # Full suite
```

The check that proves the tree is green: `make test`.

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
- **Slot**: Concrete bookable time window computed from schedule blocks - exceptions - confirmed appointments. Duration: `APPOINTMENT_DURATION_MINUTES` (default 50 min).
- **Slot Lock**: Optional concurrency hint. Does NOT hide slots from the browser.

## Appointment Status Lifecycle

```
REQUESTED ──> CONFIRMED ──> COMPLETED
    |              |
    v              v
CANCELLED      CANCELLED
```

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
- Slot availability = schedule blocks - exceptions - confirmed appointments.
- Multiple visitors CAN request the same slot. Therapist resolves conflicts manually.
- Payment verification is a manual boolean toggle, not an automated gateway.

## On-Demand Documentation

These files are NOT loaded automatically. Reference them with @ when needed:
- `@API/docs/database-schema.md` - entity relationships, column details
- `@API/Product-Requirements.md` - feature specs and implementation status
- `@API/postman/Therapy_App_API.postman_collection.json` - API contract with example requests/responses

## Adding Project-Specific Rules

Stack/architecture conventions go in `.claude/rules/*.md`. Add `paths:` frontmatter to scope a rule to matching files (lazy-loaded); omit it for always-on rules. Repeatable multi-step workflows go in `.claude/skills/` instead. Follow `.claude/rules/documentation-style.md`.

## Status

Current per-component status: `docs/STATUS.md`. Read it when the state of an unfinished component matters; the `/done` skill updates it.

## Agent skills

Issue tracker (local markdown in `.scratch/`), triage labels, and domain-doc layout: see `docs/agents/`. Glossary is `CONTEXT.md`; decisions are `docs/adr/`.
