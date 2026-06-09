import { test, expect, type Locator } from '@playwright/test';
import {
  fillRequestForm,
  gotoSlotBrowser,
  selectFirstAvailableSlot,
  validRequestForm,
} from './fixtures/helpers';

// The form fields use native `required` / `type="email"`, so the browser blocks
// submission of empty or malformed input before any API call. We assert that
// real client behavior rather than server-side field errors.
test.describe('Reservation form — validation', (): void => {
  test('empty required fields block submission', async ({ page }): Promise<void> => {
    await gotoSlotBrowser(page);
    await selectFirstAvailableSlot(page);

    await page.getByRole('button', { name: 'Solicitar cita' }).click();

    // Still on the form (no confirmation), and the first field is invalid.
    await expect(page.getByText('Solicitud recibida')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Solicitar cita' })).toBeVisible();
    const fullName: Locator = page.locator('#fullName');
    expect(
      await fullName.evaluate((el: HTMLInputElement): boolean => el.validity.valueMissing),
    ).toBe(true);
  });

  test('malformed email is rejected by the browser', async ({ page }): Promise<void> => {
    await gotoSlotBrowser(page);
    await selectFirstAvailableSlot(page);

    await fillRequestForm(page, { ...validRequestForm(), email: 'not-an-email' });
    await page.getByRole('button', { name: 'Solicitar cita' }).click();

    await expect(page.getByText('Solicitud recibida')).toHaveCount(0);
    const email: Locator = page.locator('#email');
    expect(
      await email.evaluate((el: HTMLInputElement): boolean => el.validity.typeMismatch),
    ).toBe(true);
  });
});
