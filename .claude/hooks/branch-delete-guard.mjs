#!/usr/bin/env node
// PreToolUse hook: allow a force branch delete only when merging the branch into main changes nothing.
// Replaces the `git branch -D` deny rule, so it fails closed on any force delete it can't verify.

import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { commandSegments } from './lib/shell.mjs';

const BASE = 'main';
const CD = new Set(['cd', 'pushd', 'chdir', 'set-location', 'sl']);
const GIT = /(^|[\\/])git(\.exe)?$/i;

function read(stream) {
  return new Promise((done) => {
    const chunks = [];
    stream.on('data', (c) => chunks.push(c));
    stream.on('end', () => done(Buffer.concat(chunks).toString('utf8')));
    stream.on('error', () => done(''));
  });
}

function allow() { process.exit(0); }

function deny(reason) {
  process.stdout.write(JSON.stringify({
    hookSpecificOutput: {
      hookEventName: 'PreToolUse',
      permissionDecision: 'deny',
      permissionDecisionReason: reason,
    },
  }));
  process.exit(0);
}

// Quotes are stripped per word, so `bash -c "git branch -D x"` still shows its words.
function words(segment) {
  return segment.split(/\s+/).filter(Boolean).map((w) => w.replace(/^['"]+|['"]+$/g, ''));
}

// git takes any unambiguous prefix of a long option, so `--dele --forc` is a force delete.
function longMatches(word, option, minLength) {
  return word.length >= minLength && option.startsWith(word.split('=')[0]);
}

function isForceDelete(args) {
  const flags = args.filter((w) => w.startsWith('-') && w !== '--');
  const short = flags.filter((f) => !f.startsWith('--')).join('');
  const long = flags.filter((f) => f.startsWith('--'));
  const isDelete = /[dD]/.test(short) || long.some((f) => longMatches(f, '--delete', 4));
  const isForce = /[Df]/.test(short) || long.some((f) => longMatches(f, '--force', 5));
  return isDelete && isForce;
}

function branchNames(args) {
  const end = args.indexOf('--');
  const names = end === -1 ? args.filter((w) => !w.startsWith('-')) : args.slice(end + 1);
  return names.filter(Boolean);
}

// The repo dir a plain `[&] git [-C dir] [-c k=v] [--no-pager] branch` runs in, or null for any other form.
function plainGitDir(prefix, cwd) {
  let i = prefix[0] === '&' ? 1 : 0;
  if (!GIT.test(prefix[i] ?? '')) return null;
  let dir = cwd;
  for (i += 1; i < prefix.length; i += 1) {
    if (prefix[i] === '-C' && prefix[i + 1]) { dir = resolve(dir, prefix[i + 1]); i += 1; }
    else if (prefix[i] === '-c' && prefix[i + 1]) i += 1;
    else if (prefix[i] !== '--no-pager') return null;
  }
  return dir;
}

function git(dir, args) {
  const r = spawnSync('git', args, { cwd: dir, encoding: 'utf8' });
  return r.status === 0 ? r.stdout.trim() : null;
}

// Merged when the merge result is main's own tree. Conflicts and unknown names exit non-zero.
function mergedIntoBase(dir, branch) {
  const merged = git(dir, ['merge-tree', '--write-tree', BASE, branch]);
  const base = git(dir, ['rev-parse', `${BASE}^{tree}`]);
  return merged !== null && base !== null && merged.split('\n')[0] === base;
}

function check(segment, cwd, afterCd) {
  const w = words(segment);
  const at = w.findIndex((word, i) => word.toLowerCase() === 'branch' && w.slice(0, i).some((p) => GIT.test(p)));
  if (at === -1) return;
  const args = w.slice(at + 1);
  if (!isForceDelete(args)) return;

  const dir = plainGitDir(w.slice(0, at), cwd);
  if (afterCd || dir === null) {
    deny('Force branch delete in a form this hook cannot verify. Run it on its own as ' +
      '`git branch -D <name>`, or `git -C <dir> branch -D <name>` for another repo.');
  }
  for (const branch of branchNames(args)) {
    if (branch.toLowerCase() === BASE) deny(`Refusing to force-delete ${BASE}.`);
    if (!mergedIntoBase(dir, branch)) {
      deny(`${branch} has work that ${BASE} does not, or does not exist. Force delete is only ` +
        `allowed once merging it into ${BASE} would change nothing. Check \`git diff ${BASE}...${branch}\`, ` +
        `and pull ${BASE} first if the PR just merged.`);
    }
  }
}

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { allow(); }

if (payload?.tool_name !== 'Bash' && payload?.tool_name !== 'PowerShell') allow();

const command = payload?.tool_input?.command ?? '';
const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();

try {
  let afterCd = false;
  for (const segment of commandSegments(command)) {
    check(segment, cwd, afterCd);
    if (CD.has((words(segment)[0] ?? '').toLowerCase())) afterCd = true;
  }
} catch {
  deny('The branch delete check failed, so the delete is refused. Run it on its own as `git branch -D <name>`.');
}

allow();
