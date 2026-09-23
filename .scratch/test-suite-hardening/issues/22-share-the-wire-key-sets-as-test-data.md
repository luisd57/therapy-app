# 22 - Share the wire key sets as test data

**What to build:** each wire shape's expected key list is written down once, and
every test that pins that shape reads it from there.

Ticket 07 pinned the response contract with inline key lists, because the spec
ruled out new test machinery. As of 2026-09-23 the appointment and user lists
already appear in four places each, and the envelope and pagination lists in
more. Ticket 23 would add about twenty more pins. Without this ticket, adding
one field to an Output DTO means editing every test that pins it, and missing
one reads as a false failure somewhere unrelated.

Hold the lists in a plain test data class, not a trait or a base-class method,
following `RateLimitedRoutes`: data read by several test files that none of them
should own. The lists stay hand-written literals. Building them from the DTOs
would make every pin agree with any implementation (ADR-0003).

This reverses the spec's "the only new test machinery is the surviving cache
pool in ticket 03" for this one case, by the maintainer's decision.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Every key list ticket 07 wrote inline (envelope, error, pagination, and each Output DTO and projection) is defined once and read from there by the pins
- [ ] The lists are literals, and no list is derived from a DTO or from `toArray()`
- [ ] A failing pin still names the path it checked, so the cause reads from the failure without opening the data class
- [ ] Renaming a key in one Output DTO still turns every pin of that shape red, shown by a temporary mutation per shape, then reverted
- [ ] The helper list in the API testing rules names the new class
- [ ] The spec's line about new test machinery notes it is superseded by this ticket
- [ ] Full API suite green
