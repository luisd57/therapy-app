import { test, expect, type APIRequestContext, type Route } from '@playwright/test';
import {
  chooseModality,
  fillRequestForm,
  openSlotBrowser,
  PRACTICE_ZONE,
  selectFirstAvailableSlot,
  slotButtons,
  validRequestForm,
} from './fixtures/helpers';
import { replaceScheduleWith, therapistContext } from './fixtures/schedule';

/**
 * The case that made CI red: the only availability on offer sits in a schedule
 * block that cannot host an online session. The seeded schedule reaches it only
 * on a Thursday evening, so this spec builds it through the therapist API and
 * puts the schedule back afterwards.
 */
test.describe('A schedule whose only block is in person', (): void => {
  test.use({ timezoneId: PRACTICE_ZONE, locale: 'es-ES' });

  const IN_PERSON_ONLY = {
    day_of_week: 1, // Monday
    start_time: '09:00',
    end_time: '12:00',
    supports_online: false,
    supports_in_person: true,
  };

  let context: APIRequestContext | undefined;
  let restore: (() => Promise<void>) | undefined;

  test.beforeAll(async (): Promise<void> => {
    context = await therapistContext();
    restore = await replaceScheduleWith(context, IN_PERSON_ONLY);
  });

  test.afterAll(async (): Promise<void> => {
    // Every later spec reads the seeded schedule, so this has to run even when
    // the assertions below fail.
    if (restore) await restore();
    if (context) await context.dispose();
  });

  test('online browsing offers nothing, whatever day it runs on', async ({
    page,
  }): Promise<void> => {
    await openSlotBrowser(page);
    await chooseModality(page, 'ONLINE');

    await expect(page.getByTestId('week-empty')).toBeVisible();
    await expect(slotButtons(page)).toHaveCount(0);
  });

  test('the same schedule books in person end to end', async ({ page }): Promise<void> => {
    let submitted: string | null = null;
    await page.route('**/appointments/request', async (route: Route) => {
      submitted = route.request().postData();
      await route.continue();
    });

    await openSlotBrowser(page);
    await chooseModality(page, 'IN_PERSON');
    await expect(slotButtons(page).first()).toBeVisible();

    await selectFirstAvailableSlot(page);
    await fillRequestForm(page, validRequestForm());
    await page.getByRole('button', { name: 'Solicitar cita' }).click();

    await expect(page.getByText('Solicitud recibida')).toBeVisible();
    expect(JSON.parse(submitted ?? '{}').modality).toBe('IN_PERSON');
  });
});
