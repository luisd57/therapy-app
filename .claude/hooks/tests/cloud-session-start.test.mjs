// Drives cloud-session-start.mjs like a SessionStart hook. Real git repos stand in for the stale
// cached checkout, and stub pg_isready, redis-cli and sudo on PATH stand in for the services.
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync, chmodSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, delimiter } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'cloud-session-start.mjs');
const git = (dir, ...args) => execFileSync(
  'git',
  ['-C', dir, '-c', 'user.name=t', '-c', 'user.email=t@example.com', '-c', 'core.autocrlf=false', ...args],
  { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
).trim();

// A remote with two commits and a clone left on the first one, like a restored snapshot.
function staleClone() {
  const root = mkdtempSync(join(tmpdir(), 'stale-'));
  const remote = join(root, 'remote.git');
  const work = join(root, 'work');
  const clone = join(root, 'clone');
  execFileSync('git', ['init', '-q', '--bare', '-b', 'feature', remote]);
  execFileSync('git', ['clone', '-q', '-c', 'core.autocrlf=false', remote, work], { stdio: 'pipe' });
  git(work, 'checkout', '-q', '-b', 'feature');
  writeFileSync(join(work, 'a.txt'), 'one\n');
  git(work, 'add', '-A');
  git(work, 'commit', '-q', '-m', 'one');
  git(work, 'push', '-q', 'origin', 'feature');
  execFileSync('git', ['clone', '-q', '-c', 'core.autocrlf=false', '-b', 'feature', remote, clone], { stdio: 'pipe' });
  writeFileSync(join(work, 'a.txt'), 'two\n');
  git(work, 'commit', '-q', '-am', 'two');
  git(work, 'push', '-q', 'origin', 'feature');
  return { clone, tip: git(work, 'rev-parse', 'HEAD'), work };
}

// Each stub reports up or down from env and logs every call.
function stubs() {
  const dir = mkdtempSync(join(tmpdir(), 'svc-stub-'));
  const sh = (name, body) => {
    writeFileSync(join(dir, name), `#!/usr/bin/env bash\necho "${name} $*" >> "$STUB_LOG"\n${body}\n`);
    chmodSync(join(dir, name), 0o755);
  };
  sh('pg_isready', '[ "$STUB_PG" = up ] || [ -f "$STUB_LOG.postgresql" ]');
  sh('redis-cli', '[ "$STUB_REDIS" = up ] || [ -f "$STUB_LOG.redis-server" ]');
  sh('sudo', '[ "$STUB_SUDO" = fail ] && exit 1; touch "$STUB_LOG.$2"');
  return dir;
}
const STUBS = stubs();

function run(cwd, env) {
  return new Promise((resolve) => {
    const clean = { ...process.env };
    delete clean.CLAUDE_CODE_REMOTE;
    const log = join(mkdtempSync(join(tmpdir(), 'svc-log-')), 'calls');
    writeFileSync(log, '');
    const child = spawn(process.execPath, [HOOK], {
      cwd,
      stdio: ['pipe', 'pipe', 'pipe'],
      env: { ...clean, PATH: `${STUBS}${delimiter}${process.env.PATH}`, STUB_LOG: log, STUB_PG: 'up', STUB_REDIS: 'up', ...env },
    });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', (code) => {
      let json = null;
      try { json = out.trim() ? JSON.parse(out) : null; } catch { json = { unparsed: out }; }
      resolve({ code, json, calls: readFileSync(log, 'utf8') });
    });
    child.stdin.end(JSON.stringify({ hook_event_name: 'SessionStart', source: env.SOURCE ?? 'startup', cwd }));
  });
}

