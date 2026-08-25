# 16 - Measure assertion strength with mutation testing

**What to build:** a report naming every line of production code that can be
broken without failing a test, so "the suite is green" stops being the only thing
anyone knows about it.

Infection mutates the source, reruns the tests that cover each mutation, and
reports the ones nothing noticed. A surviving mutant is a line you can change
freely while the suite stays green. That is vacuity measured rather than argued
about, and it is the one tool here that answers the question this whole effort
started from.

**This is not a reversal of the decision to skip coverage.** Coverage asks whether
a line ran, which is why it reads as the wrong instrument: a line can run under a
test that asserts nothing about it. Mutation asks whether breaking the line is
noticed. It is the measurement coverage is usually a poor proxy for.

**It does need the coverage driver, though.** Infection reads coverage data to
know which tests reach which lines, and the PHP image ships neither Xdebug nor
pcov. Adding pcov is a prerequisite and worth deciding on knowingly, since it is
the same machinery declined earlier for a different purpose.

**Run it as discovery before running it as a gate.** The suite has known-weak
assertions right now, so a first score would be low and unactionable as a
threshold. Run it once, read the surviving mutants, and let them inform tickets
02, 04 and 13. Set a threshold afterwards, from the improved baseline.

**Cost is real.** The API suite is 44 seconds across 219 source files, so a full
run is well over an hour. Infection's diff-based mode mutates only changed lines
and is what makes this viable in continuous integration. The landing side is the
cheap half: the date and modality utilities are the only unit-tested modules, so
Stryker over those two runs in seconds. The dashboard has no unit tests, so there
is nothing there to mutate.

**What this cannot catch.** It measures assertion strength, so it says nothing
about a fixture that is non-deterministic rather than unasserted: the twenty clock
stubs returning the real instant survive no mutant and cause no report. It is also
silent on the Slot value-object timezone tautology, because no mutation operator
resolves a datetime against a different zone. And it says nothing about
redundancy, which is a different property and one this suite does not currently
have.

**Blocked by:** None - can start immediately, though the discovery run is worth
more once tickets 02, 04 and 13 have landed.

**Status:** ready-for-agent

- [ ] A coverage driver is present in the PHP image and the reason it was added is recorded
- [ ] A full mutation run over the API produces a report of surviving mutants, and that report is read rather than reduced to a score
- [ ] Surviving mutants that name a test this effort already knows is weak are cross-referenced to the ticket that fixes it
- [ ] The landing utilities are covered by an equivalent run
- [ ] Continuous integration runs the diff-based mode, not the full one, so a pull request is not gated on an hour
- [ ] Any threshold is set from a baseline taken after tickets 02, 04 and 13, not before
- [ ] Killing a mutant deliberately, by strengthening one assertion, is shown to move the report
