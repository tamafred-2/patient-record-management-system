import { test, expect } from '@playwright/test';

test('dashboard fits desktop and both tables stay inside their cards', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard/);
  // Exercise long content in both independent scroll areas using fictional rows.
  await page.evaluate(() => {
    for (const selector of ['#today-visits tbody', '#follow-ups tbody']) {
      const body = document.querySelector(selector);
      if (selector === '#follow-ups tbody') {
        body.innerHTML = '<tr><td class="p-2">Nov 2, 2026</td><td class="p-2">Fictional Followup Patient<br>DEMO-PATIENT-LONG-REFERENCE</td><td class="p-2">Scheduled return visit with a longer saved reason for layout testing.</td><td class="p-2">Fictional Doctor</td></tr>';
      }
      const row = body.firstElementChild;
      for (let index = 0; index < 24; index++) body.append(row.cloneNode(true));
    }
  });
  for (const [width, height] of [[1920, 947], [1366, 768]]) {
    await page.setViewportSize({ width, height });
    await expect(page.locator('[data-dashboard-panels]')).toHaveClass(/dashboard-panels-fitted/);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollHeight <= innerHeight + 2)).toBe(true);
    for (const selector of ['#today-visits [data-queue-viewport]', '#follow-ups [data-queue-viewport]']) {
      await expect.poll(() => page.locator(selector).evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
      expect(await page.locator(selector).evaluate(el => el.getBoundingClientRect().bottom <= innerHeight)).toBe(true);
      expect(await page.locator(selector).evaluate(el => el.scrollHeight > el.clientHeight)).toBe(true);
    }
  }
  await page.screenshot({ path: 'storage/framework/testing/dashboard-fit.png', fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('[data-dashboard-panels]')).not.toHaveClass(/dashboard-panels-fitted/);
  await expect(page.getByRole('heading', { name: 'Follow-up return visits', exact: true })).toBeVisible();
});
