# Mutation lists

`api-survivors.md` and `landing-survivors.md` are dated evidence, not a backlog (ADR-0010). The two
scripts here rebuild them from the tools' JSON output, so a later run gives a list in the same
shape. Nothing in CI uses them.

`api-survivors-sorted.md` is the API list sorted by hand into three kinds, with the reason for each call.
No script rebuilds it, so after a rerun of the API list it is stale until someone sorts it again.

## Regenerate the API list

Needs the per-worker test databases first (`.claude/rules/dev-gotchas.md`).

```bash
docker compose exec -T php vendor/bin/infection --threads=8 --only-covering-test-cases
docker compose exec -T php cat var/infection/infection.json > /tmp/infection.json
node .scratch/test-suite-hardening/mutation/api-list.mjs /tmp/infection.json .scratch/test-suite-hardening/mutation/api-survivors.md <date> "<wall time>"
```

The commands are for Git Bash. `/tmp` does not resolve in PowerShell.

Run it on a warm `var/cache/test`, after a plain `vendor/bin/phpunit`. On a cold cache five extra
survivors appear on the `getSubscribedEvents()` lines of the two HTTP subscribers, and the script
then adds a paragraph saying so.

## Regenerate the landing list

```bash
cd landing && npm run mutation
node ../.scratch/test-suite-hardening/mutation/landing-list.mjs reports/mutation/mutation.json ../.scratch/test-suite-hardening/mutation/landing-survivors.md <date> "<wall time>"
```

## Update by hand before a rerun

- The `tickets` table at the top of `api-list.mjs`. It maps a source path to the ticket covering
  it, and it was read off the tickets as they stood on 2026-10-08.
- The literal header rows: Tool, Tree, Command and Config in `api-list.mjs`, and Tool, Command and
  Mutated in `landing-list.mjs`.
- The "No ticket covers these two files" sentence in `landing-list.mjs`, if a ticket now does.
