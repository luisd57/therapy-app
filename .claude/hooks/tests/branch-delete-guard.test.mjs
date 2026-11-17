// Drives branch-delete-guard.mjs the way Claude Code does: JSON on stdin, read the
// verdict. Runs against a real throwaway repo, so the content comparison is git's own.
import { spawn, spawnSync } from 'node:child_process';
import { writeFileSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'branch-delete-guard.mjs');

function git(cwd, ...args) {
  const r = spawnSync('git', args, { cwd, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`git ${args.join(' ')}: ${r.stderr}`);
}

// main, plus "squashed" (merged as a squash, then main moved on), "ahead" (extra work), and
// "conflict" (edits a.txt differently from main).
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

  // Another PR lands after the squash, so main no longer has the branch's exact tree.
  writeFileSync(join(dir, 'c.txt'), 'other pr\n');
  git(dir, 'add', '.');
  git(dir, 'commit', '-q', '-m', 'another pr');

  git(dir, 'checkout', '-q', '-b', 'ahead');
  writeFileSync(join(dir, 'b.txt'), 'unmerged\n');
  git(dir, 'add', '.');
  git(dir, 'commit', '-q', '-m', 'unmerged work');
  git(dir, 'checkout', '-q', 'main');

  git(dir, 'checkout', '-q', '-b', 'conflict');
  writeFileSync(join(dir, 'a.txt'), 'something else\n');
  git(dir, 'commit', '-q', '-am', 'conflicting work');
  git(dir, 'checkout', '-q', 'main');
  mkdirSync(join(dir, 'sub'));
  return dir;
}

const REPO = repo();
// A second repo where "ahead" is fully merged, to catch checks run against the wrong repo.
const OTHER = repo();
git(OTHER, 'merge', '-q', '--no-edit', 'ahead');

function run(tool, command, cwd = REPO) {
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
    child.stdin.end(raw ?? JSON.stringify({ tool_name: tool, cwd, tool_input: { command } }));
  });
}

let raw = null;

// [tool, command, verdict, cwd]
const cases = [
  ['Bash', 'git branch -D squashed', 'ALLOW'],
  ['Bash', 'git branch -D ahead', 'DENY'],
  ['Bash', 'git branch -D conflict', 'DENY'],
  ['PowerShell', 'git branch -D squashed', 'ALLOW'],
  ['PowerShell', 'git branch -D ahead', 'DENY'],
  ['Bash', 'git branch --delete --force ahead', 'DENY'],
  ['Bash', 'git branch --dele --forc ahead', 'DENY'],
  ['Bash', 'git branch --force -d ahead', 'DENY'],
  ['Bash', 'git branch -d -f ahead', 'DENY'],
  ['Bash', 'git branch -df ahead', 'DENY'],
  ['Bash', 'git branch -D -- ahead', 'DENY'],
  ['Bash', 'git branch -D squashed ahead', 'DENY'],
  ['Bash', 'git branch -D main', 'DENY'],
  ['Bash', 'git branch -D MAIN', 'DENY'],
  ['Bash', 'git branch -D no-such-branch', 'DENY'],
  ['Bash', 'git status && git branch -D ahead', 'DENY'],
  ['Bash', 'git -C . branch -D ahead', 'DENY'],
  ['Bash', `git -C ${OTHER} branch -D ahead`, 'ALLOW'],
  ['Bash', 'git -c core.pager=cat branch -D ahead', 'DENY'],
  ['Bash', 'LC_ALL=C git branch -D ahead', 'DENY'],
  ['Bash', 'env git branch -D ahead', 'DENY'],
  ['Bash', '/usr/bin/git branch -D ahead', 'DENY'],
  ['Bash', 'bash -c "git branch -D squashed"', 'DENY'],
  ['Bash', 'echo ahead | xargs git branch -D', 'DENY'],
  ['Bash', `cd ${OTHER} && git branch -D ahead`, 'DENY'],
  ['PowerShell', 'Git branch -D ahead', 'DENY'],
  ['PowerShell', 'git.exe branch -D ahead', 'DENY'],
  ['PowerShell', '& git branch -D ahead', 'DENY'],
  ['PowerShell', '& git branch -D squashed', 'ALLOW'],
  ['Bash', 'git branch -D squashed', 'ALLOW', join(REPO, 'sub')],
  ['Bash', 'git branch -d ahead', 'ALLOW'],
  ['Bash', 'git branch -f ahead main', 'ALLOW'],
  ['Bash', 'git branch --list --format=%(refname)', 'ALLOW'],
  ['Bash', 'git branch --list', 'ALLOW'],
  // A quoted mention reads the same as `bash -c "..."`, so it is refused too.
  ['Bash', 'echo "git branch -D ahead"', 'DENY'],
  ['Bash', 'echo "git branch -d ahead"', 'ALLOW'],
  ['Read', 'git branch -D ahead', 'ALLOW'],
];

let bad = 0;
async function check(label, tool, command, want, cwd) {
  const got = await run(tool, command, cwd);
  const ok = got === want;
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${label} -> ${got}${ok ? '' : `, want ${want}`}`);
}

for (const [tool, command, want, cwd] of cases) {
  await check(`${tool}: ${command}`, tool, command, want, cwd);
}

raw = 'not json';
await check('bad input allows', 'Bash', '', 'ALLOW');

const total = cases.length + 1;
console.log(`\n${total - bad}/${total} passed`);
process.exit(bad === 0 ? 0 : 1);
