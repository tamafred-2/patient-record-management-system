import { test, expect } from '@playwright/test';

test('midwife records a vaccine and staff completes the visit', async ({ browser, baseURL }) => {
  test.setTimeout(120000);
  let context;
  const errors = [];
  async function signIn(role) {
    if (context) await context.close();
    context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill(role + '@rhu.test');
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    return page;
  }
  try {
    let page = await signIn('information');
    await page.goto('/patients/create');
    await page.getByLabel('First name (required)', { exact: true }).fill('Fictional Vaccine');
    await page.getByLabel('Last name (required)', { exact: true }).fill('Browser');
    await page.getByRole('button', { name: 'Register patient', exact: true }).click();
    await expect(page).toHaveURL(/\/patients\/\d+$/);
    const patient = new URL(page.url()).pathname.split('/').pop();
    await page.getByRole('link', { name: 'Start visit', exact: true }).click();
    await page.getByLabel('Requested service (required)').selectOption({ label: 'Vaccination' });
    await page.getByLabel('Existing queue reference (required)').fill('VAC-BROWSER-001');
    await page.getByRole('button', { name: 'Start visit', exact: true }).click();
    await expect(page).toHaveURL(/\/visits\/\d+$/);
    const visit = new URL(page.url()).pathname.split('/').pop();
    const scope = patient + '/visits/' + visit;

    page = await signIn('midwife');
    await expect(page.getByRole('heading', { name: "Today's vaccination visits", exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Open vaccination record', exact: true }).click();
    await page.getByLabel('Vaccine name (required)', { exact: true }).fill('Fictional vaccine only');
    await page.getByLabel('Dose as documented (required)', { exact: true }).fill('Fictional dose only');
    await page.getByLabel('Batch / lot number (optional)', { exact: true }).fill('FICTIONAL-BATCH');
    await page.getByLabel('Remarks (optional)', { exact: true }).fill('Fictional test, no vaccine administered.');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Save vaccination record', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Vaccination record saved.');
    await page.getByRole('link', { name: 'Edit vaccination entry', exact: true }).click();
    await expect(page.getByLabel('Vaccine name (required)', { exact: true })).toHaveValue('Fictional vaccine only');
    await page.getByLabel('Remarks (optional)', { exact: true }).fill('Fictional corrected record.');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Save vaccination record', exact: true }).click();
    await expect(page.getByText('Fictional corrected record.', { exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'storage/framework/testing/vaccination-mobile.png', fullPage: true });
    expect((await page.goto('/consultations')).status()).toBe(403);

    page = await signIn('information');
    await page.goto('/patients/' + scope);
    await page.getByRole('link', { name: 'Review and complete visit', exact: true }).click();
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Complete visit', exact: true }).click();
    await expect(page.getByText('COMPLETED', { exact: true })).toBeVisible();
    page = await signIn('midwife');
    await page.goto('/vaccinations/' + scope);
    await expect(page.getByText('Vaccination records for this visit are read-only.', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save vaccination record', exact: true })).toHaveCount(0);
    expect(errors).toEqual([]);
  } finally {
    if (context) await context.close();
  }
});
