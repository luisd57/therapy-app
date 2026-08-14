# Landing E2E (Playwright)

End-to-end tests for the public slot browser + appointment-request flow. Runs
inside a dedicated Docker container - nothing is installed on your host.

## How it runs

A `playwright-landing` service is defined in [docker-compose.yml](../../docker-compose.yml)
using `mcr.microsoft.com/playwright:v1.49.1-noble`:

- The container starts its **own** Astro dev server (Playwright `webServer`) bound
  to `127.0.0.1:4321`, so the page origin matches the API's loopback CORS rule
  (`^https?://(localhost|127\.0\.0\.1)(:port)?$`).
- The in-container browser fetches the API at `API_BASE_URL` (`http://nginx/api`
  on the compose network). `PUBLIC_API_BASE_URL` is overridden to that value -
  the `landing/.env` default (`http://localhost:8080/api`) is unreachable from
  inside the container, and Vite prioritizes the inline env var.
- The landing app is public/anonymous - no auth, no `storageState`.
- `node_modules` lives in a named volume (`landing_playwright_node_modules`).
- Kept out of `docker-compose up` via `profiles: [e2e]`.

## Prerequisites

1. **Docker stack up**: `docker-compose up -d`.
2. **Seeded availability** - the reservation specs need real, future slots:
   ```bash
   docker-compose exec php php bin/console app:seed-schedule
   ```
   `global-setup.ts` fails fast with this exact remedy if no slots are found.

## Running

From the repo root:

```bash
docker-compose --profile e2e run --rm playwright-landing
```

Pass extra Playwright args after the service name:

```bash
docker-compose --profile e2e run --rm playwright-landing \
  npx playwright test reservation-happy-path
```

## Test files

| File                                  | What it covers                                          |
|---------------------------------------|---------------------------------------------------------|
| `reservation-happy-path.spec.ts`      | Browse → pick slot → submit request → "Solicitud recibida" |
| `reservation-form-validation.spec.ts` | Native required + email validation block submission     |
| `slot-browser.spec.ts`                | Availability + weekend gaps, week nav, modality toggle  |
| `reservation-navigation.spec.ts`      | "Cambiar horario" back nav + "Reservar otra cita" restart |
| `fixtures/helpers.ts`                 | Slot/form helpers + env constants                       |
| `global-setup.ts`                     | Waits for API, asserts availability is seeded           |

## Env overrides

| Variable               | Default (inside container)  |
|------------------------|-----------------------------|
| `LANDING_URL`          | `http://127.0.0.1:4321`     |
| `API_BASE_URL`         | `http://nginx/api`          |
| `PUBLIC_API_BASE_URL`  | `http://nginx/api`          |

(Compose maps `LANDING_API_BASE_URL` → both `API_BASE_URL` and `PUBLIC_API_BASE_URL`.)

## Reports & artifacts

`landing/playwright-report/` (HTML report) and `landing/test-results/`
(per-test traces, video, screenshots-on-failure) are written on every run and
are gitignored.

## Test data

Each request uses a unique email (`landing+<timestamp>@e2e.test`). Submitted
requests create REQUESTED appointments in the DB; they don't block slots (only
CONFIRMED appointments do), so reruns keep finding availability.
