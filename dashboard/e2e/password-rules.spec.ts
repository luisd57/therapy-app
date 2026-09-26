import { test, expect, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { fetchLatestTokenFor, inviteFromDialog, uniqueEmail } from './fixtures/helpers';

// One message covers all six rules. The UI text starts with a dashed range, so match its tail.
const STRENGTH_MESSAGE: string = 'chars with uppercase, lowercase, number, and special character';

const MIN_LENGTH: number = 8;
const MAX_LENGTH: number = 72;

// Each input breaks exactly one rule, so the message appearing proves that rule fired.
const RULE_BREAKERS: { rule: string; password: string }[] = [
  { rule: 'minimum length', password: 'Aa1!' + 'a'.repeat(MIN_LENGTH - 5) },
  { rule: 'maximum length', password: 'Aa1!' + 'a'.repeat(MAX_LENGTH - 3) },
  { rule: 'uppercase letter', password: 'abcdef1!' },
  { rule: 'lowercase letter', password: 'ABCDEF1!' },
  { rule: 'digit', password: 'Abcdefg!' },
  { rule: 'special character', password: 'Abcdefg1' },
];

const VALID_PASSWORDS: { label: string; password: string }[] = [
  { label: 'a typical password', password: 'ValidPass1!' },
  { label: 'a password at the minimum length', password: 'Aa1!' + 'a'.repeat(MIN_LENGTH - 4) },
  { label: 'a password at the maximum length', password: 'Aa1!' + 'a'.repeat(MAX_LENGTH - 4) },
];

// fill() does not blur, and the errors only render once the control is touched.
async function fillAndBlur(field: Locator, value: string): Promise<void> {
  await field.fill(value);
  await field.blur();
}

// The reset form renders without calling the API, so it carries the per-rule sweep.
test.describe('Password rules - reset screen', (): void => {
  let resetCtx: BrowserContext;
  let resetPage: Page;

  test.beforeEach(async ({ browser }): Promise<void> => {
    resetCtx = await browser.newContext({ storageState: undefined });
    resetPage = await resetCtx.newPage();
    // The token is only checked on submit.
    await resetPage.goto('/reset-password?token=garbage-token-for-e2e-12345');
  });

  test.afterEach(async (): Promise<void> => {
    await resetCtx.close();
  });

  for (const { rule, password } of RULE_BREAKERS) {
    test(`rejects a password that breaks only the ${rule} rule`, async (): Promise<void> => {
      await fillAndBlur(resetPage.getByRole('textbox', { name: 'New Password' }), password);
      await expect(resetPage.getByText(STRENGTH_MESSAGE)).toBeVisible();
    });
  }

  for (const { label, password } of VALID_PASSWORDS) {
    test(`accepts ${label}`, async (): Promise<void> => {
      await fillAndBlur(resetPage.getByRole('textbox', { name: 'New Password' }), password);
      await fillAndBlur(resetPage.getByRole('textbox', { name: 'Confirm Password' }), password);
      await expect(resetPage.getByRole('button', { name: 'Reset Password' })).toBeEnabled();
      await expect(resetPage.getByText(STRENGTH_MESSAGE)).toBeHidden();
    });
  }
});

test.describe('Password rules - register screen', (): void => {
  test('applies the same rules as the reset screen', async ({
    page,
    browser,
    request,
  }): Promise<void> => {
    const patientEmail: string = uniqueEmail('verify-pw-rules');
    await inviteFromDialog(page, patientEmail, 'Password Rules');
    const token: string = await fetchLatestTokenFor(request, patientEmail);

    const patientCtx: BrowserContext = await browser.newContext({ storageState: undefined });
    const patientPage: Page = await patientCtx.newPage();
    await patientPage.goto(`/register?token=${token}`);
    const passwordField: Locator = patientPage.getByRole('textbox', { name: 'Password', exact: true });

    // One rule, not the full sweep. Enough to catch register dropping the shared validator.
    await fillAndBlur(passwordField, 'Abcdefg1');
    await expect(patientPage.getByText(STRENGTH_MESSAGE)).toBeVisible();

    await fillAndBlur(passwordField, 'ValidPass1!');
    await fillAndBlur(patientPage.getByRole('textbox', { name: 'Confirm Password' }), 'ValidPass1!');
    await expect(patientPage.getByRole('button', { name: 'Create Account' })).toBeEnabled();
    await expect(patientPage.getByText(STRENGTH_MESSAGE)).toBeHidden();

    await patientCtx.close();
  });
});
