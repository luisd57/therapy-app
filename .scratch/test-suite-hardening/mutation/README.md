# Mutation lists

`api-survivors.md` and `landing-survivors.md` are dated evidence, not a backlog (ADR-0010). The two
scripts here rebuild them from the tools' JSON output, so a later run gives a list in the same
shape. Nothing in CI uses them.

## Regenerate the API list

Needs the per-worker test databases first (`.claude/rules/dev-gotchas.md`).

```bash
docker compose exec -T php vendor/bin/infection --threads=8 --only-covering-test-cases
docker compose exec -T php cat var/infection/infection.json > /tmp/infection.json
node .scratch/test-suite-hardening/mutation/api-list.mjs /tmp/infection.json .scratch/test-suite-hardening/mutation/api-survivors.md <date> "<wall time>"
```

Run it on a warm `var/cache/test`, after a plain `vendor/bin/phpunit`. On a cold cache five extra
survivors appear on the `getSubscribedEvents()` lines of the two HTTP subscribers.

## Regenerate the landing list

```bash
cd landing && npm run mutation
node ../.scratch/test-suite-hardening/mutation/landing-list.mjs reports/mutation/mutation.json ../.scratch/test-suite-hardening/mutation/landing-survivors.md <date> "<wall time>"
```

## Update by hand before a rerun

- The `tickets` table at the top of `api-list.mjs`. It maps a source path to the ticket covering
  it, and it was read off the tickets as they stood on 2026-10-08.
- The Tool, Tree, Command and Config rows in both scripts. They are literals.
- The paragraph about the five cold-cache survivors in `api-list.mjs`. Drop it if the run does not
  have them.
