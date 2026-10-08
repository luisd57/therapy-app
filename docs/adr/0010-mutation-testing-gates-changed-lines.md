# Mutation testing gates changed lines, and pcov is installed for it

Status: accepted, implemented on 2026-10-08 (`test-suite-hardening/16`).

## Decision

The API is mutation tested with **Infection**. A full run is evidence, done by hand and committed
as a dated list. CI runs the **diff-based mode only**: on a pull request, the `test` job mutates
the lines the pull request added or changed and fails below a covered MSI of **87**.

**pcov** is in the PHP image and in the CI PHP setup, with `pcov.enabled = 0`. Infection's initial
test run turns it on for itself through `initialTestsPhpOptions`. Nothing else does.

The landing side runs **Stryker** over `src/utils/dates.ts` and `src/utils/modality.ts`, the only
unit-tested modules there. That is discovery only, with no CI step.

## Why this does not reverse the coverage decision

Coverage was declined as a gate because it asks whether a line ran, and a line can run under a
test that asserts nothing about it. Mutation asks whether breaking the line is noticed. Infection
needs a coverage driver only to know which tests reach which line, so it can run those and skip
the rest. pcov is that input. There is still no coverage report, no coverage threshold and no
coverage step, and adding one is a separate decision this ADR does not take.

pcov over Xdebug: it does line coverage and nothing else, and it built on PHP 8.4.26 at version
1.0.12, which its own metadata did not promise.

## What is not mutated

`source.excludes` in `API/infection.json5`, as listed paths and not globs:

- `Infrastructure/Http/Controller`
- `Infrastructure/Config`
- `Application/Appointment/DTO`, `Application/User/DTO`, `Application/Shared/DTO`

The spec ruled out testing the DTO classes one by one and pins the wire contract at the
integration seam (tickets 07, 22, 23). Mutating that code produces survivors no ticket covers and
none will. The cut is by directory and not by layer, because cutting at Domain plus Application
would drop the Doctrine types where ADR-0001 is enforced and both HTTP subscribers.

Lines carrying a Doctrine mapping attribute are ignored by regex (`global-ignoreSourceCodeByRegex`).
A column length or a nullable flag is schema, and no test reads the schema, so those mutants can
only survive. A first run without the rule had 66 of them in 228 survivors, and under a diff gate
any pull request adding a column would have failed on them. The cost is two mutants on those
lines that a test did kill.

Uncovered code is not mutated either. That is Infection's default since 0.31, and the opt-out is
`--with-uncovered`. So the score says nothing about code no test reaches.

## Each worker needs its own state

Infection sets `TEST_TOKEN` for every worker, starting at 1, even with one thread. `doctrine.yaml`
appends it to the test database name. Three things follow, and each one produced wrong results
before it was fixed:

- **A database per worker.** Without `therapy_db_test1` every integration test fails on the
  connection, and Infection reads the failure as a kill. The first trial reported 100% killed for
  that reason.
- **A test pool per worker.** `KeepsBlocklistAcrossRequests` and `KeepsRateLimitsAcrossRequests`
  wrote to one shared directory, and the session cutoff is keyed by an email every worker reuses.
  Both pool names now carry `TEST_TOKEN`.
- **Nothing on stderr.** Infection stops its initial run on the first stderr write. The kernel's
  fallback logger printed every expected 403 there, so the test environment now logs to a file
  under `var/cache/test`.

The check for all three is `--noop`, which mutates nothing. Every mutant must survive. One that is
reported killed is a test failing for a reason other than the mutation. On 2026-10-08 the noop run
over `Infrastructure/Persistence` went from 67 false kills in 96, to 2 in 211, to 0 in 211.

So thread count is a speed setting and not a correctness one, as long as a database exists for
each. The full run used 8. CI uses 4 and creates four databases.

`timeout` is 300 seconds. At the default 10, 56 mutants in one directory were skipped as needing
more time than allowed, because one mutant can be covered by most of the integration suite.

## Why only changed lines are gated

A full run took 13 to 19 minutes on a 12-core machine. Gating every pull request on it would be paid on
changes that touch no PHP. More than that, a threshold over the whole tree invites clearing old
survivors to get a build through, and that produces assertions pinned to internal state, which
break on the next honest refactor. The committed list is evidence, not a backlog. New work is
what gets gated.

`--git-diff-base=origin/main` is required. Infection falls back to `origin/master`, which this
repository does not have. `--ignore-msi-with-no-mutations` lets a pull request with no mutable
change pass.

The php container mounts `API/` only, so it has no git repository and the diff mode cannot run
there. It runs in CI.

## Baseline

Measured 2026-10-08 with Infection 0.35.6, after tickets 02, 04, 13 and 18:

| | |
|---|---|
| Mutants | 1375 |
| Ignored | 68 |
| Killed by tests | 1130 |
| Killed by PHPStan | 12 |
| Escaped | 164 |
| Timed out | 1 |
| Covered MSI | 87.45% |

The threshold is that figure rounded down. Raising it is a decision, taken by editing the number
in `ci.yml` after a new full run. The lists are in `.scratch/test-suite-hardening/mutation/`.

Two full runs that day agreed mutant for mutant, apart from the changes made between them. Five of
the 164 are an artefact: the `getSubscribedEvents()` lines of the two HTTP subscribers are covered
only when the container is compiled during the initial run, and then nothing can kill them.

PHPStan runs over each mutant the tests missed and counts the ones it rejects as killed (ADR-0008).

The landing run the same day: 131 mutants, 88 killed, 15 survived, 28 with no coverage.

## What this cannot catch

It measures assertion strength. It is silent on a fixture that is non-deterministic and not
unasserted, on a timezone tautology of the kind ADR-0003 is about, since no mutator resolves a
datetime against a different zone, and on redundancy.
