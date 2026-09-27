import { test, expect } from '@playwright/test';

test('midwife records care with conditional location and staff completes the visit', async ({ browser, baseURL }) => {
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
    await page.getByLabel('First name (required)', { exact: true }).fill('Fictional Care');
    await page.getByLabel('Last name (required)', { exact: true }).fill('Browser');
    await page.getByRole('button', { name: 'Register patient', exact: true }).click();
    await expect(page).toHaveURL(/\/patients\/\d+$/);
    const patient = new URL(page.url()).pathname.split('/').pop();
    await page.getByRole('link', { name: 'Start visit', exact: true }).click();
    await page.getByLabel('Requested service (required)').selectOption({ label: 'Midwife Care' });
    await page.getByLabel('Existing queue reference (required)').fill('CARE-BROWSER-001');
    await page.getByRole('button', { name: 'Start visit', exact: true }).click();
    await expect(page).toHaveURL(/\/visits\/\d+$/);
    const visit = new URL(page.url()).pathname.split('/').pop();
    const scope = patient + '/visits/' + visit;

    page = await signIn('midwife');
    await expect(page.getByRole('heading', { name: "Today's Midwife care visits", exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Open Midwife care record', exact: true }).click();
    await page.getByLabel('Care type (required)', { exact: true }).selectOption('COMMUNITY');
    await page.getByLabel('Visit purpose (required)', { exact: true }).fill('Fictional care purpose only.');
    await page.getByLabel('Community / home visit location (required)', { exact: true }).fill('Fictional home location');
    await page.getByLabel('Services provided (optional)', { exact: true }).fill('Fictional service, no clinical advice.');
    await page.getByLabel('Care notes (optional)', { exact: true }).fill('Fictional notes.');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Save care record', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Midwife care record saved.');
    await page.getByRole('link', { name: 'Edit care entry', exact: true }).click();
    await expect(page.getByLabel('Visit purpose (required)', { exact: true })).toHaveValue('Fictional care purpose only.');
    await page.getByLabel('Care type (required)', { exact: true }).selectOption('POSTPARTUM');
    await expect(page.getByLabel('Community / home visit location (required)', { exact: true })).not.toBeVisible();
    await expect(page.getByLabel('Community / home visit location (required)', { exact: true })).toBeDisabled();
    await page.getByLabel('Care notes (optional)', { exact: true }).fill('Fictional corrected record.');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Save care record', exact: true }).click();
    await expect(page.getByText('Fictional corrected record.', { exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'storage/framework/testing/midwife-care-mobile.png', fullPage: true });
    expect((await page.goto('/consultations')).status()).toBe(403);

    page = await signIn('information');
    await page.goto('/patients/' + scope);
    await page.getByRole('link', { name: 'Review and complete visit', exact: true }).click();
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Complete visit', exact: true }).click();
    await expect(page.getByText('COMPLETED', { exact: true })).toBeVisible();
    page = await signIn('midwife');
    await page.goto('/midwife-care/' + scope);
    await expect(page.getByText('Care records for this visit are read-only.', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save care record', exact: true })).toHaveCount(0);
    expect(errors).toEqual([]);
  } finally {
    if (context) await context.close();
  }
});
