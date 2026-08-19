import { test, expect, type Page, type Route } from '@playwright/test';
import {
  chooseModality,
  fillRequestForm,
  openSlotBrowser,
  PRACTICE_ZONE,
  selectFirstAvailableSlot,
  slotButtons,
  validRequestForm,
} from './fixtures/helpers';

/** The container runs as UTC, so Online is preselected in this block. */
test.describe('Modality gate', (): void => {
  test('no slot is reachable until a modality is confirmed', async ({
    page,
  }): Promise<void> => {
    await openSlotBrowser(page);

    // The chooser being visible proves the island hydrated, so the empty count
    // below is a real absence rather than a race with hydration.
    await expect(page.getByTestId('modality-chooser')).toBeVisible();
    await expect(slotButtons(page)).toHaveCount(0);

    await chooseModality(page, 'ONLINE');

    await expect(page.getByTestId('modality-chooser')).toBeHidden();
    await expect(slotButtons(page).first()).toBeVisible();
  });

  test('the browsed modality is the modality submitted', async ({
    page,
  }): Promise<void> => {
    const sent: Record<string, string | null> = {};

    await page.route('**/appointments/lock-slot', async (route: Route) => {
      sent['lock'] = route.request().postData();
      await route.continue();
    });
    await page.route('**/appointments/request', async (route: Route) => {
      sent['request'] = route.request().postData();
      await route.continue();
    });

    await openSlotBrowser(page);
    await chooseModality(page, 'IN_PERSON');
    await expect(slotButtons(page).first()).toBeVisible();

    await selectFirstAvailableSlot(page);
    await fillRequestForm(page, validRequestForm());
    await page.getByRole('button', { name: 'Solicitar cita' }).click();

    // The API refuses a mismatched modality with SLOT_NOT_AVAILABLE, so the
    // confirmation appearing is itself half the assertion.
    await expect(page.getByText('Solicitud recibida')).toBeVisible();

    expect(JSON.parse(sent['lock'] ?? '{}').modality).toBe('IN_PERSON');
    expect(JSON.parse(sent['request'] ?? '{}').modality).toBe('IN_PERSON');
  });

  test('every availability request carries a modality', async ({
    page,
  }): Promise<void> => {
    const availability: string[] = [];
    page.on('request', (request): void => {
      const url: string = request.url();
      if (/\/appointments\/(available-slots|next-available-week)/.test(url)) {
        availability.push(url);
      }
    });

    await openSlotBrowser(page);
    await chooseModality(page, 'ONLINE');
    await expect(slotButtons(page).first()).toBeVisible();

    // Switching modality and paging both refetch, so both are covered.
    const afterFirstLoad: number = availability.length;
    await page.getByRole('button', { name: 'Presencial', exact: true }).click();
    await expect.poll(() => availability.length).toBeGreaterThan(afterFirstLoad);

    const afterSwitch: number = availability.length;
    await page.getByRole('button', { name: /Siguiente/ }).click();
    await expect.poll(() => availability.length).toBeGreaterThan(afterSwitch);

    for (const url of availability) {
      expect(new URL(url).searchParams.get('modality'), url).not.toBeNull();
    }
  });
});

test.describe('Preselection abroad', (): void => {
  test.use({ timezoneId: 'Europe/Madrid', locale: 'es-ES' });

  test('online is preselected when the viewer zone is not the practice zone', async ({
    page,
  }): Promise<void> => {
    await openSlotBrowser(page);

    await expect(page.getByTestId('modality-option-ONLINE')).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    await expect(page.getByTestId('modality-option-IN_PERSON')).toHaveAttribute(
      'aria-pressed',
      'false',
    );
    await expect(page.getByTestId('modality-continue')).toBeEnabled();
  });
});

test.describe('Preselection in the practice zone', (): void => {
  test.use({ timezoneId: PRACTICE_ZONE, locale: 'es-ES' });

  test('nothing is preselected when the zones match', async ({
    page,
  }): Promise<void> => {
    await openSlotBrowser(page);

    await expect(page.getByTestId('modality-option-ONLINE')).toHaveAttribute(
      'aria-pressed',
      'false',
    );
    await expect(page.getByTestId('modality-option-IN_PERSON')).toHaveAttribute(
      'aria-pressed',
      'false',
    );
    await expect(page.getByTestId('modality-continue')).toBeDisabled();

    await page.getByTestId('modality-option-IN_PERSON').click();
    await expect(page.getByTestId('modality-continue')).toBeEnabled();
  });
});

