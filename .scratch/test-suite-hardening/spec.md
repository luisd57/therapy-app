# Test suite hardening

Status: ready-for-agent

Branch: `test-suite-hardening`, tickets in `issues/`
Related decisions: ADR-0001, ADR-0003

Source: a full audit of all three deployables run on 2026-08-22, with every suite
executed rather than read. Eleven findings were reviewed one by one and eight were
granted. This spec records what was granted, what was rejected and why, and the
seams the tickets land on.

## Problem Statement

Every suite in this repository is green, and the green means less than it looks.

At the time of the audit: API PHPUnit 602 tests and 1400 assertions, landing
Vitest 25, dashboard Playwright 15, landing Playwright 20. All passing, nothing
skipped, nothing incomplete, no `assertEquals` anywhere, no `sleep`, no
`waitForTimeout`. By the usual signals the suite is in good health.

Underneath that, three separate problems.

**Some tests cannot fail.** The Slot value-object suite builds a naive datetime,
passes it in, then formats the resulting object back out and compares the two.
Both sides resolve against the process timezone and shift together, so no case in
that file can fail for any timezone the suite runs under. This is the exact
pattern ADR-0003 was written about, and it survived the move to a hostile
timezone for exactly this reason.

**Some code is unreachable by any test.** Rate limiting and the security response
headers both run on every request and neither has a test at any level. Rate
limiting additionally cannot be tested as things stand: under `APP_ENV=test` the
application cache is an array adapter that resets between requests, so the
limiter's sliding window starts empty every time and the configured ceiling is
never reached. Alongside them: ten of eleven Doctrine types, four of five console
commands, five security primitives, and both password-rule validators.

**Some green is an accident of state.** The dashboard's known intermittent
failure could not fire during the audit because the development database held 43
Patients, and the ambiguous locator only resolves to two elements when a list is
empty. Continuous integration tears the volume down every run, so continuous
integration is always the case that breaks. A local green run is not evidence
about it.

The common thread is that "the suite passes" is being read as "the behaviour is
pinned", and for these areas it is not. `testing-policy.md` already says this in
so many words. The audit is what measured how far it had drifted.

## Solution

Twelve tickets closing the eight granted findings, all landing on seams that
already exist. The counts differ because the largest finding, the untested edges
of the API, splits across four tickets, and because two tickets come from
decisions taken during the review rather than from a finding: static analysis and
the bundle of small drift.

The work divides into four kinds. **Make lying tests honest**: rewrite the
fixtures whose expectations are derived from the object under test, and fix the
three availability tests that wire a Slot grid the practice has never run on.
**Cover the unreachable**: the two HTTP subscribers, the auth and token
primitives, the Doctrine types, the console commands, and the response contract.
**Make one e2e failure mode survivable**: the landing spec that swaps Schedule
Blocks currently destroys the seed on a retry. **Close gaps in gates already
present**: both e2e directories sit outside lint and typecheck, and the landing
typecheck exists but is never called.

Two things are deliberately not part of the solution, both by the maintainer's
decision. No coverage instrumentation is added, and no unit-test seam is created
for the dashboard. See Out of Scope.

## User Stories

### Requester and Patient

1. As a Requester, I want the Slots I am offered to sit on the real Start Increment grid, so that the times I see are times the Therapist can actually take.
2. As a Requester, I want the availability I browse to be computed by code whose tests would notice if the grid changed, so that a configuration change cannot silently reshape what I am offered.
3. As a Requester, I want the public endpoints to stay protected by rate limiting, so that the booking service stays available when someone hammers it.
4. As a Requester, I want my Slot Lock and my Appointment request to keep the response shape the site expects, so that a renamed field does not present me with a broken page.
5. As a Patient, I want the password rules on registration to be enforced as documented, so that a rule that quietly stopped working does not leave my account weaker than the interface claims.
6. As a Patient, I want to be told which password rule I broke, so that I am not guessing at a form that will not submit.
7. As a Patient, I want my session cookie to keep its http-only, same-site, path and secure attributes, so that an accidental change to the cookie manager does not expose my session.
8. As a Patient, I want the login endpoint to stay rate limited, so that my account is not trivially brute-forced.
9. As a Patient, I want my password reset link to be minted by a generator that is verified to produce unguessable, distinct tokens, so that someone else's link cannot become mine.
10. As a Patient, I want every Instant I am shown to survive the round trip to the database unchanged, so that my session time does not shift because a type dropped a zone.

### Therapist

