import { test, expect } from '@playwright/test';

test('password help modal and session-only login', async ({ page, context }) => {
  await page.goto('/login');
  await page.getByRole('link', { name: 'Forgot password?' }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page).toHaveURL(/login$/);
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).not.toBeVisible();
  await expect(page.getByRole('link', { name: 'Forgot password?' })).toBeFocused();
  await page.getByRole('link', { name: 'Forgot password?' }).click();
  await page.getByRole('button', { name: 'Close password help' }).click();
  await expect(page.getByRole('dialog')).not.toBeVisible();
  await expect(page.getByLabel('Remember me')).toHaveCount(0);
  await page.getByLabel('Email address').fill('admin@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard$/);
  const remembered = (await context.cookies()).filter(cookie => cookie.name.startsWith('remember_'));
  expect(remembered).toHaveLength(0);
  await context.clearCookies();
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/login$/);
});
