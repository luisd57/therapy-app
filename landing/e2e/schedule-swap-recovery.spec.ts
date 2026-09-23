import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  activeBlocks,
  IN_PERSON_ONLY_BLOCK,
  normalized,
  replaceScheduleWith,
  requireSoleWorker,
  restoreBaseline,
  therapistContext,
  type ScheduleBlock,
} from './fixtures/schedule';

/**
 * CI retries a failed spec once. If the first attempt's restore never runs, the
 * retry swaps again from the swapped state, and its undo must still bring back
 * the seeded schedule rather than the leftover single block.
 */
test.describe('A schedule swap whose restore never ran', (): void => {
  test.describe.configure({ mode: 'serial' });

  test.afterAll(async (): Promise<void> => {
    // A hook, not a finally: it still runs when the test times out mid-rewrite.
    const context: APIRequestContext = await therapistContext();
    try {
      await restoreBaseline(context);
    } finally {
      await context.dispose();
    }
  });

  test('the next swap still restores the seeded blocks', async (): Promise<void> => {
    requireSoleWorker(test.info().config);
    // Rewrites the whole schedule several times, one request per block.
    test.slow();

    const context: APIRequestContext = await therapistContext();
    try {
      const seeded: ScheduleBlock[] = normalized(await activeBlocks(context));

      // First attempt: swap, then the restore is lost.
      await replaceScheduleWith(context, IN_PERSON_ONLY_BLOCK);

      // Retry: swap again from the swapped state and restore normally.
      await replaceScheduleWith(context, IN_PERSON_ONLY_BLOCK);
      await restoreBaseline(context);

      expect(normalized(await activeBlocks(context))).toEqual(seeded);
    } finally {
      await context.dispose();
    }
  });
});