/**
 * Stubbed: the real schedule cannot be made to offer only in-person slots on
 * demand. This is the shape CI run 31750161621 hit on a Thursday evening.
 */
test.describe('A week whose only availability is in person', (): void => {
  test.use({ timezoneId: PRACTICE_ZONE, locale: 'es-ES' });

  const FRIDAY_IN_PERSON = '2026-08-21T12:00:00+00:00'; // Friday 08:00 practice-local

  function slotsFor(modality: string | null): Record<string, unknown>[] {
    // A request with no modality is the regression, and it answers with the
    // slot the requester cannot book, exactly as the API did before the filter.
    if (modality === 'ONLINE') return [];
    const start: Date = new Date(FRIDAY_IN_PERSON);
    return [
      {
        start_time: FRIDAY_IN_PERSON,
        end_time: new Date(start.getTime() + 50 * 60_000).toISOString(),
        duration_minutes: 50,
      },
    ];
  }

  async function stubInPersonOnly(page: Page): Promise<void> {
    const json = (data: Record<string, unknown>): Parameters<Route['fulfill']>[0] => ({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data }),
    });

    await page.route('**/appointments/next-available-week*', (route: Route) => {
      const modality: string | null = new URL(
        route.request().url(),
      ).searchParams.get('modality');
      const slots = slotsFor(modality);
      return route.fulfill(
        json({
          found: slots.length > 0,
          week_start: slots.length > 0 ? FRIDAY_IN_PERSON : null,
          week_end: null,
          modality,
          practice_timezone: PRACTICE_ZONE,
          slots,
          total_slots: slots.length,
        }),
      );
    });

    await page.route('**/appointments/available-slots*', (route: Route) => {
      const modality: string | null = new URL(
        route.request().url(),
      ).searchParams.get('modality');
      const slots = slotsFor(modality);
      return route.fulfill(
        json({ slots, total_slots: slots.length, practice_timezone: PRACTICE_ZONE }),
      );
    });
  }

  test('browsing online offers nothing rather than an unbookable slot', async ({
    page,
  }): Promise<void> => {
    await stubInPersonOnly(page);
    await openSlotBrowser(page);
    await chooseModality(page, 'ONLINE');

    await expect(page.getByTestId('week-empty')).toBeVisible();
    await expect(slotButtons(page)).toHaveCount(0);
  });

  test('the same week offers that slot in person', async ({ page }): Promise<void> => {
    await stubInPersonOnly(page);
    await openSlotBrowser(page);
    await chooseModality(page, 'IN_PERSON');

    await expect(slotButtons(page)).toHaveCount(1);
    await expect(slotButtons(page).first()).toContainText('8:00');
  });
});

/** Stubbed: the API cannot be made to fail on demand. */
test.describe('A refetch that fails after switching modality', (): void => {
  test.use({ timezoneId: PRACTICE_ZONE, locale: 'es-ES' });

  const ONLINE_SLOT = '2026-08-19T12:00:00+00:00'; // Wednesday 08:00 practice-local

  async function stubFailingInPerson(page: Page): Promise<void> {
    const slots = [
      {
        start_time: ONLINE_SLOT,
        end_time: new Date(new Date(ONLINE_SLOT).getTime() + 50 * 60_000).toISOString(),
        duration_minutes: 50,
      },
    ];

    await page.route('**/appointments/next-available-week*', (route: Route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            found: true,
            week_start: ONLINE_SLOT,
            week_end: null,
            modality: 'ONLINE',
            practice_timezone: PRACTICE_ZONE,
            slots,
            total_slots: 1,
          },
        }),
      }),
    );

    await page.route('**/appointments/available-slots*', (route: Route) => {
      const modality: string | null = new URL(
        route.request().url(),
      ).searchParams.get('modality');

      if (modality === 'IN_PERSON') {
        return route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            error: { code: 'INTERNAL_ERROR', message: 'Error del servidor.' },
          }),
        });
      }

      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: { slots, total_slots: 1, practice_timezone: PRACTICE_ZONE },
        }),
      });
    });
  }

  test('drops the slots it could not refetch instead of leaving them clickable', async ({
    page,
  }): Promise<void> => {
    await stubFailingInPerson(page);
    await openSlotBrowser(page);
    await chooseModality(page, 'ONLINE');
    await expect(slotButtons(page)).toHaveCount(1);

    await page.getByRole('button', { name: 'Presencial', exact: true }).click();

    // Stale online slots would submit as IN_PERSON and be refused by the API.
    await expect(page.getByText('Error del servidor.')).toBeVisible();
    await expect(slotButtons(page)).toHaveCount(0);
  });
});
