import { test, expect } from '@playwright/test';

test('login uses native required fields and a simple inline credential alert', async ({ page }) => {
  await page.goto('/login');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page.locator('#validation-modal')).toHaveCount(0);
  await expect(page.getByLabel('Email address')).toBeFocused();
  await page.getByLabel('Email address').fill('missing-validation-account@example.test');
  await page.getByLabel('Password', { exact: true }).fill('Incorrect-Test-Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page.getByRole('alert')).toHaveText('Invalid email or password.');
  await expect(page.locator('#validation-modal')).toHaveCount(0);
  await expect(page.getByLabel('Email address')).toHaveValue('missing-validation-account@example.test');
  await expect(page.getByLabel('Password', { exact: true })).toHaveValue('');
});
