import { test, expect, type Locator } from '@playwright/test';
import {
  fetchAllMessagesFor,
  inviteFromDialog,
  uniqueEmail,
} from './fixtures/helpers';

test('resend + revoke transitions the invitation row through expected states', async ({
  page,
  request,
}): Promise<void> => {
  // `page` is pre-authenticated as therapist via storageState.
  const patientEmail: string = uniqueEmail('verify-resend');

  await inviteFromDialog(page, patientEmail, 'Resend Test');

  const allRowsForEmail: Locator = page.getByRole('row').filter({ hasText: patientEmail });

  // Resend: original row → Revoked, a fresh Pending row appears for the same email.
  await allRowsForEmail.first().getByLabel('Row actions').click();
  await page.getByRole('menuitem', { name: 'Resend' }).click();
  await page.getByRole('button', { name: 'Resend' }).click();

  await expect(allRowsForEmail).toHaveCount(2);
  await expect(allRowsForEmail.filter({ hasText: 'Revoked' })).toHaveCount(1);

  // MailHog should hold both invitation emails (initial + resend).
  const matchingEmails = await fetchAllMessagesFor(request, patientEmail);
  expect(matchingEmails.length).toBeGreaterThanOrEqual(2);

  // Revoke the still-Pending row.
  await allRowsForEmail
    .filter({ hasText: 'Pending' })
    .getByLabel('Row actions')
    .click();
  await page.getByRole('menuitem', { name: 'Revoke' }).click();
  await page.getByRole('button', { name: 'Revoke' }).click();

  // Filter chip → Revoked: both rows visible.
  await page.getByRole('option', { name: 'Revoked' }).click();
  await expect(allRowsForEmail).toHaveCount(2);
});
