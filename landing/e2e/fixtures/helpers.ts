import { expect, type Locator, type Page } from '@playwright/test';

export const API_BASE_URL: string = process.env['API_BASE_URL'] ?? 'http://localhost:8080/api';

export interface RequestFormData {
  fullName: string;
  phone: string;
  email: string;
  city: string;
  country: string;
}

export function uniqueEmail(prefix: string): string {
  return `${prefix}+${Date.now()}-${Math.random().toString(36).slice(2, 7)}@e2e.test`;
}

/** A slot button (SlotCard) renders the time plus "<duration> min". */
export function slotButtons(page: Page): Locator {
  return page.getByRole('button').filter({ hasText: /\bmin\b/ });
}

/**
 * Open the slot browser. The AppointmentFlow island uses `client:visible` and
 * sits below the fold, so we must scroll it into view to trigger hydration (and
 * its initial slot fetch). Resolves once a slot button or the empty-state renders.
 */
export async function gotoSlotBrowser(page: Page): Promise<void> {
  await page.goto('/');
  await page.getByText('Agenda tu cita').scrollIntoViewIfNeeded();
  // globalSetup guarantees seeded availability, so a slot button always appears
  // once the island hydrates and finishes its initial fetch.
  await expect(slotButtons(page).first()).toBeVisible();
}

/**
 * Click the first available slot, transitioning the flow to the request form.
 * Assumes the slot browser is already open (see {@link gotoSlotBrowser}).
 */
export async function selectFirstAvailableSlot(page: Page): Promise<void> {
  const firstSlot: Locator = slotButtons(page).first();
  await firstSlot.waitFor({ state: 'visible' });
  await firstSlot.click();
  // The form's submit button confirms we reached the filling_form step.
  await expect(page.getByRole('button', { name: 'Solicitar cita' })).toBeVisible();
}

export async function fillRequestForm(page: Page, data: RequestFormData): Promise<void> {
  await page.locator('#fullName').fill(data.fullName);
  await page.locator('#phone').fill(data.phone);
  await page.locator('#email').fill(data.email);
  await page.locator('#city').fill(data.city);
  await page.locator('#country').fill(data.country);
}

export function validRequestForm(): RequestFormData {
  return {
    fullName: 'E2E Reservation Tester',
    phone: '+584141234567',
    email: uniqueEmail('landing'),
    city: 'Caracas',
    country: 'Venezuela',
  };
}
