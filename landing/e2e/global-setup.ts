import { request, type APIRequestContext } from '@playwright/test';
import { API_BASE_URL } from './fixtures/helpers';

const READY_TIMEOUT_MS: number = 120_000;
const POLL_INTERVAL_MS: number = 2_000;

interface NextAvailableWeek {
  success: boolean;
  data?: { found: boolean };
}

/**
 * The reservation specs need real availability. Schedule blocks are recurring
 * weekly, so once `app:seed-schedule` has run, slots always exist in the future.
 * Fail fast (with a clear remedy) if none are seeded — otherwise the specs would
 * fail deep in the flow with a confusing "no slot to click" error.
 */
export default async function globalSetup(): Promise<void> {
  await waitForUrl(`${API_BASE_URL}/appointments/next-available-week`, 'API');

  const context: APIRequestContext = await request.newContext();
  try {
    const response = await context.get(`${API_BASE_URL}/appointments/next-available-week`);
    if (!response.ok()) {
      throw new Error(`next-available-week returned ${response.status()} from ${API_BASE_URL}`);
    }
    const body = (await response.json()) as NextAvailableWeek;
    if (!body.data?.found) {
      throw new Error(
        'No available slots found. Seed availability first: ' +
          '`docker-compose exec php php bin/console app:seed-schedule`',
      );
    }
  } finally {
    await context.dispose();
  }
}

async function waitForUrl(url: string, label: string): Promise<void> {
  const context: APIRequestContext = await request.newContext();
  const deadline: number = Date.now() + READY_TIMEOUT_MS;
  try {
    let lastError: string = '';
    while (Date.now() < deadline) {
      try {
        const response = await context.get(url, { timeout: 5_000 });
        if (response.status() > 0) return;
      } catch (error: unknown) {
        lastError = error instanceof Error ? error.message : String(error);
      }
      await new Promise<void>((resolve): NodeJS.Timeout => setTimeout(resolve, POLL_INTERVAL_MS));
    }
    throw new Error(
      `Timed out after ${READY_TIMEOUT_MS}ms waiting for ${label} at ${url}. Last error: ${lastError}`,
    );
  } finally {
    await context.dispose();
  }
}
