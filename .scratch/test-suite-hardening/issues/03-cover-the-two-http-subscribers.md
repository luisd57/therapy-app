# 03 - Cover the two HTTP subscribers

**What to build:** the two security controls that run on every request are
verified, so removing either one fails the suite.

Neither has a test at any level. The security headers subscriber sets six
response headers and not one of those header names appears anywhere in the test
suite. The rate limit subscriber has no test either, and unlike the headers it
**cannot** be tested as things stand.

Under the test environment the application cache is an array adapter that Symfony
resets between requests. The limiter's sliding window therefore starts empty on
every request and the configured ceiling is never reached, no matter how many
times a test hits the endpoint. This is the same trap the JWT blocklist hit. The
fix there was a test helper that puts a pool surviving requests behind the
service before the kernel resolves it. Mirror that helper rather than inventing a
second mechanism, and do not relax the limits to make a test pass - the strict
limits under test are deliberate, and dev already loosens them for the e2e suite.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #77](https://github.com/luisd57/therapy-app/pull/77)

- [x] A test helper gives the rate limiter a cache pool that survives requests, following the existing blocklist helper
- [x] Exceeding the login limit returns the documented rate-limited response, and the test fails if the limiter is removed from the login route
- [x] The public endpoint limit is covered the same way, since it has a different ceiling
- [x] A request under the limit is unaffected, so the test cannot pass by rejecting everything
- [x] Every response header the subscriber sets is asserted by name and value
- [x] Deleting either subscriber makes the suite red
- [x] Full API suite green

## Comments

**2026-09-02** - The helper swaps `limiter.storage.api_login` and
`limiter.storage.api_public` rather than the `RateLimiterFactory` services above
them, so the real configured ceilings stay in play and the test duplicates no
config. `TestContainer::set()` writes into the container's private services, so
the swap has to happen before the first request builds them.

Criterion 6 was forced and observed, not assumed. Each mutation below was applied
to `API/src/`, run against `tests/Integration/Infrastructure/Http/EventSubscriber`
(11 tests), and reverted.

| Mutation | Result |
|---|---|
| `KernelEvents::RESPONSE` removed from the headers subscriber | 2 failures |
| The six `headers->set()` calls commented out | 2 failures |
| `KernelEvents::REQUEST` removed from the rate limit subscriber | 9 errors |
| The `isAccepted()` branch made dead, wiring left intact | 9 failures |
| `api_therapist_login` dropped from `resolveLimiter()` | 2 failures |
| `api_lock_slot` dropped from `resolveLimiter()` | 1 failure, named for that route |

The first two cover the headers subscriber at both its registration and its body,
so the criterion holds for deletion, not only for a broken body. Row five is the
criterion-2 wording exercised literally.

Criteria 2 and 3 are covered wider than worded. The ticket names the login and
public ceilings, but the same regression is available on any of the eight routes
`resolveLimiter()` matches, so the test is a data provider with one case per
route. Before that widening, five public routes and `api_patient_login` could
each have been deleted with the suite still green.

Criterion 7: 637 tests, 1533 assertions, green, up from 633 and 1473.

Still uncovered: the `default => null` arm. Nothing asserts that an unlisted
route stays unlimited, so moving a route into the map is not caught. Out of scope
here, since no criterion asks for it.

Two gotchas went to `.claude/rules/dev-gotchas.md`: the limiter window restarting
empty under `APP_ENV=test`, and Symfony's `ErrorListener::removeCspHeader`
stripping `Content-Security-Policy` off the debug HTML error page, which makes a
routing 404 useless for asserting that header. The other five headers survive it,
so only CSP looks missing.

**2026-09-02, later** - the `default => null` gap above is closed by
`Http/RateLimitedRouteSetTest`, which walks the router and drives
`onKernelRequest` per route. Reviewing it turned up four more holes in the
coverage this ticket shipped, all now pinned: the limiter key was not tied to the
client IP, the public routes were not shown to share one ceiling, `Retry-After`
was asserted only to fall somewhere inside the window, and the `kernel.request`
priority was unpinned even though dropping below the firewall's would let a
brute-force attempt authenticate before being counted.

Left alone deliberately: the subscriber has no `isMainRequest()` guard, so a
forwarded sub-request consumes a second token. That is a production bug rather
than a coverage gap, and this effort does not touch `src/`.
