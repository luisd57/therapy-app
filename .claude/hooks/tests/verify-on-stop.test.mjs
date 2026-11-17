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
if (a.startsWith('rev-parse --show-toplevel')) { process.stdout.write(process.env.STUB_TOPLEVEL + '\\n'); process.exit(0); }
if (a === 'diff --name-only HEAD') process.stdout.write(process.env.STUB_CHANGED ?? '');
if (a === 'ls-files --others --exclude-standard') process.stdout.write(process.env.STUB_UNTRACKED ?? '');
process.exit(0);
`,
  'docker-compose': `
const a = process.argv.slice(2).join(' ');
if (process.env.STUB_DC === 'missing') process.exit(127);
if (a === 'ps --status running -q php') { process.stdout.write(process.env.STUB_DC === 'down' ? '' : 'abc123\\n'); process.exit(0); }
if (!a.startsWith('exec -T php ')) { process.stderr.write('the input device is not a TTY'); process.exit(1); }
if (a.includes('phpstan') && process.env.STUB_PHPSTAN === 'fail') { process.stdout.write('Line 12: Undefined variable $x'); process.exit(1); }
if (a.includes('deptrac') && process.env.STUB_DEPTRAC === 'fail') { process.stderr.write('Domain must not depend on Infrastructure'); process.exit(1); }
process.exit(0);
`,
  npm: `
const a = process.argv.slice(2).join(' ');
if (process.env.STUB_NPM === 'missing') process.exit(127);
if (a === 'run lint' && process.env.STUB_LINT === 'fail') { process.stdout.write('no-unused-vars in app.ts'); process.exit(1); }
if (a === 'run check' && process.env.STUB_CHECK === 'fail') { process.stdout.write('Type error in index.astro'); process.exit(1); }
if (a === 'run check:svelte' && process.env.STUB_SVELTE === 'fail') { process.stdout.write('Svelte warning in Slot.svelte'); process.exit(1); }
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
  mkdirSync(join(dir, 'API'), { recursive: true });
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
      env: {
        ...process.env,
        PATH: `${STUB_DIR}${delimiter}${process.env.PATH}`,
        STUB_TOPLEVEL: payload.root ?? payload.cwd,
        ...env,
      },
    });
    let out = '';
    let err = '';
    child.stdout.on('data', (c) => { out += c; });
    child.stderr.on('data', (c) => { err += c; });
    child.on('close', (code) => resolve({ code, out, err }));
    child.stdin.end(raw ?? JSON.stringify(payload));
  });
}

let raw = null;
const stop = (cwd, extra = {}) => ({ hook_event_name: 'Stop', cwd, stop_hook_active: false, ...extra });
const PROJ = project();
const BARE = project({ modules: false });
const API = 'API/src/Foo.php\n';

// [name, payload, env, exit code, stderr must include, stdout must include]
const cases = [
  ['no changes passes', stop(PROJ), {}, 0],
  ['docs-only change passes', stop(PROJ), { STUB_CHANGED: 'docs/STATUS.md\nCLAUDE.md\n' }, 0],
  ['clean API change passes', stop(PROJ), { STUB_CHANGED: API }, 0],
  ['PHPStan failure blocks', stop(PROJ), { STUB_CHANGED: API, STUB_PHPSTAN: 'fail' }, 2, 'Undefined variable'],
  ['Deptrac failure on stderr blocks', stop(PROJ), { STUB_CHANGED: API, STUB_DEPTRAC: 'fail' }, 2, 'must not depend'],
  ['both API failures reported', stop(PROJ), { STUB_CHANGED: API, STUB_PHPSTAN: 'fail', STUB_DEPTRAC: 'fail' }, 2, 'must not depend', null, 'Undefined variable'],
  ['untracked file counts', stop(PROJ), { STUB_UNTRACKED: 'API/src/New.php\n', STUB_PHPSTAN: 'fail' }, 2, 'Undefined variable'],
  ['dashboard lint failure blocks', stop(PROJ), { STUB_CHANGED: 'dashboard/src/app.ts\n', STUB_LINT: 'fail' }, 2, 'no-unused-vars'],
  ['landing check failure blocks', stop(PROJ), { STUB_CHANGED: 'landing/src/index.astro\n', STUB_CHECK: 'fail' }, 2, 'index.astro'],
  ['landing Svelte failure blocks', stop(PROJ), { STUB_CHANGED: 'landing/src/Slot.svelte\n', STUB_SVELTE: 'fail' }, 2, 'Slot.svelte'],
  ['API failure ignored when only landing changed', stop(PROJ), { STUB_CHANGED: 'landing/src/x.ts\n', STUB_PHPSTAN: 'fail' }, 0],
  ['session in a subdirectory still checks', stop(join(PROJ, 'API'), { root: PROJ }), { STUB_CHANGED: 'dashboard/src/app.ts\n', STUB_LINT: 'fail' }, 2, 'no-unused-vars'],
  ['second stop never blocks', stop(PROJ, { stop_hook_active: true }), { STUB_CHANGED: API, STUB_PHPSTAN: 'fail' }, 0],
  ['container down fails open with a note', stop(PROJ), { STUB_CHANGED: API, STUB_PHPSTAN: 'fail', STUB_DC: 'down' }, 0, null, 'php container'],
  ['docker-compose missing fails open', stop(PROJ), { STUB_CHANGED: API, STUB_DC: 'missing' }, 0, null, 'skipped'],
  ['npm missing fails open', stop(PROJ), { STUB_CHANGED: 'dashboard/src/app.ts\n', STUB_NPM: 'missing' }, 0, null, 'skipped'],
  ['no dashboard node_modules fails open', stop(BARE), { STUB_CHANGED: 'dashboard/src/app.ts\n', STUB_LINT: 'fail' }, 0, null, 'node_modules'],
  ['no landing node_modules fails open', stop(BARE), { STUB_CHANGED: 'landing/src/x.ts\n', STUB_CHECK: 'fail' }, 0, null, 'node_modules'],
  ['git failure fails open', stop(PROJ), { STUB_GIT: 'fail' }, 0],
];

let bad = 0;
async function check(name, payload, env, wantCode, errHas, outHas, errHas2) {
  const got = await run(payload, env);
  const ok = got.code === wantCode
    && (!errHas || got.err.includes(errHas))
    && (!errHas2 || got.err.includes(errHas2))
    && (!outHas || got.out.includes(outHas));
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name} -> exit ${got.code}${ok ? '' : `, want ${wantCode}\nstdout: ${got.out}\nstderr: ${got.err}`}`);
}

for (const [name, payload, env, code, errHas, outHas, errHas2] of cases) {
  await check(name, payload, env, code, errHas, outHas, errHas2);
}

raw = 'not json';
await check('bad input fails open', stop(PROJ), { STUB_CHANGED: API, STUB_PHPSTAN: 'fail' }, 0);

const total = cases.length + 1;
console.log(`\n${total - bad}/${total} passed`);
process.exit(bad === 0 ? 0 : 1);
