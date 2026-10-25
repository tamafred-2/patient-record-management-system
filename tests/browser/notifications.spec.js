import { test, expect } from '@playwright/test';

test('open pages receive new work alerts without reloading', async ({ browser, baseURL }) => {
  test.setTimeout(120000);
  const contexts = [];
  const errors = [];
  async function login(role) {
    const context = await browser.newContext({ baseURL });
    contexts.push(context);
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill(`${role}@rhu.test`);
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    return page;
  }
  try {
    const nurse = await login('nurse');
    const doctor = await login('doctor');
    const staff = await login('information');
    // Let both open pages take their first (baseline) poll before anything new arrives.
    await nurse.waitForTimeout(1500);

    await staff.goto('/patients');
    await staff.getByLabel('Patient number or name', { exact: true }).fill('DEMO-PATIENT-1');
    await staff.getByRole('button', { name: 'Search', exact: true }).click();
    await staff.getByRole('row').filter({ hasText: 'DEMO-PATIENT-1' }).getByRole('link').first().click();
    await staff.getByRole('link', { name: 'Start visit', exact: true }).click();
    await staff.getByLabel('Requested service (required)').selectOption({ label: 'Consultation' });
    await staff.getByRole('radio', { name: 'Emergency' }).check();
    await staff.getByRole('button', { name: 'Start visit', exact: true }).click();
    await expect(staff.getByText('EMERGENCY').first()).toBeVisible();

    for (const page of [nurse, doctor]) {
      await page.mouse.move(10, 10);
      const toast = page.locator('#notification-toasts [role="alert"]');
      await expect(toast).toContainText('EMERGENCY visit: Fictional Aster Demo', { timeout: 45000 });
      await expect(page.locator('[data-unread-badge]')).toBeVisible();
    }
    await doctor.getByRole('button', { name: /^Notifications/ }).click();
    const panel = doctor.getByRole('region', { name: 'Recent notifications' });
    await expect(panel).toContainText('EMERGENCY visit: Fictional Aster Demo');
    await expect(panel.getByRole('link', { name: 'View all notifications' })).toBeVisible();
    await doctor.screenshot({ path: 'storage/framework/testing/notification-bell-panel.png' });
    await doctor.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await nurse.locator('#notification-toasts a').first().click();
    await expect(nurse).toHaveURL(/assessments\//);
    await expect(staff.locator('[data-unread-badge]')).toBeHidden();
    expect(errors).toEqual([]);
  } finally {
    for (const context of contexts) await context.close();
  }
});