11. As the Therapist, I want my Schedule Blocks to survive the test suite running against the stack, so that a failed e2e run does not silently replace my real schedule with a fixture.
12. As the Therapist, I want the seeding and cleanup commands verified, so that a scheduled cleanup cannot delete rows that were not yet expired.
13. As the Therapist, I want the guard against creating a second Therapist to be covered by a test, so that the single-therapist invariant is enforced rather than assumed.
14. As the Therapist, I want Appointment Instants stored as UTC by a type with its own test, so that the decision ADR-0001 records is enforced somewhere I can point at.

### Maintainer

15. As the maintainer, I want a test that fails when duration and Start Increment are swapped, so that the two rules stay distinct in the tests as well as in the code.
16. As the maintainer, I want every timezone expectation written by hand as an absolute Instant, so that moving the suite to a hostile zone produces failures rather than false confidence.
17. As the maintainer, I want to be able to observe rate limiting in a test at all, so that the control is verifiable rather than merely present.
18. As the maintainer, I want deleting a security control to turn the suite red, so that the suite is the thing protecting the control rather than review being the only thing.
19. As the maintainer, I want the response envelope asserted whole in one place per endpoint, so that a contract change reads as one failure rather than forty.
20. As the maintainer, I want my Playwright specs linted and typechecked, so that a broken locator is a failing check rather than a failing test hours later.
21. As the maintainer, I want the landing typecheck to run in continuous integration, so that type errors in the public site stop reaching `main`.
22. As the maintainer, I want static analysis on the API, so that the class of bug a green suite cannot see is caught before review.
23. As the maintainer, I want the static analysis level and baseline stance recorded in an ADR, so that a future red build does not get fixed by quietly lowering the level.
24. As the maintainer, I want the e2e suite to be safe to retry, so that continuous integration's retry does not turn a transient failure into a permanent data problem.
25. As the maintainer, I want no stray container spawned on every compose run, so that compose warnings stay worth reading.
26. As the maintainer, I want the Practice Timezone declared once, so that the e2e helpers and the site cannot drift apart.
27. As the maintainer, I want the documentation to name commands that actually run on this machine, so that a fresh session does not start by failing.

### AFK agent

28. As an agent picking up a ticket, I want each ticket to name the behaviour that must fail if the code regresses, so that I cannot satisfy it by writing no tests.
29. As an agent, I want the tickets to say when a green run is not evidence, so that I force the failing path rather than reporting a pass.
30. As an agent, I want to know which seam a ticket belongs to before I start, so that I do not invent a new one.

## Implementation Decisions

### The two rejected findings shape the rest

**No coverage instrumentation.** The maintainer considers reaching for coverage
the wrong approach. There is no `<coverage>` block, no threshold, and none is
being added. Worth recording as a fact rather than a preference: the PHP image
carries no coverage driver either, so this was never a config toggle. No ticket
below uses a coverage percentage as an acceptance criterion.

**No dashboard unit-test seam.** The maintainer values e2e over unit for
frontend. The dashboard has zero `*.spec.ts` files, its Angular test target
declares a builder with no options, and its `tsconfig.spec.json` globs a pattern
matching no files. **No unit-test runner is being wired up.** Ticket 10 does bring
the dashboard's e2e directory under a typecheck, which is a different thing: it
type-checks Playwright specs that already exist, and creates no seam for asserting
behaviour in isolation. The consequence is that the
password-rule coverage, which would naturally be a unit suite over a pure
function, is an e2e spec instead. This matches the decision already recorded for
the landing app in `timezone-management/15`.

### Rate limiting gets an opt-in test helper, not a config change

The limiter is unobservable under test because `cache.app` is an array adapter.
Two ways to fix that: change the test environment cache globally, or put a
surviving pool behind the service per test class. The second was chosen. It
mirrors `KeepsBlocklistAcrossRequests`, which exists for the same reason and is
documented in `dev-gotchas.md`, and it keeps the divergence opt-in so the other
600 tests continue to see real test-environment behaviour.

The strict limits under test are deliberate and stay. Dev already loosens them to
1000 because the e2e suite drives everything from one container IP.

### The response contract is pinned at the integration seam

Confirmed with the maintainer. The 422 body and the Output DTO shapes are
asserted on real response bodies rather than on `toArray()` in isolation, because
that is the highest seam that can see the behaviour and it asserts what a client
actually receives.

The accepted cost: a renamed key surfaces as a controller test failure rather
than as a failure naming the DTO. Asserting the whole body in one place per
endpoint, instead of a field at a time, is what keeps that diagnosable.

