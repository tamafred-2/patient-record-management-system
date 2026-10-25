import { test, expect } from '@playwright/test';

test('completion staff reopen a visit and pharmacy preserves a voided release', async ({ browser, baseURL }) => {
  test.setTimeout(90000);
  const contexts = [];
  const errors = [];
  async function login(role) {
    const context = await browser.newContext({ baseURL });
    contexts.push(context);
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill(`${role}@rhu.test`);
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    return page;
  }
  try {
    const staff = await login('information');
    await staff.goto('/patients/2/visits/2/completion');
    await staff.getByLabel(/I have checked that no further service/).check();
    await staff.getByRole('button', { name: 'Complete visit', exact: true }).click();
    await expect(staff.getByText('Completed by')).toBeVisible();
    await staff.getByText('Reopen for correction', { exact: true }).click();
    await staff.getByLabel('Reason for reopening').fill('Fictional browser correction');
    await staff.getByLabel(/I confirm this visit needs correction/).check();
    await staff.getByRole('button', { name: 'Reopen visit', exact: true }).click();
    await expect(staff.getByRole('heading', { name: 'Reopening history' })).toBeVisible();
    await expect(staff.getByText('Fictional browser correction', { exact: true })).toBeVisible();
    await expect(staff.getByRole('button', { name: 'Complete visit', exact: true })).toBeVisible();
    await staff.screenshot({ path: 'storage/framework/testing/reopening-history.png', fullPage: true });

    const pharmacy = await login('pharmacist');
    await pharmacy.goto('/pharmacy/1/visits/1');
    await pharmacy.getByText('Correct this release', { exact: true }).click();
    await pharmacy.getByLabel('Correction reason').fill('Fictional release entry correction');
    await pharmacy.getByLabel(/I confirm this entry is incorrect/).check();
    await pharmacy.getByRole('button', { name: 'Void incorrect release' }).click();
    await expect(pharmacy.getByText(/Voided by/)).toBeVisible();
    await expect(pharmacy.getByText('Not dispensed', { exact: true })).toBeVisible();
    await pharmacy.screenshot({ path: 'storage/framework/testing/voided-release.png', fullPage: true });
    expect(errors).toEqual([]);
  } finally {
    for (const context of contexts) await context.close();
  }
});
