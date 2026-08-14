import { chromium, request, type APIRequestContext, type Browser } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';
import {
  MAILHOG_URL,
  THERAPIST_EMAIL,
  THERAPIST_PASSWORD,
  THERAPIST_STORAGE_STATE,
} from './fixtures/helpers';

const DASHBOARD_URL: string = process.env['DASHBOARD_URL'] ?? 'http://127.0.0.1:4200';
const READY_TIMEOUT_MS: number = 120_000;
const POLL_INTERVAL_MS: number = 2_000;

export default async function globalSetup(): Promise<void> {
  // 1. Wait for the Angular dev server to finish compiling.
  //    `depends_on: dashboard` in compose only waits for the container to
  //    start, not for the dev server to listen. First-time compile is ~10 s.
  await waitForUrl(DASHBOARD_URL, 'dashboard');

  // 2. Wait for MailHog (typically instant, but defensive).
  await waitForUrl(MAILHOG_URL, 'mailhog');

  // 3. Clear MailHog so tests start from an empty inbox.
  const mailhog = await request.newContext();
  try {
    const response = await mailhog.delete(`${MAILHOG_URL}/api/v1/messages`);
    if (!response.ok()) {
      throw new Error(`MailHog DELETE failed: ${response.status()}`);
    }
  } finally {
    await mailhog.dispose();
  }

  // 4. Login as therapist ONCE through the real UI and persist the full
  //    storageState (cookies + localStorage). Every test re-uses this via
  //    playwright.config.ts `use.storageState`. This:
  //      - avoids tripping the API's 5-login/min rate limiter (per IP)
  //      - captures localStorage `auth_user`, which the route guards check
  //        synchronously before the AuthService's `/api/auth/me` round-trip
  //        completes - without it, navigating to a protected route on a
  //        fresh page bounces to /login.
  const browser: Browser = await chromium.launch();
  const ctx = await browser.newContext({ baseURL: DASHBOARD_URL });
  const page = await ctx.newPage();
  try {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email' }).fill(THERAPIST_EMAIL);
    await page.getByRole('textbox', { name: 'Password' }).fill(THERAPIST_PASSWORD);
    await page.getByRole('button', { name: 'Log In' }).click();
    try {
      await page.waitForURL(/\/appointments$/, { timeout: 15_000 });
    } catch (error: unknown) {
      const message: string = error instanceof Error ? error.message : String(error);
      throw new Error(
        `Therapist login pre-check failed: ${message}. ` +
          `Set THERAPIST_EMAIL / THERAPIST_PASSWORD env vars to match the therapist ` +
          `seeded in your dev DB.`,
      );
    }

    await fs.promises.mkdir(path.dirname(THERAPIST_STORAGE_STATE), { recursive: true });
    await ctx.storageState({ path: THERAPIST_STORAGE_STATE });
  } finally {
    await page.close();
    await ctx.close();
    await browser.close();
  }
}

async function waitForUrl(url: string, label: string): Promise<void> {
  const context: APIRequestContext = await request.newContext();
  const deadline: number = Date.now() + READY_TIMEOUT_MS;
  try {
    let lastError: string = '';
    while (Date.now() < deadline) {
      try {
        const response = await context.get(url, { timeout: 5_000 });
        // Any HTTP response (even 404) means the server is listening.
        if (response.status() > 0) return;
      } catch (error: unknown) {
        lastError = error instanceof Error ? error.message : String(error);
      }
      await new Promise<void>((resolve): NodeJS.Timeout => setTimeout(resolve, POLL_INTERVAL_MS));
    }
    throw new Error(
      `Timed out after ${READY_TIMEOUT_MS}ms waiting for ${label} at ${url}. Last error: ${lastError}`,
    );
  } finally {
    await context.dispose();
  }
}
