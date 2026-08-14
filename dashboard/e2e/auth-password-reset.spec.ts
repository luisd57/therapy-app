import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import {
  PATIENT_PASSWORD,
  fetchLatestResetTokenFor,
  fetchLatestTokenFor,
  inviteFromDialog,
  loginAsPatient,
  registerPatient,
  uniqueEmail,
} from './fixtures/helpers';

const NEW_PASSWORD: string = 'ResetPass1!';

test.describe('Auth - password reset', (): void => {
  test('patient resets password via emailed link and logs in with the new one', async ({
    page,
    browser,
    request,
  }): Promise<void> => {
    // Create an active patient: therapist (storageState) invites, patient registers.
    const patientEmail: string = uniqueEmail('verify-reset');
    await inviteFromDialog(page, patientEmail, 'Reset Flow Patient');
    const inviteToken: string = await fetchLatestTokenFor(request, patientEmail);

    const registerCtx: BrowserContext = await browser.newContext({ storageState: undefined });
    const registerPage: Page = await registerCtx.newPage();
    await registerPatient(registerPage, inviteToken, PATIENT_PASSWORD);
    await registerCtx.close();

    // Request a reset - the page always shows the generic confirmation.
    const resetCtx: BrowserContext = await browser.newContext({ storageState: undefined });
    const resetPage: Page = await resetCtx.newPage();
    await resetPage.goto('/forgot-password');
    await resetPage.getByRole('textbox', { name: 'Email' }).fill(patientEmail);
    await resetPage.getByRole('button', { name: 'Send Reset Link' }).click();
    await expect(resetPage.getByText(/If an account with that email exists/i)).toBeVisible();

    // Follow the emailed reset link and set a new password.
    const resetToken: string = await fetchLatestResetTokenFor(request, patientEmail);
    await resetPage.goto(`/reset-password?token=${resetToken}`);
    await resetPage.getByRole('textbox', { name: 'New Password' }).fill(NEW_PASSWORD);
    await resetPage.getByRole('textbox', { name: 'Confirm Password' }).fill(NEW_PASSWORD);
    await resetPage.getByRole('button', { name: 'Reset Password' }).click();
    await expect(resetPage.getByText('Your password has been reset successfully.')).toBeVisible();

    // The new password works.
    await loginAsPatient(resetPage, patientEmail, NEW_PASSWORD);
    await resetCtx.close();
  });

  test('garbage reset token surfaces an error on submit', async ({ browser }): Promise<void> => {
    const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
    const page: Page = await ctx.newPage();

    // Token is only validated server-side on submit, so a valid password is
    // required first to enable the button.
    await page.goto('/reset-password?token=garbage-token-for-e2e-12345');
    await page.getByRole('textbox', { name: 'New Password' }).fill(NEW_PASSWORD);
    await page.getByRole('textbox', { name: 'Confirm Password' }).fill(NEW_PASSWORD);
    await page.getByRole('button', { name: 'Reset Password' }).click();
    await expect(page.locator('.error-message')).toBeVisible();

    await ctx.close();
  });
});
