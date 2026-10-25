import { test, expect } from '@playwright/test';

test('date pickers constrain future dates and invalid ranges immediately', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('admin@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.goto('/visits');
  const from = page.getByLabel('From date', { exact: true });
  const to = page.getByLabel('To date', { exact: true });
  const today = await to.getAttribute('max');
  await from.fill('2099-01-01');
  await expect(from).toHaveAttribute('aria-invalid', 'true');
  await expect(page.locator('#from-date-error')).toHaveText('Future dates cannot be used for this filter.');
  expect(await from.evaluate(el => el.validity.valid)).toBe(false);
  await from.fill('');
  await to.fill('');
  await from.fill('2020-01-01');
  await expect(to).toHaveAttribute('min', '2020-01-01');
  await expect(to).toHaveAttribute('max', '2020-12-31');
  await to.fill('2019-12-31');
  await expect(page.locator('#to-date-error')).toHaveText('From date must be on or before To date.');
  await to.fill('2020-01-02');
  expect(await to.evaluate(el => el.validity.valid)).toBe(true);
  await expect(from).toHaveAttribute('max', '2020-01-02');
  await page.getByRole('button', { name: 'Filter by date', exact: true }).click();
  await expect(page).toHaveURL(/from=2020-01-01&to=2020-01-02/);
  for (const path of ['/analytics', '/reports', '/audit']) {
    await page.goto(path);
    await expect(page.locator('input[name="to"]')).toHaveAttribute('max', today);
    await page.locator('input[name="from"]').fill('2099-01-01');
    await expect(page.locator('#from-date-error')).toBeVisible();
  }
});
