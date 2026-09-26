import { test, expect, type PlaywrightTestArgs } from '@playwright/test';
import {
  fillRequestForm,
  gotoSlotBrowser,
  selectFirstAvailableSlot,
  validRequestForm,
} from './fixtures/helpers';

test('happy path: browse → pick slot → submit request → confirmation', async ({
  page,
}: PlaywrightTestArgs): Promise<void> => {
  await gotoSlotBrowser(page);

  // First week with availability auto-loads; pick the first open slot.
  await selectFirstAvailableSlot(page);

  // The form shows the selected slot summary (modality pill).
  await expect(page.getByText(/Online|Presencial/).first()).toBeVisible();

  await fillRequestForm(page, validRequestForm());
  await page.getByRole('button', { name: 'Solicitar cita' }).click();

  // ThankYou screen.
  await expect(page.getByText('Solicitud recibida')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Reservar otra cita' })).toBeVisible();
});
