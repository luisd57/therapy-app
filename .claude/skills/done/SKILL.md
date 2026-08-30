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
- Keep the file. It carries the reasoning; the next reader needs the why more than the tidiness.

If the work revealed a reusable pattern or a non-obvious gotcha, say so and ask whether to
record it before writing anything.

Do not edit `.claude/rules/` unless the user explicitly asks.
