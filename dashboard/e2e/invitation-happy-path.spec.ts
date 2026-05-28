import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import {
  PATIENT_PASSWORD,
  fetchLatestTokenFor,
  inviteFromDialog,
  loginAsPatient,
  registerPatient,
  uniqueEmail,
} from './fixtures/helpers';

test('happy path: therapist invites → patient registers → row flips to Used after refresh', async ({
  page,
  browser,
  request,
}): Promise<void> => {
  // `page` is pre-authenticated as the therapist via storageState (globalSetup).
  const patientEmail: string = uniqueEmail('verify-patient');
  const patientName: string = 'E2E Verify Patient';

  await inviteFromDialog(page, patientEmail, patientName);
  await expect(
    page.getByRole('row').filter({ hasText: patientEmail }).first(),
  ).toContainText('Pending');

  // Patient consumes the token in a SEPARATE context with no cookies.
  const token: string = await fetchLatestTokenFor(request, patientEmail);
  const patientContext: BrowserContext = await browser.newContext({ storageState: undefined });
  const patientPage: Page = await patientContext.newPage();
  await patientPage.goto(`/register?token=${token}`);
  await expect(patientPage.getByText(`Welcome, ${patientName}!`)).toBeVisible();
  await registerPatient(patientPage, token, PATIENT_PASSWORD);
  await patientPage.getByRole('link', { name: 'Go to Login' }).click();
  await loginAsPatient(patientPage, patientEmail, PATIENT_PASSWORD);
  await patientContext.close();

  // F2 regression — therapist list should refresh on visibility change.
  // Simulate by dispatching the event directly (Playwright pages are always "visible").
  await page.evaluate((): void => {
    document.dispatchEvent(new Event('visibilitychange'));
  });
  await expect(
    page.getByRole('row').filter({ hasText: patientEmail }).first(),
  ).toContainText('Used');
});
