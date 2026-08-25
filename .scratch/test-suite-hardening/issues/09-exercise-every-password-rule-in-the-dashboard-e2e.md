# 09 - Exercise every password rule in the dashboard e2e

**What to build:** a Patient setting a password sees the specific rule they broke,
and each rule is covered by a spec.

The dashboard validates passwords against six independent conditions plus a
maximum length, on both the registration and the reset screens. No spec reaches
any of them individually. The closest is a mismatch check, which exercises a
different validator entirely. Every rule could be deleted and the suite would
stay green.

**This is deliberately end-to-end rather than a unit suite.** The dashboard has
no component or unit test seam and is not getting one. The rules are reachable
through the form, the form is what the Patient meets, and the existing suite
already covers this screen.

Both screens share the validator but are separate components, so pick one for the
per-rule sweep and cover the other at the level of "the same rules apply here",
rather than duplicating seven cases twice.

**One generic message covers all six rules today**, so a spec cannot read which
rule fired off the screen. It does not need to. Each case feeds a password that
breaks exactly one rule, so the strength error appearing is proof that rule fired,
because nothing else could have produced it.

Assert that error by locating it inside the password field, not by matching its
text. The field holds two mutually exclusive errors, and the required one only
fires on an empty field, so an error next to a non-empty password is the strength
error. Matching the text instead would tie these specs to an English string that
the Spanish sweep in `timezone-management/07` rewrites, and buy nothing for it.

Splitting the generic message into six is a product decision about what the
Patient should see, not test hardening. Out of scope here.

Note the suite's session constraint: specs share one authenticated session, and a
spec that logs out invalidates it for everything after. Registration and reset
are reached without that session, so follow the existing pattern rather than
inventing new session handling.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each rule has a case whose input violates only that rule, so a passing case cannot be explained by a different rule firing
- [ ] The maximum length bound is exercised at the boundary
- [ ] Each case asserts the strength error itself, located structurally within the password field and not matched on its text, so a missing required field cannot satisfy the assertion and the Spanish sweep cannot break it
- [ ] A valid password is accepted, so the specs cannot pass by rejecting everything
- [ ] The second screen is covered enough to catch it drifting from the shared validator
- [ ] No test dependency is added to the dashboard
- [ ] Dashboard e2e suite green, and lint and build green
