import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { loginAsTherapist } from './fixtures/helpers';

// Logout shares one implementation across roles (only the post-logout redirect
// target differs), so it is exercised once with the therapist.
//
// This test logs in with its OWN fresh session rather than reusing the shared
// storageState: logout revokes the token's jti server-side, so consuming the
// global session would break every other test that relies on it.
test.describe('Auth — logout', (): void => {
  test('logout clears the session and re-protects routes', async ({ browser }): Promise<void> => {
    const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
    const page: Page = await ctx.newPage();
    await loginAsTherapist(page);

    // The logout button is icon-only and the mat-icon is aria-hidden, so it has
    // no accessible name — target it by the `logout` icon ligature instead.
    await page.locator('button', { has: page.locator('mat-icon', { hasText: 'logout' }) }).click();
    await expect(page).toHaveURL(/\/login$/);

    // Session is gone — the guard now bounces protected routes back to /login.
    await page.goto('/appointments');
    await expect(page).toHaveURL(/\/login$/);

    await ctx.close();
  });
});
