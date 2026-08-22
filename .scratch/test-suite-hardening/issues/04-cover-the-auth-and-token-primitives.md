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

**Status:** ready-for-agent

- [ ] Each password strength rule is asserted on its own, with an input that violates only that rule
- [ ] The maximum length bound is asserted at the boundary, not just well inside it
- [ ] A failing password reports which rule failed, so the error detail contract is pinned and not just the status code
- [ ] The session cookie's http-only, same-site, path and secure attributes are each asserted
- [ ] The password hasher verifies its own output and rejects a wrong password
- [ ] The secure token generator produces distinct values across calls
- [ ] Full API suite green
