import { expect, type Locator, type Page } from '@playwright/test';

export const API_BASE_URL: string = process.env['API_BASE_URL'] ?? 'http://localhost:8080/api';

export type Modality = 'ONLINE' | 'IN_PERSON';

export const PRACTICE_ZONE = 'America/Caracas';

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
 * Astro drops the `ssr` attribute once a client component hydrates. A click
 * before that lands on inert server markup and is silently lost.
 */
async function waitForHydration(page: Page): Promise<void> {
  await expect(page.locator('astro-island[ssr]')).toHaveCount(0);
}

/**
 * Open the flow at the modality chooser. The AppointmentFlow island is
 * `client:visible` and sits below the fold, so it needs a scroll to hydrate.
 */
export async function openSlotBrowser(page: Page): Promise<void> {
  await page.goto('/');
  await page.getByText('Agenda tu cita').scrollIntoViewIfNeeded();
  // The chooser is the first thing a spec touches, and unlike the grid it is
  // not preceded by a fetch that would have forced hydration already.
  await waitForHydration(page);
}

/**
 * Confirm a modality on the chooser. Nothing is fetched before this, so every
 * spec that wants a grid has to go through it.
 */
export async function chooseModality(
  page: Page,
  modality: Modality = 'ONLINE',
): Promise<void> {
  await page.getByTestId(`modality-option-${modality}`).click();
  await page.getByTestId('modality-continue').click();
}

/** Open the flow, take the given modality, and wait for real availability. */
export async function gotoSlotBrowser(
  page: Page,
  modality: Modality = 'ONLINE',
): Promise<void> {
  await openSlotBrowser(page);
  await chooseModality(page, modality);
  // globalSetup guarantees seeded availability, so a slot button always appears
  // once the browser finishes its initial fetch.
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
