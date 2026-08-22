# 11 - Adopt PHP static analysis

**What to build:** the API has a static analysis gate, so the class of bug that
survives a green test suite is caught before review.

There is none today. No PHPStan, no Psalm, no CS-Fixer, and no PHP lint step in
continuous integration at all. The front ends both have strict, type-aware
linting. The API, which holds most of the code and all of the domain logic, has
none.

**This is a decision, not a gap fix, which is why it is its own ticket.** Two
choices have to be made deliberately and then lived with:

**The level.** Starting at the maximum on an existing codebase produces a wall of
findings and the usual outcome is that the level quietly drops later. Starting
low and ratcheting means the gate is real from day one. Either is defensible.
Pick one and write down why.

**The baseline.** A baseline lets the gate go on immediately by grandfathering
everything already there. It also means the gate protects new code only, and a
large baseline is indistinguishable from not having the tool. If a baseline is
used, decide what shrinks it over time, or accept that it will not shrink.

Record both in an ADR under `docs/adr/`. A level chosen without a recorded reason
gets "helpfully" adjusted by the next person who hits a red build, which is the
failure mode `documentation-style.md` warns about for bare rules.

Expect interesting findings around the hexagonal boundaries: the repositories
return domain types the ORM knows nothing about, and the DTOs hand arrays across
layers. Those are the places where analysis pays for itself.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A static analysis tool is installed and configured for the API
- [ ] The chosen level and the baseline stance are recorded in an ADR, with the reasoning
- [ ] The analysis runs in continuous integration and a violation fails the build
- [ ] The tests directory is in scope or is deliberately excluded with a stated reason
- [ ] Introducing a deliberate violation fails the pipeline, proving the gate is connected
- [ ] Full pipeline green
