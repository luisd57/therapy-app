#!/usr/bin/env node
// Runs one of the user's global hooks (installed by cloud-user-config.mjs) in cloud sessions only.
// Locally the user settings already run it, so this exits at once to avoid running it twice.

import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

const name = process.argv[2] ?? '';
if (process.env.CLAUDE_CODE_REMOTE !== 'true' || !/^[\w.-]+\.mjs$/.test(name)) process.exit(0);

const script = join(homedir(), '.claude', 'hooks', name);
if (!existsSync(script)) process.exit(0);

const result = spawnSync(process.execPath, [script], { input: readFileSync(0) });
if (result.stdout) process.stdout.write(result.stdout);
if (result.stderr) process.stderr.write(result.stderr);
process.exit(result.status ?? 0);