### Duration and Start Increment are separate rules, in tests too

Three availability tests construct the slot rules with both set to the same
value. Production is a 50-minute session offered every 30 minutes. The factory
takes the two as adjacent integers of the same type, which is why the mistake
reads as correct. Nothing catches it today because those handlers stub the
availability computer, so the rules are built and never consulted.

This lands before `timezone-management/04`. That ticket moves sessions to 90
minutes and keeps starts at 30. If the fixtures still say "increment equals
duration" when it arrives, they become 90 and 90 and the wrong grid outlives the
change meant to correct it.

### Expected values come from an independent source

ADR-0003's rule is restated here because it is the one most often broken: an
expected value must be a hand-written absolute Instant or a worked example, never
a re-formatting of the object under test. Two violations remain and both are in
scope.

### PHP static analysis is a decision, not a gap fix

Adopting it means choosing a level and a baseline stance and then living with
both. Starting at maximum on an existing codebase produces a wall of findings and
the usual outcome is that the level quietly drops later. A large baseline is
indistinguishable from not having the tool. Whichever is chosen, it goes in an
ADR, because `documentation-style.md` is right that a bare rule with no recorded
reason gets helpfully undone by the next person to hit a red build.

### The Makefile is left alone

It does not parse, because a block of prose sits in rule position with space
indentation. It is also irrelevant: the maintainer has no `make` and does not
plan to install one, and continuous integration has never used it. The file stays
as it is. What changes is the documentation that sends readers to it.

## Testing Decisions

A good test here asserts externally observable behaviour through a public seam,
and its expected value comes from an independent source. Two failure modes are
called out specifically because both are present in committed code today.

**A test whose expectation is derived from its own fixture cannot fail.** Both
sides shift together. This is not hypothetical here: the whole suite was moved to
UTC+14 to expose implicit-local bugs and produced zero new failures.

**A test that passes for the wrong reason is worse than a missing test.** Two
integration tests assert a 409 on an Instant that is now in the past, so the
status is guaranteed by the calendar rather than by the behaviour under test. One
registration test sends a password that is invalid for two separate reasons and
asserts only the status code, so it would pass with the strength rule deleted.

**For two tickets, a green run is not evidence.** Ticket 02 must show its new
assertions fail under a deliberately wrong zone. Ticket 08 must force a failed
restore and show the next attempt still recovers the real seed. Green is what the
broken versions already produce.

### Seams

**All five already exist. No new test seam is proposed**, which is the intended
outcome under `testing-policy.md`: prefer an existing seam, and adding one is a
decision worth stating rather than a reflex.

1. **API PHPUnit Unit** - the slot rules wiring, the Slot value-object fixtures,
   the auth and token primitives, the password-rule validators, and the Doctrine
   types. The Doctrine types are the highest-value addition: the UTC Instant type
   is where ADR-0001 is actually enforced and it has no test of its own.
2. **API PHPUnit Integration** - the two HTTP subscribers, the console commands
   through the console tester, the 422 contract and the Output DTO shapes, and
   the one controller fixture in ticket 02.
3. **Dashboard Playwright** - the password rules, per the no-unit-seam decision.
4. **Landing Playwright** - the Schedule Block swap's retry safety.
5. **The pipeline itself** - widened, not new. Both e2e directories come under
   lint and typecheck, the landing typecheck gets called, and static analysis is
   added.

**No new test seam** is the claim, and seam 5 is the caveat. Ticket 11 does add a
tool the repo has never had, and ticket 10 widens gates that already exist. What
neither does is create a new place where behaviour is asserted, which is the thing
`testing-policy.md` asks to be deliberate about.

The only new test machinery is the surviving cache pool in ticket 03, and that
follows an existing helper rather than introducing a mechanism.

### Prior art

`AvailabilityComputerTest` is the reference for asserting absolute Instants: it
states its rule in a header docblock, pins fixtures to UTC, normalises before
formatting, and uses no mocks. `InstantFormatterTest` and `TimezoneGuardTest` are
equally correct and much shorter.

`KeepsBlocklistAcrossRequests` is the reference for ticket 03's cache pool.
`SendDailyAgendaCommandTest` is the reference for ticket 06, being the only
existing command test. `ProtectedRouteRolesTest` and `MappingMatchesSchemaTest`
are the reference for a guard test that asserts a convention rather than a
behaviour, and both are worth reading before writing ticket 07. The first was
`RouteConventionsTest` until PR #63 cut it back to the role check, which is worth
knowing: a guard test earns its place only where inspection cannot do the job.

