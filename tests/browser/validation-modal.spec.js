import { test, expect } from '@playwright/test';

test('validation modal handles required fields, server errors and keyboard dismissal', async ({ page }) => {
  await page.goto('/login');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  const modal = page.locator('#validation-modal');
  await expect(modal).toBeVisible();
  await expect(modal).toContainText('Email address');
  await expect(modal).toContainText('Password');
  await modal.getByRole('button', { name: 'Return to form' }).click();
  await expect(page.getByLabel('Email address')).toBeFocused();
  await page.getByLabel('Email address').fill('missing-validation-account@example.test');
  await page.getByLabel('Password', { exact: true }).fill('Incorrect-Test-Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(modal).toBeVisible();
  await expect(modal.locator('li')).not.toHaveCount(0);
  await page.keyboard.press('Escape');
  await expect(modal).not.toBeVisible();
  await expect(page.getByLabel('Email address')).toBeFocused();
});
