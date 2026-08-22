# 12 - Clear the small drift the audit found

**What to build:** four unrelated small things the audit turned up, none of them
worth a ticket alone, all of them cheap and currently costing something.

**An orphaned compose override.** The API directory holds a compose override left
behind by Symfony Flex recipes, declaring services that do not exist in the real
compose file. It spawns a stray container that Docker warns about on every
compose run, training everyone to ignore compose warnings. Delete it unless it
turns out to be doing something, in which case fold that into the real file.

**One landing group pins no Viewer Zone.** Every other zone-aware group in that
spec file sets an explicit zone. One does not, and its comment records that it
depends on whatever zone the container happens to run as. That makes its result
different on a developer machine than in continuous integration. Pin it like its
siblings. This is not the same as `timezone-management/18`, which is about adding
a third zone to the matrix.

**The Practice Timezone is declared twice.** The landing e2e helpers hardcode it
and the landing source config declares it as the fallback. Two sources of truth
that can drift with nothing to catch it. Have the helpers read the one in the
source.

**Docs point at commands that cannot run.** `CLAUDE.md`, the dev gotchas rule and
the API README all prescribe `make` targets. There is no `make` on the maintainer's
machine and none is planned, and the Makefile does not parse anyway because a
block of prose sits in rule position with space indentation. Continuous
integration has never used it. Point the docs at the direct commands they already
document elsewhere. **Leave the Makefile itself alone** - it is not being fixed
and not being deleted, this is only about the docs that send people to it.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] The orphaned compose override is gone and no stray container is created by a normal compose run
- [ ] Every zone-aware group in the landing spec file pins its Viewer Zone explicitly
- [ ] The Practice Timezone is declared once and the e2e helpers read it rather than repeating it
- [ ] No document instructs the reader to run a `make` target
- [ ] Landing e2e suite green, which is the level that can observe the Viewer Zone pin and the shared Practice Timezone
- [ ] The compose and documentation items are verified by a normal compose up and by following the changed docs end to end, since no suite can observe either
