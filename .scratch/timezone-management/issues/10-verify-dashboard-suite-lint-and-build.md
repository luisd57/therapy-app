# 10 — Verify the dashboard suite, lint and build

**What to build:** Confirmation that the dashboard still works after being
translated and given timezone-aware date rendering.

**None of this was run in the session that produced this work** — not the
end-to-end suite, not lint, not the build. The dashboard was untouched by the
timezone changes at the time, but tickets 06 and 07 change every template that
renders a date and most that render copy, so assertions matching on visible text
are likely to break.

Separated from ticket 09 on purpose: folding both suites into one verification
step would have made the public site's timezone verification wait on the Spanish
translation, which has nothing to do with it.

Note the suite's known constraint — all specs share one authenticated session,
and a test that logs out invalidates it for everything after. Any new spec should
follow the existing pattern rather than inventing its own session handling.

**Blocked by:** 06 — Dashboard date-formatting seam and dual-time display;
07 — Spanish dashboard sweep.

**Status:** ready-for-agent

- [ ] Specs asserting on visible text are updated for Spanish copy
- [ ] Specs asserting on rendered dates are updated for the new formatting
- [ ] The dashboard end-to-end suite runs green
- [ ] Lint passes
- [ ] The production build succeeds
- [ ] The new date-formatting unit tests run in continuous integration alongside the existing suites