Integration tests run inside a rolled-back transaction and pin "now" through the
frozen-clock helper before issuing any request, because handlers resolve the
clock lazily at dispatch. Note that 20 of 24 integration files do not currently
freeze the clock. Most genuinely do not need to.

## Out of Scope

- **Coverage measurement and thresholds.** Rejected by the maintainer. No driver
  is installed and none is being added.
- **A dashboard unit or component test seam.** Rejected. Frontend confidence is
  e2e. This extends the decision `timezone-management/15` records for landing.
- **Fixing or deleting the Makefile.** No `make` on the maintainer's machine and
  none planned. Only the documentation pointing at it changes.
- **Git hooks.** Considered and declined. Continuous integration catches the same
  failures without adding friction to every push.
- **Testing all forty-three DTO classes individually.** Most are
  constructor-to-array with nothing to get wrong. Only the serialised shapes that
  cross the wire are pinned.
- **The ambiguous Invite Patient locator.** Already `timezone-management/16`, and
  that ticket is correct and complete, including the warning not to trust a green
  rerun.
- **Making the e2e job a required check.** Already `timezone-management/13`, and
  it is deliberately last in that sequence.
- **The chained e2e steps that let a dashboard failure hide the landing result.**
  Already `timezone-management/17`.
- **Adding a third Viewer Zone to the landing matrix.** Already
  `timezone-management/18`. Ticket 12 only pins the one group that currently pins
  nothing, which is a different problem.
- **Hardcoded session duration in fixtures.** Already covered by
  `timezone-management/04`. Ticket 01 covers the Start Increment, which that
  ticket does not.
- **Rewriting the pagination assertions that use a lower bound where the
  transaction guarantees an exact count.** Real but low value, and touching the
  same controller tests `controller-per-action` is rewriting.
- **The two integration tests that assert a 409 on an Instant now in the past.**
  Named in Testing Decisions as an example of passing for the wrong reason, and
  deliberately left unticketed: the maintainer has not ruled on it, and it sits in
  controller test files `controller-per-action` is rewriting. Worth raising again
  once that sequence lands, since the tests will keep passing either way and
  nothing will prompt a second look.

## Further Notes

### Verification status - read before trusting any test result

Every suite was executed during the audit, not inferred. All four green:

| Suite | Result |
|---|---|
| API PHPUnit, both testsuites, no filter | 602 tests, 1400 assertions, 44.4s |
| Landing Vitest | 25 tests, 1.4s |
| Dashboard Playwright | 15 passed, 1.6m |
| Landing Playwright | 20 passed, 2.5m |

**The dashboard result proves nothing about the known flake.** The ambiguous
locator only resolves to two elements when a list renders its empty state, and
the development database held 43 Patients. Continuous integration runs `down -v`
every time, so continuous integration is always the failing case and a local run
is always the passing one.

**The landing run happened on a Friday evening practice-local, and that does not
count toward `timezone-management/13`.** That ticket asks for a Friday
*afternoon*, a Saturday and a Sunday. 22:45 is not an afternoon, and by then the
day's Schedule Blocks are behind the clock, so the run exercises less than an
afternoon run would. Read this as one green run on a Friday, nothing more. Of the
two defects 13 cites as the reason for those days, `timezone-management/05` is
already resolved by PR #39 and only `12` is still open on its weekend criterion.

The run passed and the Schedule Blocks were confirmed restored to 8 active
afterwards.

**Assertion density is 2.3 per test**, and there are no data providers anywhere
in the API suite. Neither is a defect on its own. Both are worth knowing when
reading 602 as a number.

### What the audit found that is genuinely healthy

Worth recording so it does not get "improved". No skipped or incomplete tests. No
`assertEquals` anywhere, so the DateTime comparison rule is fully honoured. No
`sleep`, no real network calls, no global state mutation. Ninety-three mocks and
not one of a concrete class. Handlers are covered 34 out of 34. Several tests
deliberately use real collaborators and say why in a comment, which is the right
instinct. `HealthControllerTest` bypassing the base class is deliberate and
documented in `controller-per-action/01`, not drift.

### Relationship to the other effort directories

This effort overlaps `timezone-management` and `controller-per-action` at three
points, all recorded above. Ticket 07 is genuinely blocked by the controller
split. Ticket 01 should precede `timezone-management/04` but does not block it.
Ticket 02 touches a file `controller-per-action/05` will move, and whichever
lands second carries the fix forward.
