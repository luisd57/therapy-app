import { test, expect } from '@playwright/test';
import {
  fillRequestForm,
  gotoSlotBrowser,
  selectFirstAvailableSlot,
  slotButtons,
  validRequestForm,
} from './fixtures/helpers';

test.describe('Reservation navigation', (): void => {
  test('"Cambiar horario" returns to the slot browser', async ({ page }): Promise<void> => {
    await gotoSlotBrowser(page);
    await selectFirstAvailableSlot(page);

    await page.getByRole('button', { name: /Cambiar horario/ }).click();

    // Back on the browser: slots are clickable again, the form is gone.
    await expect(slotButtons(page).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Solicitar cita' })).toHaveCount(0);
  });

  test('"Reservar otra cita" restarts the flow after a successful request', async ({
    page,
  }): Promise<void> => {
    await gotoSlotBrowser(page);
    await selectFirstAvailableSlot(page);
    await fillRequestForm(page, validRequestForm());
    await page.getByRole('button', { name: 'Solicitar cita' }).click();
    await expect(page.getByText('Solicitud recibida')).toBeVisible();

    await page.getByRole('button', { name: 'Reservar otra cita' }).click();

    // Back to a fresh browser step.
    await expect(slotButtons(page).first()).toBeVisible();
    await expect(page.getByText('Solicitud recibida')).toHaveCount(0);
  });
});
