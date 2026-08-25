# 15 - Enforce the test conventions in static analysis

**What to build:** the API testing conventions that are currently prose become
rules that fail the build, so a new test cannot reintroduce a defect this effort
just spent thirteen tickets removing.

Custom PHPStan rules over `API/tests/`, deliberately chosen over a guard test that
greps its own source. Regex reads text; PHPStan reads the syntax tree, so it sees
a naive `DateTimeImmutable` construction regardless of how the line is formatted
and cannot be fooled by a mention in a docblock. That distinction is not academic
here: an audit of this suite reported the wrong figure twice by matching text that
looked right.

Rules worth writing, roughly in value order:

- A `ClockInterface` double whose `now()` returns a `DateTimeImmutable` built with
  no arguments. This is the whole of ticket 13's defect, expressed as a shape.
- A single-argument `DateTimeImmutable` built from a string literal that carries no
  offset. ADR-0003's rule, mechanised.
- `assertEquals` and `assertNotEquals` anywhere. The suite has none today and the
  reason is written down: the date comparator sees the instant, not the offset.
- `markTestSkipped` and `markTestIncomplete`, so the suite cannot shrink quietly.
- `sleep` and `usleep`.

**Sequence the first two after tickets 13 and 02.** Twenty clock stubs and a good
number of naive literals exist right now, so landing these rules first means
landing them red. Add each rule as the final act of the ticket that clears its
violations, and no allowlist is needed. That also avoids the trap the
`PENDING_CONVERSION` list hit, where emptying a loop-and-assert ratchet leaves a
zero-assertion test that `failOnRisky` then fails.

**This ticket depends on a decision ticket 11 has not made yet.** Ticket 11 allows
the tests directory to be either in analysis scope or deliberately excluded. If it
excludes `API/tests/`, these rules have nowhere to run and that call has to be
revisited before this ticket can start.

**What this cannot catch.** Shapes, not meaning. The Slot value-object tautology
would pass every rule above, because its defect is that both sides of a comparison
move together, which is a fact about what the test means rather than how it is
written. One test file per production class is also out of reach here, being file
layout rather than syntax. Prose and review still carry those.

**Blocked by:** 11.

**Status:** ready-for-agent

- [ ] The clock-stub rule and the naive-literal rule each exist and are scoped to the tests directory
- [ ] Each rule is added by the ticket that clears its existing violations, so none lands against a red suite and no allowlist is introduced
- [ ] The banned-assertion and banned-function rules cover `assertEquals`, `markTestSkipped`, `markTestIncomplete` and `sleep`
- [ ] Every rule carries a message naming the convention and where it is written down, not just the violation
- [ ] Introducing each violation deliberately fails the pipeline, one at a time, proving no rule is inert
- [ ] Full pipeline green
