import { test, expect } from '@playwright/test';

test('exact duplicate opens a responsive modal and preserves editing', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard/);
  async function submitPatient() {
    await page.goto('/patients/create');
    for (const [name, value] of Object.entries({ first_name: 'Fictional Modal', last_name: 'Example', birth_date: '2000-01-10', barangay: 'Example Barangay', municipality: 'Calasiao', province: 'Pangasinan' })) {
      await page.locator(`#${name}`).fill(value);
    }
    await page.locator('#sex').selectOption('Female');
    await page.getByRole('button', { name: 'Register patient', exact: true }).click();
  }
  await submitPatient();
  await expect(page).toHaveURL(/patients\/\d+$/);
  const profile = page.url();
  await submitPatient();
  const dialog = page.getByRole('dialog', { name: 'Patient already exists' });
  await expect(dialog).toBeVisible();
  await expect(dialog).toContainText('Fictional Modal Example');
  await expect(dialog.getByRole('button', { name: 'Back to editing' })).toBeFocused();
  await page.screenshot({ path: 'storage/framework/testing/patient-duplicate-modal-desktop.png' });
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(page.locator('#first_name')).toBeFocused();
  await expect(page.locator('#first_name')).toHaveValue('Fictional Modal');
  await page.getByRole('button', { name: 'View matching patient' }).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(dialog).toBeVisible();
  expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
  await page.screenshot({ path: 'storage/framework/testing/patient-duplicate-modal-mobile.png' });
  await dialog.getByRole('button', { name: 'Back to editing' }).click();
  await expect(dialog).toBeHidden();
  await page.getByRole('button', { name: 'View matching patient' }).click();
  await dialog.getByRole('link', { name: 'Open existing profile' }).click();
  await expect(page).toHaveURL(profile);
});
