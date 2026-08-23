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

- The test database is separate and persistent. Run `make test-db-setup` once after a fresh clone,
  and again after `down -v` or any new migration - otherwise integration tests fail confusingly.
- An *edited* migration needs more than that. The version is already recorded as applied, so
  `test-db-setup` re-runs nothing and the old schema survives. Drop first
  (`doctrine:database:drop --force --if-exists --env=test`), then `make test-db-setup`, and do the
  same for the dev database without `--env=test`. Editing in place is allowed while the series is
  unreleased, so this comes up.
- Under `APP_ENV=test`, `cache.app` is an `ArrayAdapter` that Symfony resets between requests, so
  anything cached in one request is gone by the next and `disableReboot()` does not help. Put a
  `FilesystemAdapter`-backed pool behind the service first, as
  `ResetPasswordControllerTest::useBlocklistThatSurvivesRequests()` does. Dev and prod use Redis,
  so the code is fine - only the test cannot see it.
