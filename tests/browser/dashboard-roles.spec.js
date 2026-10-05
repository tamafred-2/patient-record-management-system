import { test, expect } from '@playwright/test';

for (const [role, sections] of Object.entries({
  admin: ['visits'], doctor: ['visits'], nurse: ['assessments'], medtech: ['laboratory'],
  pharmacist: ['pharmacy'], midwife: ['midwife', 'vaccinations'],
})) {
  test(`${role} dashboard uses fixed scroll tables and authorized sections`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill(`${role}@rhu.test`);
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    await expect(page.locator('article a')).toHaveCount(await page.locator('article').count());
    if (role === 'doctor') {
      await page.getByRole('link', { name: /^Visits today:/ }).click();
      await expect(page).toHaveURL(/#today-visits$/);
      await page.getByRole('link', { name: /^Without saved ITR:/ }).click();
      await expect(page).toHaveURL(/record=without_itr/);
      await expect(page.getByText(/All dates.*Without saved ITR/)).toBeVisible();
      await expect(page.getByLabel('Record filter', { exact: true })).toHaveValue('without_itr');
      await page.getByLabel('Record filter', { exact: true }).selectOption('issued');
      await page.getByRole('button', { name: 'Apply record filter', exact: true }).click();
      await expect(page).toHaveURL(/all=1.*record=issued/);
      await expect(page.getByText(/All dates.*Issued prescriptions/)).toBeVisible();
      await page.getByLabel('Record filter', { exact: true }).selectOption('');
      await page.getByRole('button', { name: 'Apply record filter', exact: true }).click();
      await expect(page.locator('p').filter({ hasText: /^All dates/ })).toBeVisible();
      await expect(page.getByLabel('Record filter', { exact: true })).toHaveValue('');
      const doctorTable = page.getByRole('region', { name: 'Doctor visits, scroll to view more' });
      await expect(doctorTable).toHaveJSProperty('clientHeight', 744);
      await page.getByLabel('Patient name or queue number', { exact: true }).fill('FICTIONAL-NONMATCH-999');
      await page.getByRole('button', { name: 'Search', exact: true }).click();
      await expect(doctorTable.getByText('No matching visits', { exact: true })).toBeVisible();
      await expect(doctorTable).toHaveJSProperty('clientHeight', 744);
      await page.goto('/dashboard');
    }
    for (const section of sections) {
      const panel = page.locator(`#today-${section}`);
      await expect(panel).toBeVisible();
      await expect(panel.getByRole('region')).toHaveJSProperty('clientHeight', 744);
      const response = await page.request.get(`/dashboard/visits?section=${section}`);
      expect(response.ok()).toBe(true);
    }
    await page.locator('#today-search').fill('FICTIONAL-NONMATCH-999');
    await page.getByRole('button', { name: 'Search visits', exact: true }).click();
    for (const section of sections) {
      const panel = page.locator(`#today-${section}`);
      await expect(panel.getByText('No matching visits.', { exact: true })).toBeVisible();
      await expect(panel.getByRole('region')).toHaveJSProperty('clientHeight', 744);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(errors).toEqual([]);
  });
}
