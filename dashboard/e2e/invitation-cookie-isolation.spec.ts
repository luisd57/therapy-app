import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import {
  PATIENT_PASSWORD,
  fetchLatestTokenFor,
  inviteFromDialog,
  loginAsPatient,
  loginAsTherapist,
  registerPatient,
  uniqueEmail,
} from './fixtures/helpers';

test('F1 regression: patient login in second tab does not break therapist session', async ({
  browser,
  request,
}): Promise<void> => {
  const patientEmail: string = uniqueEmail('verify-cookie-iso');
  const secondInviteEmail: string = uniqueEmail('verify-second');

  // SAME browser context — therapist and patient must coexist in one cookie jar.
  // This test DELIBERATELY logs in (rather than using storageState) because the
  // regression we're guarding against is the act of one role's login clobbering
  // the other's cookie. We need the real login flow to exercise that path.
  //
  // Pre-fix: both roles used the same cookie name `THERAPY_JWT` — the patient
  // login overwrote the therapist's and the therapist tab started getting 403s.
  // Post-fix: cookies are role-scoped (THERAPY_THERAPIST_JWT /
  // THERAPY_PATIENT_JWT) and a custom extractor reads either, so both sessions
  // survive in the same browser.
  const ctx: BrowserContext = await browser.newContext({ storageState: undefined });
  const therapistTab: Page = await ctx.newPage();

  await loginAsTherapist(therapistTab);
  await inviteFromDialog(therapistTab, patientEmail, 'Cookie Isolation Test');

  const token: string = await fetchLatestTokenFor(request, patientEmail);

  // Open patient registration in a SECOND TAB of the same context.
  const patientTab: Page = await ctx.newPage();
  await registerPatient(patientTab, token, PATIENT_PASSWORD);
  await patientTab.getByRole('link', { name: 'Go to Login' }).click();
  await loginAsPatient(patientTab, patientEmail, PATIENT_PASSWORD);

  // Switch back to therapist tab and send another invitation.
  // Must succeed: any 403 here would prove the therapist's session was clobbered.
  await therapistTab.bringToFront();
  await inviteFromDialog(therapistTab, secondInviteEmail, 'Post-cookie-fix');
  await expect(
    therapistTab.getByRole('row').filter({ hasText: secondInviteEmail }).first(),
  ).toContainText('Pending');

  await ctx.close();
});
