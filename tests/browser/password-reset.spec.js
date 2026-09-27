import { test, expect } from '@playwright/test';

test('admin reset revokes sessions and forces private password replacement', async ({ page, browser, baseURL }) => {
  test.setTimeout(120000);
  const contexts = [];
  async function login(target, email, password) {
    await target.goto('/login');
    await target.getByLabel('Email address').fill(email);
    await target.getByLabel('Password', { exact: true }).fill(password);
    await target.getByRole('button', { name: 'Sign in', exact: true }).click();
  }
  async function change(target, current, replacement) {
    await target.getByLabel('Current password', { exact: true }).fill(current);
    await target.getByLabel('New password', { exact: true }).fill(replacement);
    await target.getByLabel('Confirm new password', { exact: true }).fill(replacement);
    await target.getByRole('button', { name: 'Change password', exact: true }).click();
    await expect(target).toHaveURL(/dashboard/);
  }
  try {
    await login(page, 'admin@rhu.test', 'Password!');
    await expect(page).toHaveURL(/dashboard/);
    await page.goto('/staff/create');
    await page.getByLabel('Full name').fill('Fictional Password Browser');
    await page.getByLabel('Email address').fill('password-browser@rhu.test');
    await page.locator('input[name="password"]').fill('FirstTemporary123!');
    await page.getByLabel('Confirm password', { exact: true }).fill('FirstTemporary123!');
    await page.getByLabel('Nurse', { exact: true }).check();
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).toHaveURL(/\/staff$/);
    const row = page.getByRole('row').filter({ hasText: 'password-browser@rhu.test' });
    const editUrl = await row.getByRole('link').getAttribute('href');
    const first = await browser.newContext({ baseURL }); contexts.push(first);
    const staff = await first.newPage();
    await login(staff, 'password-browser@rhu.test', 'FirstTemporary123!');
    await expect(staff).toHaveURL(/password\/change/);
    await change(staff, 'FirstTemporary123!', 'FirstPrivatePassword123!');
    const second = await browser.newContext({ baseURL }); contexts.push(second);
    const oldSession = await second.newPage();
    await login(oldSession, 'password-browser@rhu.test', 'FirstPrivatePassword123!');
    await expect(oldSession).toHaveURL(/dashboard/);
    await page.goto(editUrl);
    await page.getByLabel('Your current password', { exact: true }).fill('Password!');
    await page.getByLabel('Temporary password', { exact: true }).fill('ResetTemporary123!');
    await page.getByLabel('Confirm temporary password', { exact: true }).fill('ResetTemporary123!');
    await page.getByRole('checkbox', { name: /I confirm this reset/ }).check();
    await page.getByRole('button', { name: 'Reset password', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Password reset');
    await staff.goto('/dashboard');
    await expect(staff).toHaveURL(/login/);
    await oldSession.goto('/dashboard');
    await expect(oldSession).toHaveURL(/login/);
    await login(staff, 'password-browser@rhu.test', 'ResetTemporary123!');
    await expect(staff).toHaveURL(/password\/change/);
    await staff.goto('/analytics');
    await expect(staff).toHaveURL(/password\/change/);
    await staff.screenshot({ path: 'storage/framework/testing/required-password-change.png', fullPage: true });
    await change(staff, 'ResetTemporary123!', 'FinalPrivatePassword123!');
    await staff.goto('/analytics');
    await expect(staff.getByRole('heading', { name: 'My department analytics' })).toBeVisible();
    await login(oldSession, 'password-browser@rhu.test', 'ResetTemporary123!');
    await expect(oldSession).toHaveURL(/login/);
    await login(oldSession, 'password-browser@rhu.test', 'FinalPrivatePassword123!');
    await expect(oldSession).toHaveURL(/dashboard/);
  } finally {
    for (const context of contexts) await context.close();
  }
});
