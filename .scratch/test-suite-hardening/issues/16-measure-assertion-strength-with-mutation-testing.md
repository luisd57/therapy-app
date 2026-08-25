# 16 - Measure assertion strength with mutation testing

**What to build:** a report naming every line of production code that can be
broken without failing a test, so "the suite is green" stops being the only thing
anyone knows about it.

Infection mutates the source, reruns the tests covering each mutation, and reports
the ones nothing noticed. A surviving mutant is a line you can change freely while
the suite stays green. That is vacuity measured rather than argued about, and it
is the one tool in this effort that answers the question it started from.

**This is not a reversal of the decision to skip coverage.** Coverage asks whether
a line ran, which is why it reads as the wrong instrument: a line can run under a
test that asserts nothing about it. Mutation asks whether breaking the line is
noticed.

**Confirm the tooling before committing to it.** Infection is understood to need a
coverage driver, Xdebug or pcov, to know which tests reach which lines, and to have
a diff-based mode that keeps continuous integration affordable. Both claims come
from memory rather than from anything in this repository. Check them against the
current release first, because the whole shape of this ticket rests on them.

What is certain from the repository: `API/docker/php/Dockerfile` installs pdo,
pdo_pgsql, zip, intl, opcache and redis. No Xdebug, no pcov, nothing equivalent
anywhere else. If a driver is required, adding one is a prerequisite and is the
same machinery declined earlier for a different purpose, so decide it knowingly.

**Two phases, in this order.** The criteria below are grouped accordingly, and the
second group should not start until the first has been read.

*Discovery.* Run it, read the surviving mutants, let them inform tickets 02, 04 and
13. The suite has known-weak assertions right now, so a first score is low and
useless as a threshold, while the list of survivors is immediately useful.

*Gating.* Only afterwards, from the improved baseline.

**Cost.** 219 source files, and the API suite ran in 44 seconds on 2026-08-25.
Both figures date fast and the second needs re-measuring before anyone plans
around it. A full run is expected to take well over an hour, which is why the
diff-based mode matters. The landing side is the cheap half: the date and modality
utilities are the only unit-tested modules there, so a run over those two should
be quick. The dashboard has no unit tests, so nothing to mutate.

**What this cannot catch.** It measures assertion strength, so it is silent on a
fixture that is non-deterministic rather than unasserted: the clock stubs returning
the real instant kill no mutant and produce no report. It is also silent on the
Slot value-object timezone tautology, because no mutation operator resolves a
datetime against a different zone. And it says nothing about redundancy, which is
a different property and one this suite does not currently have.

**Blocked by:** None - can start immediately, though discovery is worth more once
tickets 02, 04 and 13 have landed.

**Status:** ready-for-agent

Discovery:

- [ ] Whether a coverage driver is required is confirmed against the current release, and if so one is present in the PHP image with the reason recorded
- [ ] A full mutation run over the API completes and its surviving mutants are committed as a file, so the next reader starts from the list rather than rerunning the hour
- [ ] Every survivor falling in code a `test-suite-hardening` ticket already covers is annotated with that ticket number in the committed list
- [ ] The landing utilities have an equivalent run and list

Gating:

- [ ] Continuous integration runs the diff-based mode, so a pull request is never gated on a full run
- [ ] The threshold is taken from a baseline measured after tickets 02, 04 and 13, and the baseline figure is recorded with its date
- [ ] Strengthening one named assertion is shown to move the survivor count, proving the measurement responds
- [ ] Full pipeline green, including whatever image change the driver required
