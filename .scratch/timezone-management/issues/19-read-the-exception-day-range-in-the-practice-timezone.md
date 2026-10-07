# 19 - Read the exception day range in the Practice Timezone

**What to build:** when the Therapist lists Schedule Exceptions for a range of days,
the range covers those days as the Therapist's Day defines them, first day to last day
inclusive.

**Read from the code on 2026-10-07, not yet reproduced. Confirm before fixing.** The
list endpoint takes `from` and `to` as bare calendar days. The validation helper that
accepts them says, in its own comment, that such a day is read in the Practice Timezone.
`ListScheduleExceptionsHandler` does not do that. It builds each bound from the string
alone, so the bound lands in the process zone. Production runs UTC, where midnight is
20:00 the evening before in Caracas. If that holds, the range the Therapist asked for is
shifted four hours early at both ends.

**A second thing in the same handler.** `to` becomes midnight at the start of that day,
and the repository query keeps an exception only if it starts before `to`. So the last
day of the range looks excluded, and the endpoint allows `from` equal to `to`, which
would then return nothing at all. If it is real it is a separate defect at the same fix
site.

**The pattern to copy already exists.** `SendDailyAgendaHandler` reads its calendar day
with the Practice Timezone passed explicitly. A calendar day plus a zone is the whole
fix here. Do not reach for an Instant parser: this input is a Therapist's Day, not an
Instant (see `GLOSSARY.md`), and treating the two alike is what produced the bug.

**A green run does not verify this.** The suite runs at `Pacific/Kiritimati`, so the
existing handler test passes for the wrong reason. Write the failing test first: an
exception just inside each edge of the practice-local range and one just outside each,
with the bounds handed to the repository asserted as literal UTC Instants.

Ticket 20 is the sibling for wire Instants. Neither blocks the other.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] The shifted range is reproduced or ruled out by a test, and the pull request says which
- [ ] The dropped last day is reproduced or ruled out by a test, and the pull request says which
- [ ] A requested range is read in the Practice Timezone, with the bounds asserted as literal UTC Instants
- [ ] A range of a single day returns that day's exceptions
- [ ] An exception starting late in the evening of the last day, practice-local, is returned, and one starting just after midnight the next day is not
- [ ] Full API suite green

## Comments

**2026-10-07** - Found while closing test-suite-hardening ticket 15. Its PHPStan rule for
naive literals only looks at `App\Tests` and only at literals, so it cannot see this.
