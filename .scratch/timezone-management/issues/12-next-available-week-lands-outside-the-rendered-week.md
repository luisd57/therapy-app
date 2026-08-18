# 12 - The grid renders an empty week when the next available Slot is in the following calendar week

**What to fix:** `SlotBrowser` picks which week to render from the API's rolling
availability window, but renders a Monday-to-Sunday calendar week. When the two
disagree, it shows a week it already knows has no Slots.

**This is an active defect.** It is what fails every landing spec at once, and it
is separate from ticket 05, which fails later in the flow, at submission, with a
populated grid. The two fire on different days and mask each other, so neither is
done until both are.

## What happens

`GetNextAvailableWeekHandler` returns a rolling seven-day window anchored on
today: `week_start` is today in the Practice Timezone, `week_end` is today plus
seven days. The Slots it returns can therefore fall on any of those seven days,
including days that belong to next week's Monday-to-Sunday block.

`SlotBrowser.onMount` then does:

```
weekStart = weekStartKey(dayKeyInZone(response.week_start, viewerZone));
slots = response.slots;
```

`weekStartKey` snaps to the **Monday of the calendar week containing that day**.
So the component renders Monday-to-Sunday of the week that *starts* the rolling
window, while holding Slots that may sit outside it. `slotsByDay` buckets by day
key, every bucket misses the rendered columns, and the grid draws seven empty
days. No error, no empty-state, just nothing.

## Evidence

Reproduced against the dev stack on Friday 2026-08-14, 17:26 practice-local:

- `GET /api/appointments/next-available-week` returns `found: true`,
  `week_start: 2026-08-14T04:00:00+00:00`, first Slot `2026-08-17T12:00:00+00:00`.
- `weekStartKey('2026-08-14')` resolves to `2026-08-10`, so the grid renders
  Aug 10 to Aug 16.
- `GET /api/appointments/available-slots?from=2026-08-10&to=2026-08-17` returns
  `total_slots: 0`.
- Every Slot the component holds is Aug 17 or later. Nothing can render.

CI run 31812861007 failed all eight landing specs, each timing out on
`expect(slotButtons(page).first()).toBeVisible()`. Re-running the unmodified
`main` (run 31751309940) on the same day reproduced the identical eight
failures, which rules out the change under test.

## Why it hid

Same reason as ticket 05: the calendar. With the current seed the remaining days
of a week run out on Friday at 12:00 practice-local, and there are no weekend
blocks. So it fires on any Friday afternoon, Saturday, or Sunday, and is
invisible Monday through Thursday. The Aug 13 run was a Thursday, where Friday's
Slots still fell inside the rendered week, which is why that day showed ticket
05's failure instead of this one.

The two defects mask each other. Fixing 05 alone will leave the suite red on
weekends; fixing this alone will leave it red on Fridays.

**Blocked by:** None - can start immediately. Worth doing before 05, since the
grid has to render Slots at all before the modality of a selected Slot matters.

**Status:** ready-for-agent

- [x] The rendered week always contains the Slots the component is holding
- [ ] Landing e2e passes on a Saturday, a Sunday, and a Friday afternoon practice-local
- [x] A unit test pins the case where the next available Slot falls in the following calendar week
- [x] An empty rendered week shows an empty state rather than a silent blank grid
- [x] Landing unit suite green
- [x] Landing build green

## Comments

**2026-08-18** - Shipped in PR #37 (squashed to `875ffb5`). `SlotBrowser` now picks
the week from the earliest Slot via `weekStartForAvailability`, then refetches that
full calendar week rather than keeping the truncated rolling-window response. An
empty week renders an empty state in place of the grid, suppressed on error.

Five of six criteria verified. The weekend one is not: 2026-08-18 is a Tuesday, so
the eight pre-existing landing specs would have passed with or without the fix. A
new spec (`landing/e2e/next-available-week.spec.ts`) stubs the API with
`page.route` and pins the scenario on any day, but that is a proxy, not the
real-clock run the criterion asks for. Status stays open until a Friday-afternoon,
Saturday or Sunday CI run goes green on its own.
