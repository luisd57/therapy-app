# PHPStan at level 10, with no baseline, over src and tests

Status: accepted, implemented on 2026-09-27 (`test-suite-hardening/11`).

## Decision

The API is analysed by PHPStan at **level 10**, written as the number and not as `max`, over
`src/` and `tests/`, with **no baseline file and no `ignoreErrors`** in `phpstan.dist.neon`. The
`test` job in CI runs it, so a finding fails the required check. The Symfony, Doctrine and PHPUnit
extensions are on, because without them container lookups, repository results and mocks all read
as `mixed` and the output is noise.

A red build is fixed in the code. Not by lowering the level, not by adding a baseline, and not by
an `ignoreErrors` entry.

## Why level 10 and not a ratchet

The usual advice on an existing codebase is to start low and ratchet up. We measured first, on
2026-09-27 with PHPStan 2.2.16:

| Level | src | tests |
|---|---|---|
| 0-3 | 0 | 2 |
| 4-5 | 2 | 39 |
| 6 | 51 | 46 |
| 7 | 70 | 102 |
| 8 | 128 | 126 |
| 9 | 293 | 142 |
| 10 | 309 | 369 |

678 findings at level 10, but concentrated: about 250 were controllers indexing a decoded request
body typed `mixed`, and about 250 were integration tests doing the same with response bodies. Two
typed readers cleared most of them, so the whole wall fit in one change. Levels 9 and 10 are the
`mixed` levels, and the controller boundary is exactly where they pay: stopping at 8 would have
left the bugs below unchecked.

Ticket 16 adds a second reason. Infection runs the analyser over mutants that survive the tests
and counts the ones it rejects as killed, so a higher level buys a stronger mutation score for free.

**`10`, not `max`.** `max` moves when PHPStan adds a level, so a `composer update` alone could turn
the build red with no code change. Raising the level is a decision, taken by editing the number.

## Why no baseline

A baseline grandfathers every existing finding. It gets the gate on immediately, and it is the
reason gates rot: regenerating it is one command, the diff looks like routine config churn, and a
large one is indistinguishable from not having the tool. This workflow is largely agent-driven,
and an agent facing a red build takes the shortest path to green. With no baseline there is no
such path. The gate is binary.

## Ignores

Inline only, scoped to an identifier, with the reason in brackets:
`// @phpstan-ignore method.alreadyNarrowedType (why)`. `reportIgnoresWithoutComments` is on, so a
bare ignore is itself an error and cannot be ignored. Each one needs a reason a reviewer can reject.

There were seven at adoption, all in `tests/`, none in `src/`. Five keep an assertion the analyser
can already prove: the `WeekDay` and `UserRole` backing values, which are stored and matched on,
and three `null` in, `null` out checks through a conditional return type that PHPStan does not check
against the method body. Two are the `phpunit.callParent` rule not seeing the `parent::tearDown()`
inside a `finally`. Count them again before quoting this.

## Tests are in scope

`tests/` is analysed at the same level. Ticket 15 writes custom PHPStan rules over the test suite,
and those need the directory in scope to run at all. It also paid off directly: the analyser
flagged 31 assertions it could prove always true. The `assertNotNull` calls on non-nullable
returns became assertions on the exact instant, or were removed. `assertInstanceOf` on an exception
hierarchy the same test already relies on was removed. Length self-checks on password fixtures gave
way to `str_pad`. What remains are the pins above.

Out of scope: `migrations/`, `config/`, `bin/`, `public/`. Generated or framework glue with no domain
code.

## What the adoption found

Real defects, each now covered by a test shown to fail against the old code:

- **Any JSON value of the wrong type reached a typed constructor and threw a 500.** A number sent as
  `phone`, a string sent as `supports_online`. Every controller now reads its body through
  `JsonBody`: a required field of the wrong type reads as missing and gets the usual 422, and an
  optional one of the wrong type gets its own 422 rather than being silently dropped. The worst case
  was `patient_id`, where a number would have booked the Appointment with no Patient attached.
- **`day_of_week: 3.5` was accepted as Wednesday.** It is now rejected.
- **A JWT claim of the wrong type threw inside the decode listener.** It now fails closed.
- **The Doctrine types cast anything to a string.** They now refuse a value that is neither a string
  nor `Stringable`.

## Consequences

Tests read decoded JSON through `Json::at()`, `arrayAt()` and `stringAt()` in `tests/Helper`, which
fail the test on a missing key rather than return `mixed`. Longer than `$data['data']['id']`, and the
price of analysing the tests at all.

What this cannot catch: anything the types do not describe. A value of the right type with the
wrong meaning, a test asserting the right shape of the wrong thing, and the timezone tautology
ADR-0003 is about all pass level 10.
