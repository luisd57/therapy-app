# 19 - Enforce the API layer dependency rule

**What to build:** the hexagonal dependency rule fails the build when it is broken, instead
of being a sentence in a rules file that nothing consults at merge time.

`api-architecture.md` states it in one line: Infrastructure to Application to Domain, never
the reverse. Nothing checks it. ADR-0007 says so outright and names the tool, that
core PHPStan has no dependency-direction rules so this needs deptrac or a custom rule.
Deptrac is the choice: it ships its own binary, so it does not wait on ticket 11, and a
layer rule in a depfile is declarative rather than a graph traversal somebody has to write
and then write tests for.

**This lands green, so it is lock-in rather than migration.** Measured 2026-09-03: no file
under `src/Domain/` imports from `App\Application` or `App\Infrastructure`, and no file
under `src/Application/` imports from `App\Infrastructure`. Say that plainly, because it
sets the bar for the ticket. A green first run shows the layering is clean today and shows
nothing whatsoever about whether the gate is connected. Forcing a violation is the
criterion that carries the weight.

**The layer rule is the whole scope, and there is deliberately no cycle rule.**
`Domain/User` and `Domain/Appointment` import each other. Measured 2026-09-03: `User` holds
the three inverse collections ADR-0007 added, five files under `Domain/Appointment` import
`User` or `UserId`, and a sixth reaches for `Email` and `Timezone`. That is a real cycle in
either reading, and it is a decision taken and implemented on 2026-08-30,
not drift. A no-cycles rule would fire on the day it landed, and could only be satisfied by
reopening the ADR or by carrying an exception from the first commit, which is the baseline
problem ticket 11 warns about in miniature. Leave it out, and record in the depfile that it
was left out on purpose.

**The framework imports inside Domain are sanctioned too.** Thirty-seven across twenty-five
files, measured 2026-09-03: Doctrine ORM and DBAL attributes, Doctrine Collections, Symfony
Uid, and Symfony Security on the `User` entity. `api-architecture.md` opens by saying the Domain layer has no
framework dependencies and then blesses most of this under ORM Pragmatism. Do not write a
rule that contradicts the ADR to satisfy the opening sentence. If that tension is worth
resolving, it is resolved in the rules file, not here.

**What this cannot catch.** Direction between three layers and nothing else. It does not see
a handler doing infrastructure work inline, a Domain service reaching through a port it
should not, or a class sitting in the right layer under a name that means something else.
Those stay with review.

**Blocked by:** None - can start immediately. Specifically not blocked by 11: deptrac runs
as its own binary rather than inside PHPStan, so the analyser decision does not gate it.

**Status:** ready-for-agent

- [ ] Deptrac is installed for the API and its depfile declares the three layers, with Infrastructure to Application to Domain as the only permitted direction
- [ ] The depfile records that intra-Domain cycles are deliberately unchecked, citing ADR-0007, so the omission reads as a decision rather than an oversight
- [ ] The first run over the current tree is green, and the pull request reports that as a measurement rather than as evidence the gate works
- [ ] A deliberate violation, an import of `App\Infrastructure` from a file under `src/Domain/`, fails the pipeline
- [ ] The check runs in continuous integration and a violation fails the build
- [ ] Full pipeline green
