#!/usr/bin/env node
// SessionStart hook, cloud sessions only. A start resumed from the setup cache skips the setup script
// and can land on an old commit, so this starts the services and moves a stale checkout to its tip.

import { spawnSync } from 'node:child_process';

if (process.env.CLAUDE_CODE_REMOTE !== 'true') process.exit(0);

const notes = [];
const run = (cmd, args) => spawnSync(cmd, args, { encoding: 'utf8', timeout: 60_000 });
const git = (...args) => run('git', args);
const out = (result) => (result.status === 0 ? result.stdout.trim() : null);

// Snapshots keep files but not processes. Each service is started only when it does not answer.
const SERVICES = [
  ['Postgres', 'pg_isready -q', 'postgresql'],
  ['Redis', 'redis-cli ping >/dev/null 2>&1', 'redis-server'],
];
for (const [label, probe, service] of SERVICES) {
  if (run('bash', ['-c', probe]).status === 0) continue;
  if (run('bash', ['-c', `sudo service ${service} start >/dev/null 2>&1 && ${probe}`]).status !== 0) {
    notes.push(`${label} is not running and did not start`);
  }
}

// The resume path fetches the branch tip but checks out the snapshot's older local branch.
const branch = out(git('symbolic-ref', '--quiet', '--short', 'HEAD'));
if (branch && git('fetch', '-q', 'origin', branch).status === 0) {
  const behind = Number(out(git('rev-list', '--count', 'HEAD..FETCH_HEAD')) ?? 0);
  const ahead = Number(out(git('rev-list', '--count', 'FETCH_HEAD..HEAD')) ?? 0);
  if (behind > 0 && ahead > 0) {
    notes.push(`${branch} has diverged from origin/${branch} (${ahead} local, ${behind} remote commits), left as is`);
  } else if (behind > 0 && out(git('status', '--porcelain')) !== '') {
    notes.push(`${branch} is ${behind} commits behind origin/${branch}, not moved because of uncommitted local changes`);
  } else if (behind > 0) {
    const from = out(git('rev-parse', '--short', 'HEAD'));
    if (git('merge', '-q', '--ff-only', 'FETCH_HEAD').status === 0) {
      const to = out(git('rev-parse', '--short', 'HEAD'));
      notes.push(`${branch} was ${behind} commits behind origin/${branch}, moved ${from} to ${to}. Hooks and settings from the new commit load next session`);
    } else {
      notes.push(`${branch} is ${behind} commits behind origin/${branch} and the fast-forward failed`);
    }
  }
}

if (notes.length) {
  const text = `Cloud session start: ${notes.join('. ')}.`;
  process.stdout.write(JSON.stringify({
    systemMessage: text,
    hookSpecificOutput: { hookEventName: 'SessionStart', additionalContext: text },
  }));
}
process.exit(0);
