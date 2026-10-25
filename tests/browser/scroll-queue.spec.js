import { test, expect } from '@playwright/test';

test('scroll queues fetch ten, bound DOM size, retry, go backwards and preserve filters', async ({ page }) => {
  test.setTimeout(180000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.goto('/patients');
  await page.getByRole('row').filter({ hasText: 'DEMO-PATIENT-1' }).getByRole('link').click();
  const patientUrl = page.url();
  await page.getByRole('link', { name: 'Start visit', exact: true }).click();
  const service = await page.getByLabel('Requested service (required)').locator('option').filter({ hasText: /^Consultation$/ }).getAttribute('value');
  const csrf = await page.locator('form[action$="/logout"] input[name="_token"]').inputValue();
  for (let index = 0; index < 42; index++) {
    const response = await page.request.post(`${patientUrl}/visits`, { form: { _token: csrf, service_id: service, visit_date: '2026-01-15' } });
    expect(response.ok()).toBe(true);
  }
  await page.goto('/visits?all=1&search=Aster');
  for (const [width, height] of [[1920, 947], [1366, 768], [1024, 700], [390, 844]]) {
    await page.setViewportSize({ width, height });
    await expect.poll(() => page.locator('[data-scroll-queue="visits"]').evaluate(el => ({
      fits: el.getBoundingClientRect().bottom <= innerHeight,
      pageFits: document.documentElement.scrollHeight <= innerHeight + 1,
      contained: getComputedStyle(el.querySelector('[data-queue-viewport]')).overscrollBehavior === 'contain',
    }))).toEqual({ fits: true, pageFits: true, contained: true });
  }
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.screenshot({ path: 'storage/framework/testing/viewport-fit-table.png', fullPage: true });
  const queue = page.locator('[data-scroll-queue="visits"]');
  const viewport = queue.locator('[data-queue-viewport]');
  const rows = queue.locator('tbody tr');
  const firstRow = await rows.first().textContent();
  await expect(rows).toHaveCount(10);
  const requests = [];
  page.on('request', request => { if (request.headers()['x-scroll-queue']) requests.push(request.url()); });
  await page.waitForTimeout(200);
  expect(requests).toHaveLength(0);
  await page.route('**/visits?**', route => route.abort(), { times: 1 });
  await viewport.evaluate(element => { element.scrollTop = element.scrollHeight; });
  await expect(queue.locator('[data-queue-status]')).toContainText('Unable to load records');
  await expect(rows).toHaveCount(10);
  await queue.getByRole('link', { name: 'Load more records', exact: true }).click();
  await expect(rows).toHaveCount(20);
  await queue.getByRole('link', { name: 'Load more records', exact: true }).click();
  await expect(rows).toHaveCount(30);
  await queue.getByRole('link', { name: 'Load more records', exact: true }).click();
  await expect(rows.first()).not.toHaveText(firstRow);
  await expect(rows).toHaveCount(30);
  await queue.getByRole('link', { name: 'Load earlier records', exact: true }).click();
  await expect(rows.first()).toHaveText(firstRow);
  await expect(rows).toHaveCount(30);
  expect(requests.every(url => new URL(url).searchParams.get('search') === 'Aster' && new URL(url).searchParams.get('all') === '1')).toBe(true);
  expect(new Set(await rows.allTextContents()).size).toBe(30);
  await page.setViewportSize({ width: 390, height: 844 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: 'storage/framework/testing/scroll-queue-mobile.png', fullPage: true });
  await page.goto('/visits?all=1&search=FICTIONAL-NO-MATCH');
  await expect(page.getByText('No matching visits', { exact: true })).toBeVisible();
  await expect(page.locator('[data-queue-next]')).toBeHidden();
  expect(errors).toEqual([]);
});
