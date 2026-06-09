import { test, expect, type BrowserContext, type Page } from '@playwright/test';

// authGuard redirects unauthenticated users to /login for every route under the
// shell. Verified from a fresh, anonymous context (no storageState).
test.describe('Auth — route guards', (): void => {
  for (const route of ['/appointments', '/schedule', '/patients']) {
    test(`unauthenticated ${route} redirects to /login`, async ({ browser }): Promise<void> => {
      const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
      const page: Page = await ctx.newPage();
      await page.goto(route);
      await expect(page).toHaveURL(/\/login$/);
      await ctx.close();
    });
  }
});
