# GitHub Actions Test Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a single GitHub Actions workflow that runs API PHPUnit tests, dashboard lint+build, and landing build on every push to `main` and every PR targeting `main`.

**Architecture:** One workflow file (`.github/workflows/ci.yml`) with one job (`test`) running on `ubuntu-latest`. The job declares Postgres 16 and Redis 7 service containers, then runs three sequential stages: API → dashboard → landing. PHP and Node are installed on the runner (not in containers). Test credentials live in the workflow file (not in repo secrets) — they only need to match `.env.test`.

**Tech Stack:** GitHub Actions, `shivammathur/setup-php@v2`, `actions/setup-node@v4`, `actions/cache@v4`, `actions/checkout@v4`.

**Spec:** `docs/superpowers/specs/2026-05-28-github-actions-test-pipeline-design.md`

---

## File Structure

**Create:**
- `.github/workflows/ci.yml` — the workflow.

**Modify:** none. The workflow only consumes existing test config.

**No test files** for this work — CI itself is verified by observing it run green on GitHub.

---

## Pre-flight: Confirm CI-relevant repo state

These are reads, not edits. Do them once before Task 1 so the workflow you write actually matches what's in the repo.

- [ ] **Step 1: Confirm composer + phpunit setup**

Run: `cat API/composer.json | grep -E 'phpunit|"php"'` — expect `phpunit/phpunit ^10.5` and `php >=8.4`.
Run: `cat API/phpunit.xml.dist | head -25` — confirm `KERNEL_CLASS=App\Kernel` and `APP_ENV=test` are forced.

- [ ] **Step 2: Confirm doctrine test DB suffix**

Run: `grep -n dbname_suffix API/config/packages/doctrine.yaml` — expect `dbname_suffix: '_test%env(default::TEST_TOKEN)%'`. This means the actual test DB will be `therapy_db_test`, not `therapy_db`. The workflow must `doctrine:database:create` it.

- [ ] **Step 3: Confirm JWT keys are gitignored**

Run: `grep jwt API/.gitignore` — expect `/config/jwt/*.pem`. The workflow must generate keys on the fly via `lexik:jwt:generate-keypair`.

- [ ] **Step 4: Confirm Node 20 is acceptable**

Run: `cat dashboard/package.json | grep -E '"@angular/core"'` — expect `^21.x`. Angular 21 supports Node 20. If it ever drops support, bump `node-version` in the workflow.

---

## Task 1: Create the workflow file

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create directory**

Run: `mkdir -p .github/workflows`

- [ ] **Step 2: Write the full workflow**

