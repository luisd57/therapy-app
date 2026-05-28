# GitHub Actions Test Pipeline — Design

**Date:** 2026-05-28
**Status:** Approved, ready for implementation

## Goal

Set up a single GitHub Actions workflow that validates every push to `main` and every PR targeting `main` by running:

1. API PHPUnit tests (Symfony 8 / PHP 8.4) against real Postgres 16 + Redis 7
2. Dashboard lint + build (Angular 21)
3. Landing build (Astro 5)

Dashboard Playwright E2E is explicitly out of scope for this iteration.

## Non-Goals

- No deployment, no release automation.
- No path filtering — all three stages run on every trigger (per user decision, simplest first).
- No matrix builds (single PHP/Node version).
- No artifact uploads (test reports, build outputs).
- No required-status enforcement at workflow-creation time; that is a separate manual step on GitHub.

## Workflow Shape

Single file: `.github/workflows/ci.yml`
Single job: `test`, runs on `ubuntu-latest`
Stages run sequentially inside the one job (API → dashboard → landing).

### Triggers

```yaml
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
```

### Service Containers (job-level)

- `postgres:16-alpine` — DB `therapy_db`, user `therapy_user`, password `therapy_pass`. Port 5432 exposed on the runner's localhost. Healthcheck via `pg_isready`.
- `redis:7-alpine` — no password (GitHub Actions service blocks do not support a `command:` field, so we can't pass `--requirepass` to a stock image). We override `REDIS_URL` in the workflow env to point at an unauthenticated localhost Redis. Port 6379 exposed on localhost. Healthcheck via `redis-cli ping`.

Service-container credentials are CI-test-only and live in the workflow file. They are not real secrets.

## Stage 1: API (PHPUnit)

**Working directory:** `API/`

**Setup steps:**

1. `actions/checkout@v4`
2. `shivammathur/setup-php@v2` with:
   - `php-version: 8.4`
   - `extensions: ctype, iconv, pdo_pgsql, intl`
   - `tools: composer:v2`
3. `actions/cache@v4` keyed on `hashFiles('API/composer.lock')` for `~/.composer/cache` and `API/vendor`.
4. `composer install --no-interaction --prefer-dist --no-progress` in `API/`.

**Test prep:**

5. Run migrations on the test database:
   `php bin/console doctrine:migrations:migrate --no-interaction --env=test`

**Run:**

6. `vendor/bin/phpunit`

**Env overrides for this stage (set on the job or each step):**

```
APP_ENV=test
DATABASE_URL=postgresql://therapy_user:therapy_pass@localhost:5432/therapy_db?serverVersion=16&charset=utf8
REDIS_URL=redis://localhost:6379
```

Notes:
- `phpunit.xml.dist` already forces `APP_ENV=test` and pins `KERNEL_CLASS=App\Kernel`. No changes to that file.
- The Docker-local `DATABASE_URL` uses host `postgres`; CI uses `localhost`. We override via workflow env, not via committing a new `.env.*` file.

## Stage 2: Dashboard

**Working directory:** `dashboard/`

1. `actions/setup-node@v4` with `node-version: 20`, `cache: npm`, `cache-dependency-path: dashboard/package-lock.json`.
2. `npm ci`
3. `npm run lint`
4. `npm run build`

Angular 21 supports Node 20. If a future bump requires Node 22, change one line.

## Stage 3: Landing

**Working directory:** `landing/`

1. Reuse Node toolchain. Add a second `setup-node` step (or `cache-dependency-path` glob covering both lockfiles) keyed on `landing/package-lock.json`.
2. `npm ci`
3. `npm run build`

`astro check` is not run (the user did not request typechecking for the landing site).

## What Runs on Failure

Each step uses default `continue-on-error: false`. The first failing step fails the job and downstream stages do not execute. This is intentional — fast feedback over completeness.

## Manual Steps Required From the User

1. **None to start the pipeline.** The workflow runs as soon as it lands on a branch and a PR is opened (or pushed to `main`).
2. **Optional, after first green run:** add a branch protection rule on `main`:
   - GitHub repo → Settings → Branches → Add rule (or "Edit" the existing rule on `main`)
   - Branch name pattern: `main`
   - Check "Require status checks to pass before merging"
   - Check "Require branches to be up to date before merging"
   - In the search box, type `test` and select the `test` job
   - Save
   - Walkthrough will be provided in the implementation plan.

## Risks / Known Caveats

- **Doctrine migration drift.** Per project memory, `doctrine:migrations:diff` here produces noisy migrations. The CI workflow runs existing committed migrations as-is; if any committed migration is broken, the API stage will fail. This is a pre-existing risk, not introduced by CI.
- **`failOnWarning="true"` in phpunit.xml.dist.** Any tolerated deprecation locally will fail CI. If this fires, we either fix the deprecation or relax the config — handled as it comes up.
- **Composer cache key.** Keyed only on `composer.lock`. If `composer.json` changes without `composer.lock` updating, cache stays stale but install still works (Composer reconciles).
- **CI Redis has no auth, local Docker Redis does.** Local `docker-compose` sets `--requirepass therapy_redis_pass`; CI uses a no-password Redis. Any test that hardcodes the password instead of reading from `REDIS_URL` will pass locally and fail in CI. The fix when it happens is to read from the env, not to add auth to the CI service.

## Out of Scope (Tracked for Later)

- Playwright E2E job (dockerized, needs full stack up). To be a separate workflow file when added.
- Code coverage upload.
- Deploy automation.
- Branch-protection rule creation (manual UI step by user).

## Acceptance Criteria

1. `.github/workflows/ci.yml` exists and is valid YAML.
2. On a PR to `main`, the `test` job runs and either passes (no test changes) or fails with output that clearly identifies which stage broke.
3. On push to `main`, same workflow runs.
4. A green run on a no-op PR is achievable without further user intervention beyond what's listed in "Manual Steps".
