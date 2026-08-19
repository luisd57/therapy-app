# 18 - Cover Viewer Zones the tests never reach

**What to build:** the Slot browser is verified from a Viewer Zone that is
neither the Practice Timezone nor Western Europe, so the grid, the zone banner
and the recorded Requester Timezone are known to hold at a non-whole-hour offset
near the date line.

**Every zone-aware test uses the same two zones.** All thirty cases in the
landing date-helper unit suite are written against `America/Caracas` and
`Europe/Madrid`, and both Playwright describes that set a `timezoneId` use those
same two. Nothing exercises a half-hour or forty-five-minute offset, and nothing
crosses the date line.

Daylight saving itself is already pinned, so this is not that gap: day-key
arithmetic is tested across a transition, the zone label is tested to follow
daylight saving rather than a fixed offset, and the therapist's "5 o 6 horas" is
tested in both seasons. All three are northern-hemisphere, where the phase runs
one way.

**One claim in the code is stated but never asserted.** The instant window the
grid fetches is padded by a day on each side, justified by a comment reasoning
that the widest real offset is plus or minus fourteen hours, so a day of slack
always covers the viewer's week. Its test checks the padding shape, not the
claim. A Requester far enough east is the case that claim exists for.

**Deliberately one ticket, not a zone matrix.** The diaspora is concentrated in
Western Europe and North America, so a Requester at UTC+13 is the tail rather
than the common case. Against ten million-plus Venezuelans abroad the tail is
not empty, and the cost of getting it wrong is someone missing a session, so it
is worth buying once, cheaply. It is not worth an ongoing combinatorial suite.

**The trap this must avoid.** Adding a zone and watching the suite stay green
proves nothing by itself. That is exactly what happened when the API suite moved
to `Pacific/Kiritimati` at UTC+14: fixtures were built naively and expectations
derived by formatting those same fixtures, so the harshest offset in the world
produced zero new failures. Every assertion added here is an absolute value
written down by hand. See ADR-0003.

One zone can carry all three properties at once: `Pacific/Chatham` sits at
UTC+12:45, observes southern-hemisphere daylight saving, and is next to the date
line. If the browser's ICU build will not accept it, `Asia/Kolkata` for the
half-hour offset and `Pacific/Auckland` for the date line cover the same ground
in two.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A unit case pins a Viewer Zone whose offset is not a whole number of hours
- [ ] A unit case pins a Viewer Zone near the date line, where a Slot's Day key differs from the Practice Timezone's
- [ ] A unit case pins southern-hemisphere daylight saving, whose phase is opposite to the existing European cases
- [ ] The plus-or-minus-fourteen-hours claim behind the fetch window padding is asserted, not just stated in a comment
- [ ] A Playwright describe runs the browse-and-book path under a third `timezoneId`
- [ ] That spec asserts a Slot is bucketed under the Requester's Day key rather than the Practice's
- [ ] That spec asserts the submitted request records the Viewer Zone as the Requester Timezone
- [ ] Every assertion added is an absolute expected value, not one derived from the fixture under test
- [ ] Landing unit suite green
- [ ] Landing e2e suite green
