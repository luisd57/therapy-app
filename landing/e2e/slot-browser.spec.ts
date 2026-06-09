import { test, expect, type Locator } from '@playwright/test';
import { gotoSlotBrowser, slotButtons } from './fixtures/helpers';

test.describe('Slot browser', (): void => {
  test('renders availability with weekend gaps', async ({ page }): Promise<void> => {
    await gotoSlotBrowser(page);
    // At least one bookable slot loads...
    await expect(slotButtons(page).first()).toBeVisible();
    // ...and days without schedule blocks (weekends) show the empty state.
    await expect(page.getByText('Sin disponibilidad').first()).toBeVisible();
  });

  test('week navigation moves the visible range forward and back', async ({
    page,
  }): Promise<void> => {
    await gotoSlotBrowser(page);
    await expect(slotButtons(page).first()).toBeVisible();

    // The week-range label is the only element containing an en dash (–).
    const rangeLabel: Locator = page.locator('span').filter({ hasText: '–' });
    const initial: string = (await rangeLabel.textContent())?.trim() ?? '';

    await page.getByRole('button', { name: /Siguiente/ }).click();
    await expect(rangeLabel).not.toHaveText(initial);
    // Going forward enables the previously-disabled "Anterior" button.
    await expect(page.getByRole('button', { name: /Anterior/ })).toBeEnabled();

    await page.getByRole('button', { name: /Anterior/ }).click();
    await expect(rangeLabel).toHaveText(initial);
  });

  test('modality toggle reflects the active selection', async ({ page }): Promise<void> => {
    await gotoSlotBrowser(page);
    await expect(slotButtons(page).first()).toBeVisible();

    await page.getByRole('button', { name: 'Online', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Online', exact: true })).toHaveClass(
      /bg-white/,
    );

    await page.getByRole('button', { name: 'Presencial', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Presencial', exact: true })).toHaveClass(
      /bg-white/,
    );
  });
});
