import { test, expect } from '@playwright/test';

test('a short patient history displays every row without a nested vertical scrollbar', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.goto('/patients/2');
  await page.getByRole('link', { name: 'Start visit', exact: true }).click();
  await page.getByLabel('Requested service (required)').selectOption({ label: 'Consultation' });
  await page.getByRole('button', { name: 'Start visit', exact: true }).click();
  await page.goto('/patients/2');
  const queue = page.locator('[data-scroll-queue="visits"]');
  await expect(queue.locator('tbody tr')).toHaveCount(2);
  await expect(queue.getByText('2 records', { exact: true })).toBeVisible();
  for (const viewport of [{ width: 1366, height: 768 }, { width: 390, height: 844 }]) {
    await page.setViewportSize(viewport);
    await queue.scrollIntoViewIfNeeded();
    await expect.poll(() => queue.locator('[data-queue-viewport]').evaluate(el => el.scrollHeight <= el.clientHeight + 1)).toBe(true);
  }
});
