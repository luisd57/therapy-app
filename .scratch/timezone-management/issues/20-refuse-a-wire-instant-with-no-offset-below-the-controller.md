# 20 - Refuse a wire Instant with no offset below the controller

**What to build:** a slot start or an exception time that arrives as text with no
offset is refused wherever it enters the Application layer, not only at the HTTP edge.

**Seven parse sites in five classes, counted 2026-10-07.** `AppointmentRequestService`,
`BookAppointmentHandler`, `LockSlotHandler`, `AddScheduleExceptionHandler` (two) and
`GetAvailableSlotsHandler` (two). Each builds an Instant from the raw string with no
zone, so a string without an offset is read in whatever zone the process runs in.

**Latent, not live.** Every controller in front of these calls `isValidInstant()`, which
refuses a string without an offset, so nothing naive arrives over HTTP. The guard is in
the wrong layer though. A console command, a queue consumer or another handler has no
such check, and production runs UTC, so a naive string would be read as UTC without any
complaint. Test-suite-hardening ticket 15 met this from the other side: unit tests were
sending naive strings straight into these classes and passing.

**Decide where the rule lives, and say so in the pull request.** The likely shape is one
named constructor or small value object that turns wire text into an Instant and throws a
domain exception when the text carries no offset, used at all seven sites. The
controllers keep `isValidInstant()`, since it produces the 422 and its message. This
ticket adds a second line of defence, it does not move the first.

**Do not fold in the day-range inputs.** A bare calendar day read in the Practice
Timezone is a different thing and is ticket 19. One helper for both is the mistake.

**A green run does not verify this.** For each site, write the test that sends a string
with no offset and expects the domain error. It fails today, because the string is
accepted.

**What this cannot catch.** A string with a wrong but valid offset. That is the caller's
meaning, not a parsing rule.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] No Application class builds an Instant from wire text with a bare one-argument `DateTimeImmutable`
- [ ] Wire text with no offset reaching any of the seven sites fails with a domain exception, and each site has a test that sends one
- [ ] Wire text with an offset still produces the same Instant as before, asserted against a literal UTC value
- [ ] The controllers' `isValidInstant()` 422 responses are unchanged
- [ ] The pull request states where the rule lives and why
- [ ] Full API suite green

## Comments

**2026-10-07** - Found while closing test-suite-hardening ticket 15. Its PHPStan rule for
naive literals only looks at `App\Tests` and only at literals, so it sees none of these:
they are in `src/` and take a variable.
