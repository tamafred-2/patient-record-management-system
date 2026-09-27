import { test, expect } from '@playwright/test';

test('today visits scroll after ten rows', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard$/);
  await expect(page.getByRole('region', { name: "Today's visits, scroll to view more" })).toHaveCount(0);
  await page.goto('/patients/1/visits/create');
  const token = await page.locator('form[action$="/visits"] input[name="_token"]').inputValue();
  const service = await page.locator('select[name="service_id"] option').filter({ hasText: 'Consultation' }).getAttribute('value');
  const date = await page.locator('input[name="visit_date"]').inputValue();
  for (let i = 0; i < 11; i++) {
    const response = await page.request.post('/patients/1/visits', { form: { _token: token, service_id: service, visit_date: date, queue_reference: `TEST-${i + 1}` } });
    expect(response.ok()).toBeTruthy();
  }
  await page.goto('/dashboard');
  const region = page.getByRole('region', { name: "Today's visits, scroll to view more" });
  await expect(region).toBeVisible();
  await expect.poll(() => region.evaluate(el => el.scrollHeight > el.clientHeight)).toBe(true);
  await region.evaluate(el => el.scrollTop = el.scrollHeight);
  expect(await region.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
});