const REMOTE = { CLAUDE_CODE_REMOTE: 'true' };
let bad = 0;
function check(name, ok, got) {
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${ok ? '' : `  ${JSON.stringify(got)}`}`);
}
const message = (got) => `${got.json?.systemMessage ?? ''} ${got.json?.hookSpecificOutput?.additionalContext ?? ''}`;

{
  const repo = staleClone();
  const got = await run(repo.clone, { STUB_PG: 'down', STUB_REDIS: 'down' });
  check('local session does nothing', got.code === 0 && got.json === null && got.calls === '' && git(repo.clone, 'rev-parse', 'HEAD') !== repo.tip, got);
}

{
  const repo = staleClone();
  const got = await run(repo.clone, REMOTE);
  check('stale clean checkout moves to the branch tip', got.code === 0 && git(repo.clone, 'rev-parse', 'HEAD') === repo.tip, got);
  check('the move is reported', /feature/.test(message(got)) && /behind|moved/i.test(message(got)), got);
}

{
  const repo = staleClone();
  const got = await run(repo.clone, { ...REMOTE, SOURCE: 'resume' });
  check('a resumed session also moves the checkout', git(repo.clone, 'rev-parse', 'HEAD') === repo.tip, got);
}

for (const source of ['compact', 'clear']) {
  const repo = staleClone();
  const got = await run(repo.clone, { ...REMOTE, SOURCE: source });
  check(`a ${source} mid-session never moves the checkout`, got.code === 0 && git(repo.clone, 'rev-parse', 'HEAD') !== repo.tip, got);
}

{
  const repo = staleClone();
  writeFileSync(join(repo.clone, 'a.txt'), 'local edit\n');
  const got = await run(repo.clone, REMOTE);
  check('local changes block the move and are reported', git(repo.clone, 'rev-parse', 'HEAD') !== repo.tip && readFileSync(join(repo.clone, 'a.txt'), 'utf8') === 'local edit\n' && /uncommitted|local changes/i.test(message(got)), got);
}

{
  const repo = staleClone();
  writeFileSync(join(repo.clone, 'b.txt'), 'mine\n');
  git(repo.clone, 'add', '-A');
  git(repo.clone, 'commit', '-q', '-m', 'local only');
  const mine = git(repo.clone, 'rev-parse', 'HEAD');
  const got = await run(repo.clone, REMOTE);
  check('a diverged branch is left alone and reported', git(repo.clone, 'rev-parse', 'HEAD') === mine && /diverged/i.test(message(got)), got);
}

{
  const repo = staleClone();
  git(repo.clone, 'merge', '-q', '--ff-only', 'origin/feature');
  git(repo.clone, 'fetch', '-q');
  git(repo.clone, 'merge', '-q', '--ff-only', 'origin/feature');
  const got = await run(repo.clone, REMOTE);
  check('an up-to-date checkout with services up is silent', got.code === 0 && got.json === null, got);
}

{
  const repo = staleClone();
  git(repo.clone, 'fetch', '-q');
  git(repo.clone, 'merge', '-q', '--ff-only', 'origin/feature');
  const got = await run(repo.clone, { ...REMOTE, STUB_PG: 'down', STUB_REDIS: 'down' });
  check('down services get started', /sudo service postgresql start/.test(got.calls) && /sudo service redis-server start/.test(got.calls), got);
  check('a started service is reported', /Postgres was down, started/.test(message(got)) && /Redis was down, started/.test(message(got)), got);
}

{
  const repo = staleClone();
  git(repo.clone, 'fetch', '-q');
  git(repo.clone, 'merge', '-q', '--ff-only', 'origin/feature');
  const got = await run(repo.clone, { ...REMOTE, STUB_PG: 'down', STUB_SUDO: 'fail' });
  check('a service that will not start is reported', got.code === 0 && /postgres/i.test(message(got)), got);
  check('a running service is not restarted', !/redis-server start/.test(got.calls), got);
}

{
  const dir = mkdtempSync(join(tmpdir(), 'no-repo-'));
  const got = await run(dir, REMOTE);
  check('outside a git repo it passes silently', got.code === 0 && got.json === null, got);
}

console.log(`\n${bad === 0 ? 'all pass' : `${bad} FAILED`}`);
process.exit(bad === 0 ? 0 : 1);
