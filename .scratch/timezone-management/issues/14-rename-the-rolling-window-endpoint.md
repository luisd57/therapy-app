# 14 - Rename the rolling-window endpoint to say what it returns

**What to build:** `GET /api/appointments/next-available-week` returns a rolling
seven-day window anchored on now, not a calendar week. Rename it so the contract
reads honestly, and record the term so the next consumer does not have to
rediscover it.

Ticket 12 is the defect the current name caused: the landing slot browser snapped
the window start to its Monday and rendered a Monday-to-Sunday week that held none
of the Slots the endpoint had just returned. Any consumer that lays out calendar
weeks walks into the same trap, and the dashboard appointment queue is next in
line.

The behaviour is correct - a rolling window from now is exactly what the landing
page needs - so this is a vocabulary change, not a contract change. The response
keeps its shape and its meaning; only the names move.

Scope, all of it in one ticket: the endpoint path becomes `next-availability`; the
handler and both DTOs follow the new name; the response fields `week_start` and
`week_end` become `window_start` and `window_end`; the landing API client, its
types and the slot browser move with them; the Postman collection and the landing
e2e global setup and README are updated. The term is recorded in `CONTEXT.md`
alongside the existing availability vocabulary.

One ticket rather than an expand-contract sequence: there are 16 in-repo consumers
and no external clients, so the API and the landing app change together in one
commit and CI never sees a half-renamed state.

**Blocked by:** 12 - both touch the slot browser's mount path.

**Status:** ready-for-agent

- [ ] No `next-available-week` or `week_start`/`week_end` reference survives outside the ADRs and resolved tickets that describe the history
- [ ] The renamed term is in `CONTEXT.md`, with the rolling-versus-calendar distinction stated
- [ ] API suite green, including the handler unit test and the controller integration test that pin the new field names
- [ ] Landing unit suite, build and e2e green
- [ ] Postman collection requests still run against the renamed endpoint
