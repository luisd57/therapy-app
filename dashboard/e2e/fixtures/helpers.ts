import { expect, type APIRequestContext, type Page } from '@playwright/test';
import * as path from 'node:path';

// ── Configuration ────────────────────────────────────────────────────────

export const MAILHOG_URL: string = process.env['MAILHOG_URL'] ?? 'http://localhost:8025';
export const THERAPIST_EMAIL: string = process.env['THERAPIST_EMAIL'] ?? 'therapist@example.com';
export const THERAPIST_PASSWORD: string = process.env['THERAPIST_PASSWORD'] ?? 'VerifyPass1!';
export const PATIENT_PASSWORD: string = 'NewPatient1!';

/** Where globalSetup persists the therapist's logged-in cookies. */
export const THERAPIST_STORAGE_STATE: string = path.join(
  __dirname,
  '..',
  '.auth',
  'therapist.json',
);

// ── Helpers ──────────────────────────────────────────────────────────────

export function uniqueEmail(prefix: string): string {
  return `${prefix}+${Date.now()}-${Math.random().toString(36).slice(2, 7)}@e2e.test`;
}

export async function loginAsTherapist(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email' }).fill(THERAPIST_EMAIL);
  await page.getByRole('textbox', { name: 'Password' }).fill(THERAPIST_PASSWORD);
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/\/appointments$/);
}

export async function inviteFromDialog(page: Page, email: string, name: string): Promise<void> {
  await page.goto('/patients');
  await page.getByRole('button', { name: 'Invite Patient' }).click();
  const dialog = page.getByRole('dialog', { name: 'Invite Patient' });
  await dialog.getByRole('textbox', { name: 'Email' }).fill(email);
  await dialog.getByRole('textbox', { name: 'Patient name' }).fill(name);
  await dialog.getByRole('button', { name: 'Send invitation' }).click();
  await expect(page).toHaveURL(/\/patients\/invitations$/);
}

export async function registerPatient(page: Page, token: string, password: string): Promise<void> {
  await page.goto(`/register?token=${token}`);
  await page.getByRole('textbox', { name: 'Password', exact: true }).fill(password);
  await page.getByRole('textbox', { name: 'Confirm Password' }).fill(password);
  await page.getByRole('button', { name: 'Create Account' }).click();
  await expect(page.getByText('Registration Complete')).toBeVisible();
}

export async function loginAsPatient(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/patient-login');
  await page.getByRole('textbox', { name: 'Email' }).fill(email);
  await page.getByRole('textbox', { name: 'Password' }).fill(password);
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/\/appointments$/);
}

/**
 * Fill the login form at `route` ('/login' or '/patient-login') with credentials
 * expected to fail, submit, and assert the inline error surfaces. Does NOT wait
 * for a redirect to /appointments.
 */
export async function loginExpectingError(
  page: Page,
  route: string,
  email: string,
  password: string,
): Promise<void> {
  await page.goto(route);
  await page.getByRole('textbox', { name: 'Email' }).fill(email);
  await page.getByRole('textbox', { name: 'Password' }).fill(password);
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page.locator('.error-message')).toBeVisible();
}

// ── MailHog ──────────────────────────────────────────────────────────────

/** Decode quoted-printable as it appears in MailHog message bodies. */
function decodeQuotedPrintable(raw: string): string {
  return raw
    .replace(/=\r?\n/g, '')
    .replace(/=([0-9A-Fa-f]{2})/g, (_match: string, hex: string): string =>
      String.fromCharCode(parseInt(hex, 16)),
    );
}

export interface MailhogMessage {
  To: { Mailbox: string; Domain: string }[];
  Created: string;
  Content: { Body: string; Headers: { Subject?: string[] } };
}

export async function fetchAllMessagesFor(
  apiContext: APIRequestContext,
  email: string,
): Promise<MailhogMessage[]> {
  const response = await apiContext.get(`${MAILHOG_URL}/api/v2/messages`);
  if (!response.ok()) throw new Error(`MailHog GET failed: ${response.status()}`);
  const body = (await response.json()) as { items: MailhogMessage[] };
  return body.items.filter((message: MailhogMessage): boolean =>
    message.To.some((recipient): boolean => `${recipient.Mailbox}@${recipient.Domain}` === email),
  );
}

/**
 * Poll MailHog for the most recent message to `email` whose decoded body matches
 * `linkPatternRegex` (must capture the token in group 1). Retries briefly because
 * the email send is async after the API call returns.
 */
async function fetchLatestTokenMatching(
  apiContext: APIRequestContext,
  email: string,
  linkPatternRegex: RegExp,
): Promise<string> {
  for (let attempt = 0; attempt < 10; attempt++) {
    const matches = (await fetchAllMessagesFor(apiContext, email)).sort(
      (a: MailhogMessage, b: MailhogMessage): number =>
        new Date(b.Created).getTime() - new Date(a.Created).getTime(),
    );

    for (const message of matches) {
      const decoded = decodeQuotedPrintable(message.Content.Body);
      const tokenMatch = decoded.match(linkPatternRegex);
      if (tokenMatch) return tokenMatch[1]!;
    }
    await new Promise<void>((resolve): NodeJS.Timeout => setTimeout(resolve, 500));
  }
  throw new Error(`No email matching ${linkPatternRegex} found for ${email} after retries`);
}

export async function fetchLatestTokenFor(
  apiContext: APIRequestContext,
  email: string,
): Promise<string> {
  return fetchLatestTokenMatching(apiContext, email, /register\?token=([A-Za-z0-9_-]+)/);
}

export async function fetchLatestResetTokenFor(
  apiContext: APIRequestContext,
  email: string,
): Promise<string> {
  return fetchLatestTokenMatching(apiContext, email, /reset-password\?token=([A-Za-z0-9_-]+)/);
}
