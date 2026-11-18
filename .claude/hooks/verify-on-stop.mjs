#!/usr/bin/env node
// Stop hook: run the fast checks for each deployable with uncommitted changes.
// Exit 2 sends Claude back to fix it. Scope and why: .claude/rules/documentation-style.md.

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const WIN = process.platform === 'win32';
const OUTPUT_LIMIT = 4000;
// Five checks must fit the 600s hook timeout in settings.json.
// The env override lets the tests hit a timeout without waiting two minutes.
const CHECK_TIMEOUT_MS = Number(process.env.VERIFY_ON_STOP_TIMEOUT_MS) || 110_000;

// Which checks each deployable gets: { checks } or { skip: reason }. Repo-specific, like SOURCE_DIRS in skill-gate.
const AREAS = [
  { prefix: 'API/', plan: apiPlan },
  {
    prefix: 'dashboard/',
    plan: (root) => (existsSync(join(root, 'dashboard', 'node_modules'))
      ? { checks: [['dashboard lint', 'npm', ['run', 'lint'], 'dashboard']] }
      : { skip: 'dashboard has no node_modules' }),
  },
  {
    prefix: 'landing/',
    plan: (root) => (existsSync(join(root, 'landing', 'node_modules'))
      ? {
          checks: [
            ['landing check', 'npm', ['run', 'check'], 'landing'],
            ['landing check:svelte', 'npm', ['run', 'check:svelte'], 'landing'],
          ],
        }
      : { skip: 'landing has no node_modules' }),
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
function pass(notes = []) {
  if (notes.length) {
    process.stdout.write(JSON.stringify({ systemMessage: `Stop checks skipped: ${notes.join('; ')}.` }));
  }
  process.exit(0);
}

// npm is npm.cmd on Windows, which needs a shell. One string avoids Node's DEP0190 warning.
function exec(cmd, args, cwd) {
  const options = { cwd, encoding: 'utf8', timeout: CHECK_TIMEOUT_MS };
  return WIN
    ? spawnSync([cmd, ...args].join(' '), { ...options, shell: true })
    : spawnSync(cmd, args, options);
}

// 127 is "not found" in sh, 9009 in cmd. A null status is a timeout.
function unavailable(result) {
  return Boolean(result.error) || result.status === 127 || result.status === 9009 || result.status === null;
}

// A missing binary, not a timeout or an error exit. A hung Docker Desktop must not count as "no docker".
function missing(result) {
  return result.error?.code === 'ENOENT' || result.status === 127 || result.status === 9009;
}

const PHPSTAN = ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G'];
const DEPTRAC = ['vendor/bin/deptrac', 'analyse', '--no-progress'];

// The container when it runs. Host PHP only without docker at all (a cloud session): a local
// host install has different config, and a stopped container should skip, not fail on that.
function apiPlan(root) {
  const ps = exec('docker-compose', ['ps', '--status', 'running', '-q', 'php'], root);
  if (!missing(ps)) {
    if (ps.status === 0 && ps.stdout.trim() !== '') {
      return {
        checks: [
          ['PHPStan', 'docker-compose', ['exec', '-T', 'php', ...PHPSTAN]],
          ['Deptrac', 'docker-compose', ['exec', '-T', 'php', ...DEPTRAC]],
        ],
      };
    }
    return { skip: 'the php container is not running' };
  }
  if (!missing(exec('docker', ['--version'], root))) return { skip: 'docker-compose is not installed' };
  if (!existsSync(join(root, 'API', 'vendor'))) return { skip: 'no docker and API/vendor is missing' };
  return { checks: [['PHPStan', 'php', PHPSTAN, 'API'], ['Deptrac', 'php', DEPTRAC, 'API']] };
}

function lines(text) {
  return text.split('\n').map((l) => l.trim()).filter(Boolean);
}

// Both git commands print paths relative to the cwd, so run them from the repo root.
function repoRoot(cwd) {
  const top = exec('git', ['rev-parse', '--show-toplevel'], cwd);
  return top.status === 0 ? lines(top.stdout)[0] ?? null : null;
}

function changedFiles(root) {
  const tracked = exec('git', ['diff', '--name-only', 'HEAD'], root);
  const untracked = exec('git', ['ls-files', '--others', '--exclude-standard'], root);
  if (tracked.status !== 0 || untracked.status !== 0) return null;
  return lines(`${tracked.stdout}\n${untracked.stdout}`);
}

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { pass(); }

// Second stop after a block. Claude already saw the failure, don't loop.
if (payload?.stop_hook_active) pass();

const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();
const root = repoRoot(cwd);
if (!root) pass();
const files = changedFiles(root);
if (!files?.length) pass();

const failures = [];
const skipped = [];
for (const area of AREAS) {
  if (!files.some((f) => f.startsWith(area.prefix))) continue;
  const { checks, skip } = area.plan(root);
  if (skip) { skipped.push(skip); continue; }
  for (const [label, cmd, args, sub] of checks) {
    const result = exec(cmd, args, sub ? join(root, sub) : root);
    if (unavailable(result)) { skipped.push(`${label} could not run`); continue; }
    if (result.status === 0) continue;
    const output = `${result.stdout ?? ''}\n${result.stderr ?? ''}`.trim();
    failures.push(`${label} failed:\n${output.slice(-OUTPUT_LIMIT)}`);
  }
}

if (!failures.length) pass(skipped);

process.stderr.write(
  `Checks failed on the uncommitted changes. Fix them before finishing, or say why ` +
  `the failure is not from this work.\n\n${failures.join('\n\n')}\n`,
);
process.exit(2);
