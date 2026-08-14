import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import {
  PATIENT_PASSWORD,
  fetchLatestTokenFor,
  inviteFromDialog,
  registerPatient,
  uniqueEmail,
} from './fixtures/helpers';

test.describe('Patient invitation - error paths', (): void => {
  test('used token shows "already used"', async ({ page, browser, request }): Promise<void> => {
    // Therapist (default page, storageState-authenticated) sends an invitation.
    const patientEmail: string = uniqueEmail('verify-used-token');
    await inviteFromDialog(page, patientEmail, 'Used Token Test');

    const token: string = await fetchLatestTokenFor(request, patientEmail);

    // First consumption in a fresh context.
    const firstPatientCtx: BrowserContext = await browser.newContext({ storageState: undefined });
    const firstPatientPage: Page = await firstPatientCtx.newPage();
    await registerPatient(firstPatientPage, token, PATIENT_PASSWORD);
    await firstPatientCtx.close();

    // Second attempt with the same token → "Token has already been used."
    const reuseCtx: BrowserContext = await browser.newContext({ storageState: undefined });
    const reusePage: Page = await reuseCtx.newPage();
    await reusePage.goto(`/register?token=${token}`);
    await expect(reusePage.getByText('Invalid Invitation')).toBeVisible();
    await expect(reusePage.getByText(/already been used/i)).toBeVisible();
    await reuseCtx.close();
  });

  test('garbage token shows "Invalid token."', async ({ browser }): Promise<void> => {
    // No therapist needed - public route.
    const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
    const anonPage: Page = await ctx.newPage();
    await anonPage.goto('/register?token=garbage-token-for-e2e-12345');
    await expect(anonPage.getByText('Invalid Invitation')).toBeVisible();
    await expect(anonPage.getByText('Invalid token.')).toBeVisible();
    await ctx.close();
  });

  test('invalid email keeps Send invitation disabled', async ({ page }): Promise<void> => {
    // `page` is therapist-authenticated already.
    await page.goto('/patients');
    await page.getByRole('button', { name: 'Invite Patient' }).click();
    const dialog = page.getByRole('dialog', { name: 'Invite Patient' });
    await dialog.getByRole('textbox', { name: 'Email' }).fill('not-an-email');
    await dialog.getByRole('textbox', { name: 'Patient name' }).fill('Bad Email');
    await page.keyboard.press('Tab');
    await expect(dialog.getByText('Enter a valid email')).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Send invitation' })).toBeDisabled();
  });

  test('password mismatch keeps Create Account disabled', async ({
    page,
    browser,
    request,
  }): Promise<void> => {
    const patientEmail: string = uniqueEmail('verify-pw-mismatch');
    await inviteFromDialog(page, patientEmail, 'Password Mismatch');

    const token: string = await fetchLatestTokenFor(request, patientEmail);

    const patientCtx: BrowserContext = await browser.newContext({ storageState: undefined });
    const patientPage: Page = await patientCtx.newPage();
    await patientPage.goto(`/register?token=${token}`);
    await patientPage.getByRole('textbox', { name: 'Password', exact: true }).fill('Mismatch1!');
    await patientPage.getByRole('textbox', { name: 'Confirm Password' }).fill('Different1!');
    await patientPage.keyboard.press('Tab');
    await expect(patientPage.getByText('Passwords do not match')).toBeVisible();
    await expect(patientPage.getByRole('button', { name: 'Create Account' })).toBeDisabled();
    await patientCtx.close();
  });
});
