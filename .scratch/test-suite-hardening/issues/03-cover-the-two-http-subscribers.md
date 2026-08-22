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

**Status:** ready-for-agent

- [ ] A test helper gives the rate limiter a cache pool that survives requests, following the existing blocklist helper
- [ ] Exceeding the login limit returns the documented rate-limited response, and the test fails if the limiter is removed from the login route
- [ ] The public endpoint limit is covered the same way, since it has a different ceiling
- [ ] A request under the limit is unaffected, so the test cannot pass by rejecting everything
- [ ] Every response header the subscriber sets is asserted by name and value
- [ ] Deleting either subscriber makes the suite red
- [ ] Full API suite green
