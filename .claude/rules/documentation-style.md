---
paths:
  - CLAUDE.md
  - .claude/rules/**
  - .claude/skills/**
  - .claude/hooks/**
  - .claude/settings.json
  - docs/**
  - "**/*.md"
---

# Documentation Style

When writing or updating CLAUDE.md, .claude/rules/ files, or any project documentation:
- Be terse. Imperative sentences, not explanations.
- No code examples unless the pattern is non-obvious and can't be inferred from existing code.
- No verbose introductions or summaries.
- Write for a human skimming the file, not for completeness. If a section is long enough that
  the reader would skip it, it is too long - cut it or move it out.
- CLAUDE.md is an operating manual, not a tutorial. Every line earns its place by being something
  an agent would otherwise get wrong.
- Never duplicate information already discoverable in the codebase.
- No long checklists - only the handful of rules Claude would actually get wrong.

## Where things go

- CLAUDE.md loads every session - keep frequently-changing content (status, dates, counts) out of it. That belongs in `docs/STATUS.md`.
- Growing CLAUDE.md is the wrong move. Add a `paths:`-scoped rule in `.claude/rules/` so it loads only for matching files.
- Multi-step workflows go in `.claude/skills/`, not in a rules file - skills load on demand.
- A convention worth enforcing rather than only stating goes in `.claude/hooks/`, registered in `.claude/settings.json`. Hooks fail open, so one never replaces the rule that says why. Run `npm test` in that directory after changing one; nothing else does.
- The hooks come from the `symfony-angular-starter-rules` kit and that copy is canonical. A fix belongs there first, then here. `SOURCE_DIRS` in `skill-gate.mjs` and `AREAS` in `verify-on-stop.mjs` are the exceptions: both are trimmed to this repo's deployables and are meant to differ.
- After changing `.claude/cloud-setup.sh`, `.claude/cloud-bootstrap.sh` or a `cloud-*.mjs` hook, bump `CLOUD_ENV_REV` in the `therapy` environment's variables. A resumed start skips setup and restores an old snapshot, so only a fresh container shows the change.
- `verify-on-stop.mjs` checks uncommitted changes only. Committed work is CI's job, and a branch-wide diff would run the checks on every turn, questions included. Full PHPUnit stays in CI for the same reason: minutes per turn.
- State the reason behind a non-obvious convention. A rule with a reason survives; a bare prohibition gets "helpfully" undone.
