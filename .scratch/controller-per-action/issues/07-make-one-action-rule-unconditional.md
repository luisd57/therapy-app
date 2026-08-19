# 07 - Make the one-action rule unconditional

**What to build:** The one-action-per-controller rule applies to every controller
in the codebase with no exception list, so a grouped controller cannot be
reintroduced quietly.

`RouteConventionsTest` currently carries a `PENDING_CONVERSION` allowlist naming
the controllers that still hold more than one action. Tickets 01 to 06 each
remove one entry. This ticket removes the mechanism once the list is empty, which
is the contract step of the whole effort.

Until that happens the rule has an escape hatch, and an escape hatch that outlives
its purpose is how a convention rots. The test already guards against a stale list
in one direction: it fails if a named controller no longer exists or is already
down to one action. It cannot notice that the list itself has become unnecessary,
which is what this ticket handles.

Delete the constant and the `in_array` check that consults it, so the assertion
covers every controller the router knows about. Keep the stale-entry test only if
something still uses the list; if nothing does, that test goes too rather than
being left asserting over an empty array.

`PatientAppointmentController` is not an exception and needs no entry anywhere. It
already holds exactly one action, which is the whole point of the rule splitting
rather than renaming, and it satisfies the assertion as it stands.

Check whether `## Controllers` in `.claude/rules/api-architecture.md` and the
Consequences section of ADR-0006 still describe reality once the list is gone.
Both mention the ratchet, and both should read as settled rather than in progress.

**Blocked by:** 01, 02, 03, 04, 05, 06. Every conversion must land first, because
the assertion fails while any grouped controller remains.

**Status:** ready-for-agent

- [ ] `PENDING_CONVERSION` and the check that consults it are gone
- [ ] The one-action assertion covers every controller in the route collection, with no exemptions
- [ ] The stale-entry test is removed rather than left asserting over an empty list
- [ ] `.claude/rules/api-architecture.md` and ADR-0006 describe the rule as settled, not as a migration in progress
- [ ] Full API suite green
