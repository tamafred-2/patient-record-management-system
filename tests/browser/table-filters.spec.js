import { test, expect } from '@playwright/test';

async function login(page, account) {
  await page.goto('/login');
  await page.getByLabel('Email address').fill(`${account}@rhu.test`);
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard/);
}

test('account filters combine and reset without a stale cursor', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/staff');
  await page.getByRole('searchbox', { name: 'Search users by name or email', exact: true }).fill('nurse@rhu.test');
  await page.getByLabel('Role', { exact: true }).selectOption('Nurse');
  await page.getByLabel('Account status', { exact: true }).selectOption('active');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody')).toContainText('nurse@rhu.test');
  await expect(page).toHaveURL(url => !url.searchParams.has('cursor') && url.searchParams.get('role') === 'Nurse');
  await page.getByRole('link', { name: 'Reset', exact: true }).click();
  await expect(page).toHaveURL(/\/staff$/);
  await expect(page.locator('tbody tr')).not.toHaveCount(1);
});

test('nurse authorization filters and Manage use the existing table link style', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page, 'doctor');
  await page.goto('/nurse-authorizations');
  await expect(page.getByText('Manage nurses’ laboratory ordering access for patients they assessed.')).toBeVisible();
  await page.getByRole('searchbox', { name: 'Search nurses, doctors or permitted tests', exact: true }).fill('nurse@rhu.test');
  await page.getByLabel('Laboratory ordering', { exact: true }).selectOption('off');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  const manage = page.getByRole('link', { name: 'Manage Demo Nurse', exact: true });
  await expect(manage).toHaveClass(/underline/);
  await expect(manage).not.toHaveClass(/rounded-lg/);
  await page.getByRole('searchbox', { name: 'Search nurses, doctors or permitted tests', exact: true }).fill('no matching nurse');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await expect(page.getByText('No nurses match these filters.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('small tables search independently by column and print all rows', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page, 'midwife');
  await page.goto('/midwife-report');
  const controls = page.locator('.table-search-controls').nth(1);
  const table = page.locator('#main table').nth(1);
  const count = await table.locator('tbody tr').count();
  expect(count).toBeGreaterThan(1);
  await controls.getByLabel('Search column').selectOption({ label: 'Care type' });
  await controls.getByLabel('Search displayed rows').fill('no matching entry');
  await expect(table.locator('tbody tr:visible')).toHaveCount(0);
  await expect(controls.getByRole('status')).toContainText('No matching rows.');
  await expect(page.locator('#main table').first().locator('tbody tr:visible')).not.toHaveCount(0);
  await page.emulateMedia({ media: 'print' });
  await expect(table.locator('tbody tr:visible')).toHaveCount(count);
  await expect(controls).toBeHidden();
  await page.emulateMedia({ media: 'screen' });
  await controls.getByRole('button', { name: 'Reset' }).click();
  await expect(table.locator('tbody tr:visible')).toHaveCount(count);
  await expect(controls.getByLabel('Search displayed rows')).toBeFocused();
  expect(errors).toEqual([]);
});
