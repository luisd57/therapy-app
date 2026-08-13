# Testing Policy

Applies to all three deployables. For the API's test infrastructure (helpers, transaction
wrapping, how to run suites) see `.claude/rules/api-testing.md` - don't duplicate it here.

## Test-first

Implementation is test-first via `mattpocock-skills:tdd`. Write the failing test, then the
smallest code that passes it. One slice at a time, not all tests then all implementation.

## Every change carries coverage at a level that can observe it

| What changed | Level |
|---|---|
| Domain logic, value objects, pure functions | Unit |
| Anything crossing HTTP, the database, or the container | Integration |
| A user-visible flow, where a Playwright seam already exists | E2E |

Prefer an existing seam to a new one, and the highest seam that can still see the behaviour.
Adding a seam is a decision worth stating, not a reflex.

## "Suite green" is not coverage

A green suite proves nothing was broken. It does not prove the new behaviour is pinned - it is
satisfied by writing no tests at all. A ticket whose only test criterion is "suite green" is
under-specified; name the behaviour that must fail if the code regresses.

## Expected values come from an independent source

A hand-written literal, a worked example, the spec. **Never** from formatting or re-deriving the
object under test - such a test shifts with the code and can never disagree with it.

This is not theoretical here. The whole PHPUnit suite was moved to `Pacific/Kiritimati` (UTC+14)
to expose implicit-local timezone bugs and produced zero new failures, because the existing date
tests built fixtures naively and derived expectations by formatting those same fixtures. It only
started catching anything once the assertions became absolute UTC instants. See ADR-0003.

## Run the full suite before pushing API changes

`vendor/bin/phpunit` with no `--testsuite` flag. Handler and domain changes ripple into
controller exception mapping, which only the Integration suite exercises.
