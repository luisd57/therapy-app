#!/usr/bin/env node
// PreToolUse hook: allow a force branch delete only when the branch's content matches main.
// A squash merge leaves history that `-d` refuses, but identical content means nothing is lost.

import { spawnSync } from 'node:child_process';
import { commandSegments } from './lib/shell.mjs';

const BASE = 'main';

function read(stream) {
  return new Promise((resolve) => {
    const chunks = [];
    stream.on('data', (c) => chunks.push(c));
    stream.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    stream.on('error', () => resolve(''));
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

function tokens(segment) {
  return segment.split(/\s+/).filter(Boolean).map((t) => t.replace(/^(['"])(.*)\1$/, '$2'));
}

// The branch names a force delete would remove, or null when the segment is not one.
function forceDeleted(segment) {
  const words = tokens(segment);
  if (words[0] !== 'git' || words[1] !== 'branch') return null;
  const flags = words.slice(2).filter((w) => w.startsWith('-'));
  const short = flags.filter((f) => !f.startsWith('--')).join('');
  const isDelete = /[dD]/.test(short) || flags.includes('--delete');
  const isForce = /[Df]/.test(short) || flags.includes('--force');
  if (!isDelete || !isForce) return null;
  return words.slice(2).filter((w) => !w.startsWith('-'));
}

// Any exit but 0 blocks: 1 is a difference, 128 is an unknown branch.
function matchesBase(branch, cwd) {
  const r = spawnSync('git', ['diff', '--quiet', branch, BASE, '--'], { cwd });
  return r.status === 0;
}

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { allow(); }

if (payload?.tool_name !== 'Bash' && payload?.tool_name !== 'PowerShell') allow();

const command = payload?.tool_input?.command ?? '';
const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();

for (const segment of commandSegments(command)) {
  const branches = forceDeleted(segment);
  if (!branches) continue;
  for (const branch of branches) {
    if (branch === BASE) deny(`Refusing to force-delete ${BASE}.`);
    if (!matchesBase(branch, cwd)) {
      deny(
        `${branch} has content that ${BASE} does not, or does not exist. Force delete is only ` +
        `allowed once the branch matches ${BASE} (a squash merge that landed). Check ` +
        `\`git diff ${branch} ${BASE}\`, and pull ${BASE} first if the PR just merged.`,
      );
    }
  }
}

allow();
