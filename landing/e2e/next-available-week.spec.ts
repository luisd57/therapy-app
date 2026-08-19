import { test, expect, type Locator, type Page } from '@playwright/test';
import {
  chooseModality,
  openSlotBrowser,
  PRACTICE_ZONE,
  slotButtons,
} from './fixtures/helpers';

/**
 * The only specs here that stub the API: neither case is reachable from the
 * seeded schedule, which recurs weekly and never leaves a future week empty.
 */

// Pinned so the assertions are absolute rather than derived from the fixtures
// (ADR-0003). Nothing here reads the clock: the rendered week comes from the
// stubbed instants, so these stay correct whenever the suite runs.
const WINDOW_START = '2026-08-14T20:00:00+00:00'; // Friday 16:00 practice-local
const WINDOW_END = '2026-08-21T20:00:00+00:00';
const SLOT_INSTANTS = [
  '2026-08-17T16:00:00+00:00', // Monday 12:00 practice-local
  '2026-08-18T16:00:00+00:00', // Tuesday 12:00
];

test.use({ timezoneId: PRACTICE_ZONE, locale: 'es-ES' });

function slot(startInstant: string): Record<string, unknown> {
  const start: Date = new Date(startInstant);
  return {
    start_time: startInstant,
    end_time: new Date(start.getTime() + 50 * 60_000).toISOString(),
    duration_minutes: 50,
  };
}

async function stubApi(
  page: Page,
  nextWeek: Record<string, unknown>,
  slots: Record<string, unknown>[],
): Promise<void> {
  await page.route('**/appointments/next-available-week*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: nextWeek }),
    }),
  );

  await page.route('**/appointments/available-slots*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          slots,
          total_slots: slots.length,
          practice_timezone: PRACTICE_ZONE,
        },
      }),
    }),
  );
}

test.describe('Next available week', (): void => {
  test('renders the week holding the slots, not the one the window opens in', async ({
    page,
  }): Promise<void> => {
    // The window opens on Friday the 14th and runs seven days, but its slots
    // are Monday the 17th and Tuesday the 18th, the following calendar week.
    const slots = SLOT_INSTANTS.map(slot);

    await stubApi(
      page,
      {
        found: true,
        week_start: WINDOW_START,
        week_end: WINDOW_END,
        modality: null,
        practice_timezone: PRACTICE_ZONE,
        slots,
        total_slots: slots.length,
      },
      slots,
    );

    await openSlotBrowser(page);
    // Nothing is preselected here: the viewer sits in the practice zone.
    await chooseModality(page, 'ONLINE');

    // The week-range label is the only element containing an en dash.
    const rangeLabel: Locator = page.locator('span').filter({ hasText: '–' });
    await expect(rangeLabel).toContainText(/17.*23/);

    await expect(slotButtons(page)).toHaveCount(2);
    await expect(slotButtons(page).first()).toContainText('12:00');
    await expect(page.getByTestId('week-empty')).toBeHidden();
  });

  test('shows an empty state instead of a blank grid when nothing is available', async ({
    page,
  }): Promise<void> => {
    await stubApi(
      page,
      {
        found: false,
        week_start: null,
        week_end: null,
        modality: null,
        practice_timezone: PRACTICE_ZONE,
        slots: [],
        total_slots: 0,
      },
      [],
    );

    await openSlotBrowser(page);
    await chooseModality(page, 'ONLINE');

    await expect(page.getByTestId('week-empty')).toBeVisible();
    await expect(slotButtons(page)).toHaveCount(0);
  });
});
