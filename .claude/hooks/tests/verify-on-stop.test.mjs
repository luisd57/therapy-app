// Drives verify-on-stop.mjs the way Claude Code does: JSON on stdin, read the exit
// code. Stub git, docker-compose and npm on PATH keep every case offline.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync, chmodSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, delimiter } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'verify-on-stop.mjs');
const WIN = process.platform === 'win32';

// One stub per tool. Each reads its behaviour from env vars set per case.
const IMPL = {
  git: `
const a = process.argv.slice(2).join(' ');
if (process.env.STUB_GIT === 'fail') process.exit(128);
if (a.startsWith('diff')) process.stdout.write(process.env.STUB_CHANGED ?? '');
process.exit(0);
`,
  'docker-compose': `
const a = process.argv.slice(2).join(' ');
if (process.env.STUB_DC === 'missing') process.exit(127);
if (a.startsWith('ps')) { process.stdout.write(process.env.STUB_DC === 'down' ? '' : 'abc123\\n'); process.exit(0); }
if (a.includes('phpstan') && process.env.STUB_PHPSTAN === 'fail') { process.stdout.write('Line 12: Undefined variable $x'); process.exit(1); }
if (a.includes('deptrac') && process.env.STUB_DEPTRAC === 'fail') { process.stdout.write('Domain must not depend on Infrastructure'); process.exit(1); }
process.exit(0);
`,
  npm: `
const a = process.argv.slice(2).join(' ');
if (a.includes('lint') && process.env.STUB_LINT === 'fail') { process.stdout.write('no-unused-vars in app.ts'); process.exit(1); }
if (a.includes('check:svelte') && process.env.STUB_SVELTE === 'fail') { process.stdout.write('Svelte warning in Slot.svelte'); process.exit(1); }
process.exit(0);
`,
};

function stubs() {
  const dir = mkdtempSync(join(tmpdir(), 'stub-verify-'));
  for (const [name, body] of Object.entries(IMPL)) {
    const js = join(dir, `${name}-impl.mjs`);
    writeFileSync(js, body, 'utf8');
    if (WIN) {
      writeFileSync(join(dir, `${name}.cmd`), `@echo off\r\nnode "${js}" %*\r\n`, 'utf8');
    } else {
      const sh = join(dir, name);
      writeFileSync(sh, `#!/bin/sh\nexec node "${js}" "$@"\n`, 'utf8');
      chmodSync(sh, 0o755);
    }
  }
  return dir;
}

// A project dir with node_modules in both front ends, unless a case removes them.
function project({ modules = true } = {}) {
  const dir = mkdtempSync(join(tmpdir(), 'verify-proj-'));
  for (const app of ['dashboard', 'landing']) {
    mkdirSync(join(dir, app, modules ? 'node_modules' : ''), { recursive: true });
  }
  return dir;
}

const STUB_DIR = stubs();

function run(payload, env = {}) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [HOOK], {
      stdio: ['pipe', 'pipe', 'pipe'],
      env: { ...process.env, PATH: `${STUB_DIR}${delimiter}${process.env.PATH}`, ...env },
    });
    let err = '';
    child.stderr.on('data', (c) => { err += c; });
    child.on('close', (code) => resolve({ code, err }));
    child.stdin.end(JSON.stringify(payload));
  });
}

const stop = (cwd, extra = {}) => ({ hook_event_name: 'Stop', cwd, stop_hook_active: false, ...extra });
const PROJ = project();
const BARE = project({ modules: false });

const cases = [
  ['no changes passes', stop(PROJ), {}, 0],
  ['docs-only change passes', stop(PROJ), { STUB_CHANGED: 'docs/STATUS.md\nCLAUDE.md\n' }, 0],
  ['clean API change passes', stop(PROJ), { STUB_CHANGED: 'API/src/Foo.php\n' }, 0],
  ['PHPStan failure blocks', stop(PROJ), { STUB_CHANGED: 'API/src/Foo.php\n', STUB_PHPSTAN: 'fail' }, 2, 'Undefined variable'],
  ['Deptrac failure blocks', stop(PROJ), { STUB_CHANGED: 'API/src/Foo.php\n', STUB_DEPTRAC: 'fail' }, 2, 'must not depend'],
  ['dashboard lint failure blocks', stop(PROJ), { STUB_CHANGED: 'dashboard/src/app.ts\n', STUB_LINT: 'fail' }, 2, 'no-unused-vars'],
  ['landing Svelte failure blocks', stop(PROJ), { STUB_CHANGED: 'landing/src/Slot.svelte\n', STUB_SVELTE: 'fail' }, 2, 'Slot.svelte'],
  ['API failure ignored when only landing changed', stop(PROJ), { STUB_CHANGED: 'landing/src/x.ts\n', STUB_PHPSTAN: 'fail' }, 0],
  ['second stop never blocks', stop(PROJ, { stop_hook_active: true }), { STUB_CHANGED: 'API/src/Foo.php\n', STUB_PHPSTAN: 'fail' }, 0],
  ['container down fails open', stop(PROJ), { STUB_CHANGED: 'API/src/Foo.php\n', STUB_PHPSTAN: 'fail', STUB_DC: 'down' }, 0],
  ['docker-compose missing fails open', stop(PROJ), { STUB_CHANGED: 'API/src/Foo.php\n', STUB_DC: 'missing' }, 0],
  ['no node_modules fails open', stop(BARE), { STUB_CHANGED: 'dashboard/src/app.ts\n', STUB_LINT: 'fail' }, 0],
  ['git failure fails open', stop(PROJ), { STUB_GIT: 'fail' }, 0],
];

let bad = 0;
for (const [name, payload, env, wantCode, errHas] of cases) {
  const got = await run(payload, env);
  const ok = got.code === wantCode && (!errHas || got.err.includes(errHas));
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name} -> exit ${got.code}${ok ? '' : `, want ${wantCode}${errHas ? ` with "${errHas}"` : ''}\n${got.err}`}`);
}

console.log(`\n${cases.length - bad}/${cases.length} passed`);
process.exit(bad === 0 ? 0 : 1);
