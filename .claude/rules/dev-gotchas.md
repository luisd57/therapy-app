---
paths:
  - Makefile
  - docker-compose*.yml
  - API/docker/**
  - API/config/services.yaml
  - API/tests/**
  - "**/Dockerfile"
---
# Dev Gotchas

Behaviour that looks like a bug and isn't. Terse entries: the trap and how to avoid it, nothing
more. Incident history and dates belong in project memory, not here.

## Docker / build

- `vendor/` and `var/` are named volumes, so `composer install` never populates the host directory
  and every Symfony/Doctrine symbol shows as undefined in the editor. Expected, not a broken setup.
  To silence it, snapshot the volume onto the host (gitignored; redo after any dependency change):
  ```bash
  MSYS_NO_PATHCONV=1 docker compose exec -T php tar -cf - -C //var/www/html vendor > /tmp/vendor.tar
  tar -xf /tmp/vendor.tar -C API/
  ```
  `docker cp` and `docker compose cp` both fail here (`mkdir .../vendor: file exists`) - Docker will
  not copy out of a volume mount point.
- In Git Bash, `docker compose exec` mangles container paths into Windows ones
  (`bash: C:/Program Files/Git/var/www/...`). Prefix `MSYS_NO_PATHCONV=1` and use a leading `//`.
- `vendor/` and `var/` must exist in the image before the `chown`, or Docker initialises those named
  volumes root-owned and `composer install` fails with "vendor/symfony does not exist and could not
  be created". Both Dockerfiles `mkdir -p` them first.
- `composer create-project` and flex recipes overwrite `config/services.yaml`. If a composer run
  follows a config copy, the hand-written bindings are gone and the next container compile fails on
  `RateLimitSubscriber`'s `$apiLoginLimiter`. Re-check after any recipe runs.

## Testing

- Auditing the suite with `grep -l` measures the file, not the test. Every file that freezes the
  clock also leaves methods on the wall clock, so a file-level hit says nothing about the method
  you care about. Walk it per method, and match a call rather than the bare name, or a docblock
  mentioning the helper counts as a freeze:
  ```bash
  find API/tests -name '*Test.php' -exec awk '/public function test/{fn=$0} /->freezeClock\(/{print FILENAME": "fn}' {} +
  ```
  Use `find`, not `**/*.php`. Git Bash has `globstar` off, so `**` collapses to a single level and
  silently sweeps a fraction of the tree.
  The same trap applies to any per-method fact: mocked clocks, skipped assertions, seeded fixtures.
  A file-level count always overstates coverage.
- The test database is separate and persistent. Set it up once after a fresh clone, and again
  after `down -v` or any new migration - otherwise integration tests fail confusingly:
  ```bash
  docker-compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
  docker-compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction
  ```
- An *edited* migration needs more than that. The version is already recorded as applied, so those
  two re-run nothing and the old schema survives. Drop first
  (`doctrine:database:drop --force --if-exists --env=test`), then run them again, and do the
  same for the dev database without `--env=test`. Editing in place is allowed while the series is
  unreleased, so this comes up.
- Under `APP_ENV=test`, `cache.app` and `cache.rate_limiter` are both `ArrayAdapter`s that Symfony
  resets between requests, so anything cached in one request is gone by the next and
  `disableReboot()` does not help. Put a `FilesystemAdapter`-backed pool behind the service first,
  as `KeepsBlocklistAcrossRequests` and `KeepsRateLimitsAcrossRequests` do. Dev and prod use Redis,
  so the code is fine - only the test cannot see it.
- A rate limiter is that same trap wearing a different hat: the sliding window restarts empty every
  request, so no ceiling is ever reached and a test that hammers an endpoint reads as "rate
  limiting is broken".
- A routing 404 under `test` renders the debug HTML error page, and Symfony's own
  `ErrorListener::removeCspHeader` strips `Content-Security-Policy` off it. The other five security
  headers survive, so only CSP looks missing. Assert response headers against a JSON error the app
  itself returns, not against a 404. Production never renders that page.
