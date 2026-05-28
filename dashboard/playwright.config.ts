import { defineConfig, devices } from '@playwright/test';
import * as path from 'node:path';

const DASHBOARD_URL: string = process.env['DASHBOARD_URL'] ?? 'http://127.0.0.1:4200';
const THERAPIST_STORAGE_STATE: string = path.join(__dirname, 'e2e', '.auth', 'therapist.json');

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env['CI'],
  retries: process.env['CI'] ? 1 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 60_000,
  expect: { timeout: 10_000 },

  globalSetup: require.resolve('./e2e/global-setup'),

  use: {
    baseURL: DASHBOARD_URL,
    // Re-use the therapist session globalSetup persisted. Tests that need a
    // fresh/patient session create their own context with storageState: undefined.
    storageState: THERAPIST_STORAGE_STATE,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
