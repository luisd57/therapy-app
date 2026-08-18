# 17 - A dashboard e2e failure hides the landing e2e result

**What to build:** every CI run should report both e2e suites, whatever either one
does. Today the landing step runs only if the dashboard step succeeded, so one
dashboard failure leaves the landing result unknown rather than red or green.

This bit on PR #37, a landing-only change: the dashboard step failed on an
unrelated flake, the landing step was skipped, and the pull request showed a red
e2e job that said nothing about the code under review. The failure was only
visible as a skipped step, which reads like a passing suite to anyone scanning the
run.

The two suites share the stack but not their subject matter, and both reports
already upload unconditionally. Only the run steps are ordered as a chain.

Worth deciding while in there: whether a run should stop at the first failing
suite for speed, or always execute both for information. The second is the
premise of this ticket, and it is what ticket 13 needs, since a required check has
to say which suite failed.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A failing dashboard suite still runs the landing suite, and the job result reflects both
- [ ] The run output distinguishes "this suite failed" from "this suite never ran"
- [ ] Verified against a run where one suite genuinely fails, not just a green one

## Comments

**2026-08-18** - Found while merging PR #37. Gates ticket 13: a required check
that can silently skip half its work is worse than an advisory one.
