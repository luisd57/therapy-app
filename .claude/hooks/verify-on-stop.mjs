#!/usr/bin/env node
// Stop hook: run the fast checks for each deployable with uncommitted changes.
//
// Exit 2 sends Claude back to fix it. Exit 1 would end the turn anyway. Only
// uncommitted work counts: committed work is CI's job, and a branch-wide diff would
// run the checks on every turn of a long branch, questions included. Full PHPUnit
// stays out on purpose, it takes minutes and CI runs it on every push.

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const WIN = process.platform === 'win32';
const OUTPUT_LIMIT = 4000;

// Which checks each deployable gets. Repo-specific, like SOURCE_DIRS in skill-gate.
const AREAS = [
  {
    prefix: 'API/',
    ready: apiReady,
    checks: [
      ['PHPStan', 'docker-compose', ['exec', '-T', 'php', 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G']],
      ['Deptrac', 'docker-compose', ['exec', '-T', 'php', 'vendor/bin/deptrac', 'analyse', '--no-progress']],
    ],
  },
  {
    prefix: 'dashboard/',
    ready: (cwd) => existsSync(join(cwd, 'dashboard', 'node_modules')),
    checks: [['dashboard lint', 'npm', ['run', 'lint'], 'dashboard']],
  },
  {
    prefix: 'landing/',
    ready: (cwd) => existsSync(join(cwd, 'landing', 'node_modules')),
    checks: [
      ['landing check', 'npm', ['run', 'check'], 'landing'],
      ['landing check:svelte', 'npm', ['run', 'check:svelte'], 'landing'],
    ],
  },
];

function read(stream) {
  return new Promise((resolve) => {
    const chunks = [];
    stream.on('data', (c) => chunks.push(c));
    stream.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    stream.on('error', () => resolve(''));
  });
}

// A broken hook or a missing tool must never trap the session.
function pass() { process.exit(0); }

// shell on Windows so npm.cmd and stub .cmd files resolve.
function exec(cmd, args, cwd) {
  return spawnSync(cmd, args, { cwd, shell: WIN, encoding: 'utf8', timeout: 240_000 });
}

// 127 is "not found" in sh, 9009 in cmd.
function unavailable(result) {
  return Boolean(result.error) || result.status === 127 || result.status === 9009 || result.status === null;
}

function apiReady(cwd) {
  const ps = exec('docker-compose', ['ps', '--status', 'running', '-q', 'php'], cwd);
  return !unavailable(ps) && ps.status === 0 && ps.stdout.trim() !== '';
}

function changedFiles(cwd) {
  const tracked = exec('git', ['diff', '--name-only', 'HEAD'], cwd);
  const untracked = exec('git', ['ls-files', '--others', '--exclude-standard'], cwd);
  if (tracked.status !== 0 || untracked.status !== 0) return null;
  return `${tracked.stdout}\n${untracked.stdout}`.split('\n').map((l) => l.trim()).filter(Boolean);
}

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { pass(); }

// Second stop after a block. Claude already saw the failure, don't loop.
if (payload?.stop_hook_active) pass();

const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();
const files = changedFiles(cwd);
if (!files?.length) pass();

const failures = [];
for (const area of AREAS) {
  if (!files.some((f) => f.startsWith(area.prefix))) continue;
  if (!area.ready(cwd)) continue;
  for (const [label, cmd, args, sub] of area.checks) {
    const result = exec(cmd, args, sub ? join(cwd, sub) : cwd);
    if (unavailable(result) || result.status === 0) continue;
    const output = `${result.stdout ?? ''}\n${result.stderr ?? ''}`.trim();
    failures.push(`${label} failed:\n${output.slice(-OUTPUT_LIMIT)}`);
  }
}

if (!failures.length) pass();

process.stderr.write(
  `Checks failed on the uncommitted changes. Fix them before finishing, or say why ` +
  `the failure is not from this work.\n\n${failures.join('\n\n')}\n`,
);
process.exit(2);