Write `.github/workflows/ci.yml` with this exact content:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    name: test
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_DB: therapy_db
          POSTGRES_USER: therapy_user
          POSTGRES_PASSWORD: therapy_pass
        ports:
          - 5432:5432
        options: >-
          --health-cmd "pg_isready -U therapy_user -d therapy_db"
          --health-interval 5s
          --health-timeout 5s
          --health-retries 10

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 5s
          --health-timeout 5s
          --health-retries 10

    env:
      # API stage env. Set at the job level so every step inherits them.
      APP_ENV: test
      APP_SECRET: test_secret_for_testing_only
      DATABASE_URL: "postgresql://therapy_user:therapy_pass@localhost:5432/therapy_db?serverVersion=16&charset=utf8"
      REDIS_URL: "redis://localhost:6379"
      MAILER_DSN: "null://null"
      JWT_SECRET_KEY: "%kernel.project_dir%/config/jwt/private.pem"
      JWT_PUBLIC_KEY: "%kernel.project_dir%/config/jwt/public.pem"
      JWT_PASSPHRASE: therapy_jwt_passphrase
      JWT_TOKEN_TTL: 28800
      APP_FRONTEND_URL: http://localhost:3000
      INVITATION_TOKEN_TTL: 86400
      PASSWORD_RESET_TOKEN_TTL: 3600

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      # ---------- API ----------
      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: ctype, iconv, pdo_pgsql, intl
          tools: composer:v2
          coverage: none

      - name: Cache composer dependencies
        uses: actions/cache@v4
        with:
          path: |
            ~/.composer/cache
            API/vendor
          key: ${{ runner.os }}-composer-${{ hashFiles('API/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install API dependencies
        working-directory: API
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Generate JWT keypair
        working-directory: API
        run: php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

      - name: Create test database
        working-directory: API
        run: php bin/console doctrine:database:create --env=test --if-not-exists --no-interaction

      - name: Run migrations on test database
        working-directory: API
        run: php bin/console doctrine:migrations:migrate --env=test --no-interaction --all-or-nothing

      - name: Run PHPUnit
        working-directory: API
        run: vendor/bin/phpunit

      # ---------- Dashboard ----------
      - name: Setup Node 20
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
          cache-dependency-path: |
            dashboard/package-lock.json
            landing/package-lock.json

      - name: Install dashboard dependencies
        working-directory: dashboard
        run: npm ci

      - name: Lint dashboard
        working-directory: dashboard
        run: npm run lint

      - name: Build dashboard
        working-directory: dashboard
        run: npm run build

      # ---------- Landing ----------
      - name: Install landing dependencies
        working-directory: landing
        run: npm ci

      - name: Build landing
        working-directory: landing
        run: npm run build
```

- [ ] **Step 3: Validate the YAML locally**

Run: `python -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"` and expect no output.
If Python isn't available, run: `node -e "require('yaml')"` — if that errors, skip; YAML parse errors will surface on GitHub anyway.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add GitHub Actions test pipeline"
```

---

## Task 2: Trigger and observe the first run

This is a live verification step. The workflow only proves itself by running on GitHub.

- [ ] **Step 1: Create a verification branch**

```bash
git checkout -b ci/verify-pipeline
```

- [ ] **Step 2: Make a no-op change so a PR has something to show**

Append a single trailing newline to `docs/superpowers/specs/2026-05-28-github-actions-test-pipeline-design.md`, or touch a comment in `README.md`. Anything trivial.

- [ ] **Step 3: Push and open a PR**

```bash
git add -A
git commit -m "ci: trigger pipeline verification"
git push -u origin ci/verify-pipeline
gh pr create --title "ci: verify GitHub Actions pipeline" --body "Verifying the new CI workflow runs green."
```

- [ ] **Step 4: Watch the run**

Run: `gh run watch` (auto-picks the most recent run on this branch).
Expected: the `test` job completes successfully.

- [ ] **Step 5: If any stage fails, diagnose using stage-specific commands below**

See the **Troubleshooting** section at the end of this plan. Common failures and exact fixes are listed there.

- [ ] **Step 6: Merge once green**

```bash
gh pr merge --squash --delete-branch
```

---

## Task 3: (Optional, manual) Add branch protection

This is a UI-only step on GitHub. No code changes. Skip if not wanted.

- [ ] **Step 1: Navigate to branch protection settings**

Open: `https://github.com/<owner>/<repo>/settings/branches`

- [ ] **Step 2: Add or edit the rule for `main`**

- Click "Add rule" (or "Edit" if one exists for `main`).
- Branch name pattern: `main`
- Check: **Require status checks to pass before merging**
- Check: **Require branches to be up to date before merging**
- In the status checks search box, type `test` and select the entry that appears (it will only appear AFTER the workflow has run at least once — that's why Task 2 has to be done first).
- Optional but useful: also check **Require a pull request before merging**.
- Scroll to the bottom and click **Create** / **Save changes**.

- [ ] **Step 3: Verify by trying to push directly to main**

Run: `git push origin main` from a branch that isn't `main` won't trigger anything. The verification is: open a new PR and confirm the merge button is blocked until `test` passes.

---

## Troubleshooting

Triage by failure stage. The first failing stage is the only one that ran — everything after it is skipped.

**Setup PHP step fails:**
- Almost always a typo in `extensions:`. Re-check against `API/composer.json` `ext-*` entries.

**Install API dependencies (composer install) fails:**
- "Your requirements could not be resolved" → composer.lock and composer.json are out of sync. Run `composer update` locally and commit the new lock.
- "Could not find a matching version of package X" → likely a private repo with no auth. None expected in this codebase.

**Generate JWT keypair fails:**
- "command not defined" → lexik bundle didn't install. Check `composer install` step output.
- Permissions error writing to `config/jwt/` → confirm `--skip-if-exists` flag is present; if not, add it.

**Create test database fails:**
- "could not translate host name 'postgres' to address" → workflow is using the docker-compose hostname instead of `localhost`. Check the `DATABASE_URL` env block — host must be `localhost`, not `postgres`.
- "FATAL: password authentication failed" → service container env values don't match `DATABASE_URL`. They must agree on user/password/db.

**Run migrations fails:**
- "DriverException ... relation X already exists" → previous run left state. The job env is fresh per run so this should not happen; if it does, the migration is non-idempotent (pre-existing problem, not a CI bug).

**Run PHPUnit fails:**
- "Could not open input file: vendor/bin/phpunit" → composer install didn't run in `API/` directory. Verify `working-directory: API` is set on the PHPUnit step.
- A specific test failure → real test failure, treat like any local failure: read the assertion, reproduce locally with the same env vars.
- Deprecation/Warning failing the suite (due to `failOnWarning="true"`) → either fix the warning or relax phpunit.xml.dist. Out of scope for this plan; flag to the user.

**Lint or build dashboard/landing fails:**
- "Cannot find module" → `npm ci` didn't run or cache key is wrong. Verify `working-directory` is set on the install step.
- Real lint or build error → real bug; treat like a local failure.

**Whole run very slow on first attempt:**
- Expected. Caches are cold. Second run should be substantially faster (composer + npm caches hit).
