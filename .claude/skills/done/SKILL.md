---
name: done
description: Record a completed feature or milestone in docs/STATUS.md
disable-model-invocation: false
---

Update `docs/STATUS.md` for the component just finished - one line, no description.

If the work came from a ticket in `.scratch/`, close it in the same pass:

- Set its `Status:` line to `resolved` and add `**Resolved by:** [PR #NN](full GitHub URL)`. A bare
  `PR #NN` is not the format - every resolved ticket in `.scratch/` carries the link, and the number
  alone is not clickable from the file.
- Tick only the acceptance criteria you actually verified. If any are unmet, leave them
  unticked, say which, and leave the status alone - a ticket that is not finished is not
  resolved, however much of it shipped.
- Add `> Frozen record, resolved <date>.` on its own line under the title, dated the day you
  resolve it (normally the day the PR merged). It tells a later reader the facts below are a
  snapshot (see `docs/agents/issue-tracker.md`).
- Keep the file. It carries the reasoning; the next reader needs the why more than the tidiness.

If the ticket is in `.scratch/test-suite-hardening/`, the survivor docs in `mutation/` point at
it and go stale when it closes:

- Drop the ticket from the `tickets` table in `api-list.mjs`, or the next rerun of the list
  still marks those files as waiting on it.
- If `api-survivors-sorted.md` lists rows under `## Skipped` for this ticket, they are no longer
  waiting on anything. Record what the work did with them (killed, Kind 2, Kind 3) and fix the
  counts. If the work did not sort them, say so and leave the rows. Sorting them is its own task.
- Leave `api-survivors.md` alone, it is generated.

If the work revealed a reusable pattern or a non-obvious gotcha, say so and ask whether to
record it before writing anything.

Do not edit `.claude/rules/` unless the user explicitly asks.
