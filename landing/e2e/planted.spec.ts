import { test, type PlaywrightTestArgs } from '@playwright/test';

test('planted: a test with no assertion must fail lint', async ({
  page,
}: PlaywrightTestArgs): Promise<void> => {
  await page.goto('/');
});
