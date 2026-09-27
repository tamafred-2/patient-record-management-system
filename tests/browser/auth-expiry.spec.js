import { test, expect } from '@playwright/test';

test('stale login and logout forms recover without replaying submissions', async ({ page, context }) => {
  const other = await context.newPage();
  async function signIn(target) {
    await target.goto('/login');
    await target.getByLabel('Email address').fill('admin@rhu.test');
    await target.getByLabel('Password', { exact: true }).fill('Password!');
    await target.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(target).toHaveURL(/dashboard$/);
  }
  async function logout(target) {
    await target.locator('form[action$="/logout"]').evaluate(form => form.requestSubmit());
  }
  await page.goto('/login');
  await signIn(other);
  await logout(other);
  await expect(other).toHaveURL(/login$/);
  await page.getByLabel('Email address').fill('admin@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  const rejectedLogin = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/login'));
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  expect((await rejectedLogin).status()).toBe(303);
  await expect(page.getByRole('alert')).toContainText('Please sign in again');
  await expect(page.getByLabel('Password', { exact: true })).toHaveValue('');
  const reload = await page.reload();
  expect(reload.request().method()).toBe('GET');
  await signIn(page);
  await other.goto('/dashboard');
  await logout(other);
  await expect(other).toHaveURL(/login$/);
  await signIn(other);
  const rejectedLogout = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/logout'));
  await logout(page);
  expect((await rejectedLogout).status()).toBe(303);
  await expect(page).toHaveURL(/dashboard$/);
  await expect(page.getByRole('alert')).toContainText('You are still signed in');
  // Neither JSON requests nor operational writes receive this auth-only recovery.
  expect((await context.request.post('/logout', { headers: { Accept: 'application/json' }, data: { _token: 'stale' } })).status()).toBe(419);
  expect((await context.request.post('/patients', { form: { _token: 'stale' } })).status()).toBe(419);
  await logout(page);
  await expect(page).toHaveURL(/login$/);
  await other.close();
});
