# Dashboard E2E (Playwright)

End-to-end tests that drive a real Chromium against the running dashboard.
Runs inside a dedicated Docker container — nothing is installed on your host.

## How it runs

A `playwright` service is defined in [docker-compose.yml](../../docker-compose.yml)
using the official `mcr.microsoft.com/playwright:v1.49.1-noble` image:

- Same Docker network as the rest of the stack → tests hit `http://dashboard:4200`
  and `http://mailhog:8025` via service DNS.
- Browsers come preinstalled in the image — no separate install step.
- `node_modules` lives in a named volume (`playwright_node_modules`), so the
  first run does an `npm install` and later runs reuse the cache.
- Kept out of the default `docker-compose up` via `profiles: [e2e]`.

## Prerequisites

1. **Docker stack up**: `docker-compose up -d` (brings up `dashboard`, `php`,
   `postgres`, `mailhog`, etc.).
2. **A therapist account** seeded in the dev DB. Default expected by
   `global-setup.ts`: `therapist@example.com` / `VerifyPass1!`. Override via
   env vars (see below) if your seed differs.
3. **Wait for the dashboard dev server to compile** the first time after
   `docker-compose up` — `docker-compose logs -f dashboard` and wait for
   `Local: http://localhost:4200/`.

## Running

From the repo root:

```bash
docker-compose --profile e2e run --rm playwright
```

That single command:
1. Spawns the playwright container (downloads the image on first run, ~2 GB).
2. Runs `npm install` inside it (only re-resolves on first run / lockfile change).
3. Executes `npx playwright test` against all `*.spec.ts` files in `dashboard/e2e/`.
4. Removes the container on exit (`--rm`).

Pass extra Playwright args after the service name:

```bash
docker-compose --profile e2e run --rm playwright \
  npx playwright test invitation-happy-path  # one file only
```

## What `globalSetup` does

1. **Waits** for the dashboard (`/`) and MailHog (`/`) to respond. The dashboard
   container starts before the Angular dev server finishes compiling on first
   boot — without the wait, the suite would race the build.
2. **Clears** the MailHog inbox via `DELETE /api/v1/messages` so each run starts
   from an empty mailbox.
3. **Logs in once** as the therapist through a real browser (Chromium) and
   saves the resulting `storageState` to `e2e/.auth/therapist.json`.
   - A browser login (not an API-only POST) is required: Angular's route guards
     check `localStorage.auth_user` synchronously, before `/auth/me` returns.
     Pure-cookie storageState bounces every protected route to `/login`.
   - Tests pick up this state via `playwright.config.ts` → `use.storageState`,
     so they don't each re-login. That avoids tripping the API's 5-login/min
     rate limiter, which fires when many tests log in from the same container IP.
   - Fails immediately with a clear error if the seeded therapist's credentials
     don't match `THERAPIST_EMAIL` / `THERAPIST_PASSWORD`.

## Test files

| File                                       | What it covers                                          |
|--------------------------------------------|---------------------------------------------------------|
| `invitation-happy-path.spec.ts`            | Invite → register → list refreshes on visibility change |
| `invitation-cookie-isolation.spec.ts`      | F1 regression: patient login in 2nd tab keeps therapist session alive |
| `invitation-resend-revoke.spec.ts`         | Row state transitions (Pending → Revoked, new Pending after resend) |
| `invitation-errors.spec.ts`                | Used token, garbage token, bad email, password mismatch |
| `fixtures/helpers.ts`                      | Shared helpers + env constants                          |
| `global-setup.ts`                          | MailHog clear + therapist login pre-check               |

## Env overrides

| Variable             | Default (inside container)           |
|----------------------|--------------------------------------|
| `DASHBOARD_URL`      | `http://dashboard:4200`              |
| `MAILHOG_URL`        | `http://mailhog:8025`                |
| `THERAPIST_EMAIL`    | `therapist@example.com`              |
| `THERAPIST_PASSWORD` | `VerifyPass1!`                       |

Set them in a `.env` at the repo root, or export them before the
`docker-compose` call.

## Reports & artifacts

Playwright writes its HTML report and traces to `dashboard/playwright-report/`
and `dashboard/test-results/`. Both are mounted from the host so you can
open `dashboard/playwright-report/index.html` in your browser after a run.
Both directories are gitignored.

## Test data

Each test generates unique patient emails (`verify-*+<timestamp>@e2e.test`),
so they don't collide with existing data. Created invitations/patients are
left in the DB. Sweep with:

```bash
docker-compose exec php php bin/console app:cleanup-tokens
```
