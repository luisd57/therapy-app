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

## Comments

**2026-09-03** - The tooling this ticket flags as coming "from memory rather than from
anything in this repository" is now checked against Infection 0.32.

**A coverage driver is required**: Xdebug, phpdbg or pcov. The ticket was right to treat it
as a prerequisite rather than a detail. `API/docker/php/Dockerfile` is `php:8.4-fpm` and
already runs `pecl install redis`, so the change is two lines beside that one. pcov 1.0.12
declares PHP 7.1 as its minimum with no upper bound stated, which is not the same as a
statement that 8.4 works. Confirm at install rather than on this note.

**`--only-covered` no longer exists.** It was removed in 0.31.0. Covered-only is the default
now and `--with-uncovered` is the opt-out, so there is nothing to pass for the behaviour this
ticket wants.

**`--git-diff-lines` compares against `master` unless told otherwise**, and this repository's
default branch is `main`. `--git-diff-base=origin/main` is mandatory rather than optional.
`infection git:default-base` reports what Infection would use on its own.

**Three flags the ticket does not name**, all aimed at the "well over an hour" estimate.
`--only-covering-test-cases` runs only the test cases covering the mutated line instead of
the whole covering file, which is worth most against the integration suite.
`--map-source-class-to-test` cuts the initial test run by mapping source classes to tests on
a `*Test` postfix, and `API/tests` mirrors the `src` namespaces, so it should mostly land.
`--threads` takes `max`.

**A soft dependency on ticket 11, which is new.** Infection 0.32 takes
`--static-analysis-tool=phpstan`, running the analyser against each mutant that escaped the
tests and marking it "Killed by SA" where the analyser rejects it. So 11 does not only
unblock 15, it makes this ticket cheaper to read and stronger. `Blocked by: None` stays
true, but 16 after 11 is a materially different run from 16 alone.

**Add 18 to the tickets worth landing first.** The ticket names 02, 04 and 13 as making
discovery worth more. 13 and 18 are stronger than that: they are prerequisites for a
baseline anyone can trust. Ticket 13 counted twenty of twenty-three clock stubs handing back
the real instant on 2026-08-30, and ticket 18 counted fifteen integration tests building
against a June 2026 fixture that has stopped being in the future on 2026-09-02. Neither
figure was re-measured here, so take them from those tickets rather than from this date.
While they hold, kill and survive results already move between runs for reasons that have
nothing to do with assertion strength, and a threshold taken before the two land is a
threshold taken on noise.

**Scope: exclude the glue by directory, rather than cutting at a layer boundary.** `API/src`
is 54 Domain, 81 Application and 83 Infrastructure, counted 2026-09-03. Cutting to Domain
plus Application would
drop the Doctrine UTC instant type, which is where ADR-0001 is actually enforced and which
ticket 05 exists for, and both HTTP subscribers, which ticket 03 just covered. Those are the
highest-value files in the tree. Exclude instead, in `source.excludes`, as paths relative to
the source directory:

- `Infrastructure/Http/Controller`
- `Infrastructure/Config`
- `Application/Appointment/DTO`, `Application/User/DTO`, `Application/Shared/DTO`

The reason is the spec's own decision rather than run time. It puts "testing all forty-three
DTO classes individually" out of scope and pins the wire contract at the integration seam
instead, which is ticket 07. Mutating that same code produces survivors no ticket covers and
none will, and the third discovery criterion above then cannot be satisfied for those files.
Infection's docs discourage glob excludes for cross-platform consistency, so list the paths.

**The committed survivor list is evidence, not a backlog.** Handing an agent a survivor list
and asking for it to be emptied produces assertions pinned to internal state: they kill the
mutant and then break on the next legitimate refactor, which raises review load rather than
lowering it. The list exists so the next reader starts from it instead of rerunning the hour,
and so tickets 02, 04, 13 and 18 can be aimed. Gating, when it arrives, is on new work.
