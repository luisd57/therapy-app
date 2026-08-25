# 09 - Exercise every password rule in the dashboard e2e

**What to build:** every password rule is covered by a spec, so deleting one
fails the suite.

The dashboard validates passwords against six independent conditions, minimum and
maximum length plus four character classes, on both the registration and the reset
screens. No spec reaches
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
breaks exactly one rule, so the strength message appearing is proof that rule
fired, because nothing else could have produced it. Assert that message rather
than a disabled submit button, which a missing required field would also satisfy.

**Both errors are gated on `touched`.** Filling the field is not enough to render
either one: Playwright's `fill()` does not blur. `auth-login.spec.ts` already
handles this and says so in a comment, so follow it rather than rediscovering it.

**These specs will bind to English UI text**, as every existing dashboard spec
does. `timezone-management/07` rewrites that text. Scoping to the field instead of
the message does not escape it, since the only handle on the field is its label
and the sweep rewrites labels too. So either land 07 first, or accept rewriting
these strings once. Not a blocker either way, just a cost to choose knowingly.

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
- [ ] Each case asserts the strength message, not a disabled submit button
- [ ] Each case blurs the field, since the error does not render until the control is touched
- [ ] A valid password is accepted, so the specs cannot pass by rejecting everything
- [ ] The second screen is covered enough to catch it drifting from the shared validator
- [ ] No test dependency is added to the dashboard
- [ ] Dashboard e2e suite green, and lint and build green
