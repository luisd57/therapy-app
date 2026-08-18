# 16 - Dashboard e2e flakes on an ambiguous "Invite Patient" locator

**What to build:** the invitation and auth specs should pass or fail on what they
actually test, not on whether the patients list finished loading before the click.

Two buttons carry the accessible name "Invite Patient". One lives in the patients
shell header and is always present. The other lives inside the Registered and
Invitations empty states, and appears only when that list has no rows. A role plus
name locator therefore matches two elements whenever a list is empty.

Which one a spec meets is a race. Navigating and clicking immediately lands either
while the list request is still in flight, where the spinner is up and only the
header button exists, or after it resolves with zero rows, where the empty state
has rendered and Playwright raises a strict mode violation. Specs that run before
any Patient is registered are the ones that flake; specs that run after the happy
path has registered one are safe, because the empty state is gone.

Seen on PR #37: six specs failed together, then passed on a rerun with no code
change. Do not take a green rerun as the fix. Both outcomes are reachable from the
same commit, so the fix has to remove the ambiguity, and the verification has to
force the losing side of the race rather than hope for it.

The same doubling exists on the Invitations tab, so fix the pattern, not the one
button.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] No accessible name is carried by two buttons on the same screen, or the shared helpers target one of them unambiguously
- [ ] A test exercises the state that used to break it: the list loaded and empty, not merely still loading
- [ ] The dashboard suite passes from a database with no registered Patients, which is the ordering that failed
- [ ] Dashboard lint and build green

## Comments

**2026-08-18** - Found while merging PR #37. Diagnosed from the templates: the
header button is unconditional, the empty-state buttons are behind a length check.
Gates ticket 13, since a flaky suite cannot become a required check.
