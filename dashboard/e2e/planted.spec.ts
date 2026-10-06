import { test, expect, type PlaywrightTestArgs } from '@playwright/test';

test.skip('planted: a skipped test must fail lint', async ({
  page,
}: PlaywrightTestArgs): Promise<void> => {
  await expect(page).toHaveURL(/\/login$/);
});
