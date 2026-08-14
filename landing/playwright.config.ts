import { defineConfig, devices } from '@playwright/test';

// The landing app is public/anonymous - no auth, no storageState.
// We run our OWN Astro dev server (webServer below) inside the e2e container so
// the page is served from a loopback origin the API's CORS allows
// (^https?://(localhost|127\.0\.0\.1)(:port)?$). The browser fetches the API at
// API_BASE_URL (http://nginx/api on the compose network); CORS keys off the page
// origin (127.0.0.1:4321), not the API host. PUBLIC_API_BASE_URL passed to the
// dev server overrides landing/.env (Vite prioritizes inline env vars).
const LANDING_URL: string = process.env['LANDING_URL'] ?? 'http://127.0.0.1:4321';
const API_BASE_URL: string = process.env['API_BASE_URL'] ?? 'http://localhost:8080/api';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env['CI'],
  retries: process.env['CI'] ? 1 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 60_000,
  expect: { timeout: 10_000 },

  // String path (not require.resolve): landing is an ESM package ("type":"module"),
  // so `require` is undefined at config load.
  globalSetup: './e2e/global-setup.ts',

  use: {
    baseURL: LANDING_URL,
    trace: 'on',
    screenshot: 'only-on-failure',
    video: 'on',
  },

  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 4321',
    url: LANDING_URL,
    reuseExistingServer: !process.env['CI'],
    timeout: 120_000,
    env: { PUBLIC_API_BASE_URL: API_BASE_URL },
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
