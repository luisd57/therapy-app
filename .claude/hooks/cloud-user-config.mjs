#!/usr/bin/env node
// SessionStart hook, cloud sessions only: installs the private claude-config repo into ~/.claude and
// returns its CLAUDE.md and output style as context. The setup script cannot see CLAUDE_CONFIG_TOKEN.

import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { join } from 'node:path';

const REPO = process.env.CLAUDE_CONFIG_REPO || 'https://github.com/luisd57/claude-config.git';
const STYLE = 'terse-engineer';

function finish(output) {
  if (output) process.stdout.write(JSON.stringify(output));
  process.exit(0);
}

// A missing config must never block the session, so every failure is a visible note.
const skip = (why) => finish({ systemMessage: `Global config not loaded: ${why}.` });

if (process.env.CLAUDE_CODE_REMOTE !== 'true') finish();
const token = process.env.CLAUDE_CONFIG_TOKEN;
if (!token) skip('CLAUDE_CONFIG_TOKEN is not set in the environment');

// The header goes through env for this one command: not in argv (ps), a URL, or a git config file.
const tmp = mkdtempSync(join(tmpdir(), 'claude-config-'));
process.on('exit', () => rmSync(tmp, { recursive: true, force: true }));
const src = join(tmp, 'repo');
const auth = Buffer.from(`x-access-token:${token}`).toString('base64');
const clone = spawnSync('git', ['clone', '-q', '--depth', '1', REPO, src], {
  encoding: 'utf8',
  timeout: 60_000,
  env: {
    ...process.env,
    GIT_CONFIG_COUNT: '1',
    GIT_CONFIG_KEY_0: 'http.extraHeader',
    GIT_CONFIG_VALUE_0: `Authorization: Basic ${auth}`,
  },
});
if (clone.status !== 0) {
  const reason = `${clone.stderr ?? ''}${clone.error?.message ?? ''}`.trim().split('\n').pop() || 'no output';
  skip(`cloning claude-config failed (${reason.replaceAll(token, '***').replaceAll(auth, '***')})`);
}

const dest = join(homedir(), '.claude');
try {
  cpSync(join(src, 'CLAUDE.md'), join(dest, 'CLAUDE.md'));
  for (const dir of ['hooks', 'output-styles', 'skills']) {
    if (existsSync(join(src, dir))) cpSync(join(src, dir), join(dest, dir), { recursive: true });
  }
} catch (error) {
  skip(`copying it into ~/.claude failed (${error.message})`);
}

// Claude Code read ~/.claude before this ran, so the rules reach this session as context.
const rules = readFileSync(join(src, 'CLAUDE.md'), 'utf8');
const stylePath = join(src, 'output-styles', `${STYLE}.md`);
const style = existsSync(stylePath) ? readFileSync(stylePath, 'utf8').replace(/^---[\s\S]*?---\s*/, '') : '';
finish({
  hookSpecificOutput: {
    hookEventName: 'SessionStart',
    additionalContext: [
      "The user's global instructions (their ~/.claude/CLAUDE.md, from the claude-config repo). Follow them like a CLAUDE.md.",
      rules,
      ...(style ? [`The user's output style, ${STYLE}. Write in it.`, style] : []),
    ].join('\n\n'),
  },
});
