import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import {
  THERAPIST_EMAIL,
  loginAsTherapist,
  loginExpectingError,
} from './fixtures/helpers';

// Therapist and patient login share the same component/flow, so login behavior
// is exercised with one role (therapist). Patient credentials are still exercised
// once in auth-password-reset.spec.ts. Keeping login attempts low also stays under
// the API's 5-login/min/IP rate limit (the Playwright container has one IP).
test.describe('Auth - login', (): void => {
  test('therapist logs in and lands on /appointments', async ({ browser }): Promise<void> => {
    const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
    const page: Page = await ctx.newPage();
    await loginAsTherapist(page);
    await ctx.close();
  });

  test('bad credentials show an inline error', async ({ browser }): Promise<void> => {
    const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
    const page: Page = await ctx.newPage();
    await loginExpectingError(page, '/login', THERAPIST_EMAIL, 'WrongPass1!');
    await expect(page).toHaveURL(/\/login$/);
    await ctx.close();
  });

  test('form validation blocks submit until valid', async ({ browser }): Promise<void> => {
    const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
    const page: Page = await ctx.newPage();
    await page.goto('/login');

    // Touch then blur both fields to surface the required-field errors.
    await page.getByRole('textbox', { name: 'Email' }).focus();
    await page.getByRole('textbox', { name: 'Email' }).blur();
    await page.getByRole('textbox', { name: 'Password' }).focus();
    await page.getByRole('textbox', { name: 'Password' }).blur();
    await expect(page.getByText('Email is required')).toBeVisible();
    await expect(page.getByText('Password is required')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Log In' })).toBeDisabled();

    // Invalid email format.
    await page.getByRole('textbox', { name: 'Email' }).fill('not-an-email');
    await page.getByRole('textbox', { name: 'Email' }).blur();
    await expect(page.getByText('Enter a valid email')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Log In' })).toBeDisabled();

    await ctx.close();
  });
});
