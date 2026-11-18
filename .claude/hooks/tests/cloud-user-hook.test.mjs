// Drives cloud-user-hook.mjs the way Claude Code does: JSON on stdin, read the exit code and output.
import { spawn } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'cloud-user-hook.mjs');

// A home whose installed global hooks echo stdin or block.
const HOME = mkdtempSync(join(tmpdir(), 'hook-home-'));
const hooks = join(HOME, '.claude', 'hooks');
mkdirSync(hooks, { recursive: true });
writeFileSync(join(hooks, 'echo.mjs'), `
const chunks = []; process.stdin.on('data', (c) => chunks.push(c));
process.stdin.on('end', () => { process.stdout.write('got:' + Buffer.concat(chunks).toString()); });
`);
writeFileSync(join(hooks, 'block.mjs'), `process.stderr.write('blocked by global hook'); process.exit(2);`);
writeFileSync(join(HOME, 'outside.mjs'), `process.exit(3);`);

function run(args, env) {
  return new Promise((resolve) => {
    const clean = { ...process.env };
    delete clean.CLAUDE_CODE_REMOTE;
    const child = spawn(process.execPath, [HOOK, ...args], {
      stdio: ['pipe', 'pipe', 'pipe'],
      env: { ...clean, HOME, USERPROFILE: HOME, ...env },
    });
    let out = '';
    let err = '';
    child.stdout.on('data', (c) => { out += c; });
    child.stderr.on('data', (c) => { err += c; });
    child.on('close', (code) => resolve({ code, out, err }));
    child.stdin.end('{"tool_name":"Write"}');
  });
}

const REMOTE = { CLAUDE_CODE_REMOTE: 'true' };
let bad = 0;
function check(name, ok, got) {
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${ok ? '' : `  ${JSON.stringify(got)}`}`);
}

let got = await run(['block.mjs'], {});
check('local session never runs the global hook', got.code === 0 && got.err === '', got);

got = await run(['block.mjs'], REMOTE);
check('cloud passes on a block with its exit code and message', got.code === 2 && got.err.includes('blocked by global hook'), got);

got = await run(['echo.mjs'], REMOTE);
check('cloud forwards stdin and stdout', got.code === 0 && got.out === 'got:{"tool_name":"Write"}', got);

got = await run(['missing.mjs'], REMOTE);
check('cloud with the hook not installed passes', got.code === 0 && got.out === '' && got.err === '', got);

got = await run(['../outside.mjs'], REMOTE);
check('a path outside ~/.claude/hooks is refused', got.code === 0, got);

got = await run([], REMOTE);
check('no hook name passes', got.code === 0, got);

console.log(`\n${bad === 0 ? 'all pass' : `${bad} FAILED`}`);
process.exit(bad === 0 ? 0 : 1);
