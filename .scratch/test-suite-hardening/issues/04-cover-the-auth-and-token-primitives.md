# 04 - Cover the auth and token primitives

**What to build:** the primitives that hash passwords, mint tokens and set the
session cookie are verified directly, rather than only through whichever endpoint
happens to call them.

Five classes in the security namespace have no test: the password hasher, the
secure token generator, the JWT generator, the cookie manager and the JWT created
listener. Both password rule validators are untested too.

The password strength rules are the sharpest gap. Six independent conditions plus
a length bound, and the only test standing in for them sends a request that is
invalid for two separate reasons at once and asserts nothing but the status code.
It would pass unchanged if the strength rule were deleted.

The cookie manager is worth naming separately. Its flags are the session security
posture, and today a test asserts the cookie exists and carries a value but never
that it is http-only, same-site, scoped to the right path, or secure when
configured to be. Those attributes are the part an accident would silently drop.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #83](https://github.com/luisd57/therapy-app/pull/83)

- [x] Each password strength rule is asserted on its own, with an input that violates only that rule
- [x] The maximum length bound is asserted at the boundary, not just well inside it
- [x] A failing password reports which rule failed, so the error detail contract is pinned and not just the status code
- [x] The session cookie's http-only, same-site, path and secure attributes are each asserted
- [x] The password hasher verifies its own output and rejects a wrong password
- [x] The secure token generator produces distinct values across calls
- [x] The JWT generator produces a token carrying the claims the application relies on, and the created listener's additions to the payload are asserted
- [x] Full API suite green

## Comments

**2026-09-04** - Each criterion was checked by mutating the production line it names and confirming
the test went red, rather than by the suite being green. 27 of 27 mutants killed: every rule in
both password implementations, both length bounds, all four cookie flags, the cookie TTL, both
claims the created listener adds, the token generator's entropy floor, the bcrypt default cost,
and `new PasswordStrength()` in the register controller. 643 tests before, 700 after.

Two things came out of the work.

`JwtCreatedListener` setting `email` looks redundant next to lexik's `user_id_claim: email` and is
not. `JWTManager::addUserIdentityToPayload` finds `User::getEmail()` readable and writes the `Email`
value object into the payload, so that line is what turns the claim back into the string
`JwtDecodedListener` hands to the blocklist cutoff. Both the unit and the integration test assert it
is a string, so deleting it now fails.

The two password rule implementations were tested separately rather than merged, since collapsing
them is a production change. Ticket 21 covers that, including the fact that the two copies already
disagree on empty input.
