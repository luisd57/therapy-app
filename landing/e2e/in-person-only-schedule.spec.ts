import {
  test,
  expect,
  type APIRequestContext,
  type PlaywrightTestArgs,
  type Route,
} from '@playwright/test';
import {
  chooseModality,
  fillRequestForm,
  modalityOf,
  openSlotBrowser,
  PRACTICE_ZONE,
  selectFirstAvailableSlot,
  slotButtons,
  validRequestForm,
} from './fixtures/helpers';
import {
  installInPersonOnlySchedule,
  requireSoleWorker,
  restoreBaseline,
  therapistContext,
} from './fixtures/schedule';

/**
 * The case that made CI red: the only availability on offer sits in a schedule
 * block that cannot host an online session. The seeded schedule reaches it only
 * on a Thursday evening, so this spec builds it through the therapist API and
 * puts the schedule back afterwards.
 */
test.describe('A schedule whose only block is in person', (): void => {
  test.describe.configure({ mode: 'serial' });
  test.use({ timezoneId: PRACTICE_ZONE, locale: 'es-ES' });

  let context: APIRequestContext | undefined;

  test.beforeAll(async (): Promise<void> => {
    requireSoleWorker(test.info().config);
    context = await therapistContext();
    await installInPersonOnlySchedule(context);
  });

  test.afterAll(async (): Promise<void> => {
    // Every later spec reads the seeded schedule, so this has to run even when the
    // assertions below fail, or the swap above died halfway.
    if (context) await restoreBaseline(context);
  });

  test('online browsing offers nothing, whatever day it runs on', async ({
    page,
  }: PlaywrightTestArgs): Promise<void> => {
    await openSlotBrowser(page);
    await chooseModality(page, 'ONLINE');

    await expect(page.getByTestId('week-empty')).toBeVisible();
    await expect(slotButtons(page)).toHaveCount(0);
  });

  test('the same schedule books in person end to end', async ({
    page,
  }: PlaywrightTestArgs): Promise<void> => {
    // A holder, not a `let`: TS does not see the callback assign it and narrows it to null.
    const submitted: { body: string | null } = { body: null };
    await page.route('**/appointments/request', async (route: Route): Promise<void> => {
      submitted.body = route.request().postData();
      await route.continue();
    });

    await openSlotBrowser(page);
    await chooseModality(page, 'IN_PERSON');
    await expect(slotButtons(page).first()).toBeVisible();

    await selectFirstAvailableSlot(page);
    await fillRequestForm(page, validRequestForm());
    await page.getByRole('button', { name: 'Solicitar cita' }).click();

    await expect(page.getByText('Solicitud recibida')).toBeVisible();
    expect(modalityOf(submitted.body)).toBe('IN_PERSON');
  });
});
