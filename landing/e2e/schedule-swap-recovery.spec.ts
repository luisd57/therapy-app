import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  activeBlocks,
  installInPersonOnlySchedule,
  normalized,
  requireSoleWorker,
  restoreBaseline,
  therapistContext,
  type ScheduleBlock,
} from './fixtures/schedule';

/**
 * CI retries once. A retry after a lost restore swaps again from the swapped state,
 * and its undo must still bring back the seed, not the leftover block.
 */
test.describe('A schedule swap whose restore never ran', (): void => {
  let context: APIRequestContext | undefined;

  test.beforeAll((): void => {
    requireSoleWorker(test.info().config);
  });

  test.afterAll(async (): Promise<void> => {
    // A hook, not a finally: it stops a timed-out body before restoring.
    await restoreBaseline(context);
  });

  test('the next swap still restores the seeded blocks', async (): Promise<void> => {
    // Rewrites the whole schedule several times, one request per block.
    test.slow();

    context = await therapistContext();
    const seeded: ScheduleBlock[] = normalized(await activeBlocks(context));

    // First attempt: swap, then the restore is lost.
    await installInPersonOnlySchedule(context);

    // Retry: swap again from the swapped state and restore normally.
    await installInPersonOnlySchedule(context);
    await restoreBaseline();

    expect(normalized(await activeBlocks(context))).toEqual(seeded);
  });
});
