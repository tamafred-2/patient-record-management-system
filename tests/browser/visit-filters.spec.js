import { test, expect } from '@playwright/test';

for (const [path, account, range] of [
  ['/visits', 'admin', true],
  ['/consultations', 'doctor', false],
  ['/assessments', 'nurse', false],
  ['/laboratory', 'medtech', false],
  ['/pharmacy', 'pharmacist', false],
  ['/eligibility', 'admin', false],
  ['/midwife-care', 'midwife', false],
  ['/vaccinations', 'midwife', false],
]) {
  test(`separate search and date filters on ${path}`, async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(account + '@rhu.test');
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    await page.goto(path);
    const dateForm = page.getByRole('form', { name: 'Filter visits by date' });
    const searchForm = page.getByRole('form', { name: 'Search visits' });
    const dateName = range ? 'From date' : 'Specific date';
    const originalDate = await dateForm.getByLabel(dateName, { exact: true }).inputValue();
    await dateForm.getByLabel(dateName, { exact: true }).fill('2000-01-01');
    await searchForm.getByLabel('Patient name or queue number').fill('DEMO-NOMATCH');
    await searchForm.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page).toHaveURL(url => url.searchParams.get('search') === 'DEMO-NOMATCH' && url.searchParams.get(range ? 'from' : 'date') === originalDate);
    await dateForm.getByLabel(dateName, { exact: true }).fill('2000-01-01');
    if (range) await dateForm.getByLabel('To date').fill('2000-01-02');
    await dateForm.getByRole('button', { name: 'Filter by date' }).click();
    await expect(page).toHaveURL(url => url.searchParams.get('search') === 'DEMO-NOMATCH' && url.searchParams.get(range ? 'from' : 'date') === '2000-01-01' && !url.searchParams.has('page'));
    await searchForm.getByLabel('Patient name or queue number').fill('');
    await searchForm.getByLabel('Patient name or queue number').press('Enter');
    await expect(page).toHaveURL(url => !url.searchParams.get('search') && url.searchParams.get(range ? 'from' : 'date') === '2000-01-01');
    await expect(page.getByRole('region', { name: /scroll to view more/ })).toHaveJSProperty('clientHeight', 744);
    await expect(page.getByRole('status').getByText('No matching visits', { exact: true })).toBeVisible();
    {
      await dateForm.getByLabel(range ? 'Date scope' : 'Visit date', { exact: true }).selectOption('1');
      await expect(dateForm.getByLabel(dateName, { exact: true })).toBeHidden();
      await dateForm.getByRole('button', { name: 'Filter by date' }).click();
      await expect(page).toHaveURL(url => url.searchParams.get('all') === '1' && !url.searchParams.has('page'));
      await expect(page.locator('p').filter({ hasText: /^All dates/ })).toBeVisible();
      await dateForm.getByLabel(range ? 'Date scope' : 'Visit date', { exact: true }).selectOption('0');
      await dateForm.getByLabel(dateName, { exact: true }).fill('2000-01-01');
      if (range) await dateForm.getByLabel('To date').fill('2000-01-02');
      await dateForm.getByRole('button', { name: 'Filter by date' }).click();
      await expect(page).toHaveURL(url => url.searchParams.get('all') === '0' && url.searchParams.get(range ? 'from' : 'date') === '2000-01-01');
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(dateForm.getByRole('button', { name: 'Filter by date' })).toBeVisible();
    await expect(searchForm.getByRole('button', { name: 'Search', exact: true })).toBeVisible();
  });
}
