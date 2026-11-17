// Drives branch-delete-guard.mjs the way Claude Code does: JSON on stdin, read the
// verdict. Runs against a real throwaway repo, so the content comparison is git's own.
import { spawn, spawnSync } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'branch-delete-guard.mjs');

function git(cwd, ...args) {
  const r = spawnSync('git', args, { cwd, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`git ${args.join(' ')}: ${r.stderr}`);
}

// main, plus "squashed" (same content as main, different history) and "ahead" (extra work).
function repo() {
  const dir = mkdtempSync(join(tmpdir(), 'branch-guard-'));
  git(dir, 'init', '-q', '-b', 'main');
  git(dir, 'config', 'user.email', 'test@example.com');
  git(dir, 'config', 'user.name', 'test');
  git(dir, 'config', 'commit.gpgsign', 'false');
  writeFileSync(join(dir, 'a.txt'), 'base\n');
  git(dir, 'add', '.');
  git(dir, 'commit', '-q', '-m', 'base');

  git(dir, 'checkout', '-q', '-b', 'squashed');
  writeFileSync(join(dir, 'a.txt'), 'feature\n');
  git(dir, 'commit', '-q', '-am', 'feature on branch');
  git(dir, 'checkout', '-q', 'main');
  writeFileSync(join(dir, 'a.txt'), 'feature\n');
  git(dir, 'commit', '-q', '-am', 'feature squashed into main');

  git(dir, 'checkout', '-q', '-b', 'ahead');
  writeFileSync(join(dir, 'b.txt'), 'unmerged\n');
  git(dir, 'add', '.');
  git(dir, 'commit', '-q', '-m', 'unmerged work');
  git(dir, 'checkout', '-q', 'main');
  return dir;
}

const REPO = repo();

function run(tool, command) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [HOOK], { stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', () => {
      if (!out.trim()) return resolve('ALLOW');
      try {
        resolve(JSON.parse(out).hookSpecificOutput.permissionDecision === 'deny' ? 'DENY' : 'ALLOW');
      } catch { resolve('ALLOW'); }
    });
    child.stdin.end(JSON.stringify({ tool_name: tool, cwd: REPO, tool_input: { command } }));
  });
}

const cases = [
  ['Bash', 'git branch -D squashed', 'ALLOW'],
  ['Bash', 'git branch -D ahead', 'DENY'],
  ['PowerShell', 'git branch -D squashed', 'ALLOW'],
  ['PowerShell', 'git branch -D ahead', 'DENY'],
  ['Bash', 'git branch --delete --force ahead', 'DENY'],
  ['Bash', 'git branch -d -f ahead', 'DENY'],
  ['Bash', 'git branch -df ahead', 'DENY'],
  ['Bash', 'git branch -D squashed ahead', 'DENY'],
  ['Bash', 'git branch -D main', 'DENY'],
  ['Bash', 'git branch -D no-such-branch', 'DENY'],
  ['Bash', 'git status && git branch -D ahead', 'DENY'],
  ['Bash', 'git branch -d ahead', 'ALLOW'],
  ['Bash', 'git branch --list', 'ALLOW'],
  ['Bash', 'echo "git branch -D ahead"', 'ALLOW'],
  ['Read', 'git branch -D ahead', 'ALLOW'],
];

let bad = 0;
for (const [tool, command, want] of cases) {
  const got = await run(tool, command);
  const ok = got === want;
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${tool}: ${command} -> ${got}${ok ? '' : `, want ${want}`}`);
}

console.log(`\n${cases.length - bad}/${cases.length} passed`);
process.exit(bad === 0 ? 0 : 1);
