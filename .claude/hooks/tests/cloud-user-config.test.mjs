// Drives cloud-user-config.mjs like a SessionStart hook. A real local git repo stands in for
// luisd57/claude-config through CLAUDE_CONFIG_REPO, so every clone here is a real one.
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'cloud-user-config.mjs');
const TOKEN = 'tok_SECRET_123';

function configRepo() {
  const dir = mkdtempSync(join(tmpdir(), 'cfg-repo-'));
  const files = {
    'CLAUDE.md': '# Global Rules\nNo em dashes.\n',
    'output-styles/terse-engineer.md': '---\nname: terse-engineer\ndescription: short\n---\n\n# Output Style\nTerse.\n',
    'hooks/scrub-check.mjs': 'process.exit(0);\n',
    'skills/strip-hidden-chars/SKILL.md': 'skill\n',
  };
  for (const [path, body] of Object.entries(files)) {
    mkdirSync(join(dir, path, '..'), { recursive: true });
    writeFileSync(join(dir, path), body);
  }
  const git = (...args) => execFileSync('git', ['-C', dir, ...args], { stdio: 'pipe' });
  git('init', '-q');
  git('add', '-A');
  git('-c', 'user.name=t', '-c', 'user.email=t@example.com', 'commit', '-q', '-m', 'config');
  return dir;
}

const REPO = configRepo();

function home(settings) {
  const dir = mkdtempSync(join(tmpdir(), 'cfg-home-'));
  mkdirSync(join(dir, '.claude'));
  if (settings) writeFileSync(join(dir, '.claude', 'settings.json'), settings);
  return dir;
}

function run(env) {
  return new Promise((resolve) => {
    const clean = { ...process.env };
    delete clean.CLAUDE_CODE_REMOTE;
    delete clean.CLAUDE_CONFIG_TOKEN;
    const child = spawn(process.execPath, [HOOK], { stdio: ['pipe', 'pipe', 'pipe'], env: { ...clean, ...env } });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', (code) => {
      let json = null;
      try { json = out.trim() ? JSON.parse(out) : null; } catch { json = { unparsed: out }; }
      resolve({ code, json });
    });
    child.stdin.end(JSON.stringify({ hook_event_name: 'SessionStart', source: 'startup' }));
  });
}

const cloud = (dir, extra = {}) => ({
  HOME: dir, USERPROFILE: dir, CLAUDE_CODE_REMOTE: 'true', CLAUDE_CONFIG_TOKEN: TOKEN, CLAUDE_CONFIG_REPO: REPO, ...extra,
});

let bad = 0;
function check(name, ok, detail = '') {
  if (!ok) bad += 1;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${ok ? '' : `  ${detail}`}`);
}

{
  const dir = home();
  const got = await run({ HOME: dir, USERPROFILE: dir, CLAUDE_CONFIG_TOKEN: TOKEN, CLAUDE_CONFIG_REPO: REPO });
  check('local session does nothing', got.code === 0 && got.json === null && !existsSync(join(dir, '.claude', 'CLAUDE.md')), JSON.stringify(got));
}

{
  const dir = home();
  const got = await run({ HOME: dir, USERPROFILE: dir, CLAUDE_CODE_REMOTE: 'true', CLAUDE_CONFIG_REPO: REPO });
  check('cloud without a token warns', got.code === 0 && /CLAUDE_CONFIG_TOKEN/.test(got.json?.systemMessage ?? ''), JSON.stringify(got));
}

{
  const dir = home('{"enabledPlugins":{"x@y":true}}\n');
  const got = await run(cloud(dir));
  const context = got.json?.hookSpecificOutput?.additionalContext ?? '';
  check('cloud returns the rules as context', got.code === 0 && got.json?.hookSpecificOutput?.hookEventName === 'SessionStart' && context.includes('No em dashes.'), JSON.stringify(got));
  check('context carries the output style without its frontmatter', context.includes('Terse.') && !context.includes('description: short'), context);
  check('rules, hooks and skills land in ~/.claude', ['CLAUDE.md', 'hooks/scrub-check.mjs', 'skills/strip-hidden-chars/SKILL.md', 'output-styles/terse-engineer.md']
    .every((p) => existsSync(join(dir, '.claude', p))));
  check('settings.json is left alone', readFileSync(join(dir, '.claude', 'settings.json'), 'utf8') === '{"enabledPlugins":{"x@y":true}}\n');
  const configs = readdirSync(tmpdir())
    .filter((d) => d.startsWith('claude-config-'))
    .map((d) => join(tmpdir(), d, 'repo', '.git', 'config'))
    .filter((p) => existsSync(p));
  check('no clone keeps the token in its git config', configs.length > 0 && configs.every((p) => !readFileSync(p, 'utf8').includes(Buffer.from(`x-access-token:${TOKEN}`).toString('base64')) && !readFileSync(p, 'utf8').includes(TOKEN)), configs.join(', '));
}

{
  const dir = home();
  const got = await run(cloud(dir, { CLAUDE_CONFIG_REPO: join(tmpdir(), 'no-such-config-repo') }));
  check('failed clone warns and does not block', got.code === 0 && /clon/i.test(got.json?.systemMessage ?? '') && !existsSync(join(dir, '.claude', 'CLAUDE.md')), JSON.stringify(got));
  check('the warning never shows the token', !JSON.stringify(got).includes(TOKEN));
}

console.log(`\n${bad === 0 ? 'all pass' : `${bad} FAILED`}`);
process.exit(bad === 0 ? 0 : 1);
