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
    await expect(page.getByLabel('Your current password', { exact: true })).toHaveCount(0);
    await page.getByRole('checkbox', { name: /I confirm this reset/ }).check();
    await page.getByRole('button', { name: 'Generate and reset password', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Password reset');
    const generated = page.getByLabel('Temporary password', { exact: true });
    await expect(page.getByRole('dialog', { name: 'Generated temporary password' })).toBeVisible();
    await expect(generated).toHaveAttribute('type', 'password');
    const temporary = await generated.inputValue();
    expect(temporary.length).toBe(16);
    await page.getByRole('button', { name: 'Show password', exact: true }).click();
    await expect(generated).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Hide password', exact: true }).click();
    await page.screenshot({ path: 'storage/framework/testing/generated-password-modal.png' });
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(generated).toHaveValue('');
    await expect(page.getByRole('button', { name: 'Generate and reset password', exact: true })).toBeFocused();
    await page.reload();
    await expect(generated).toHaveCount(0);
    await staff.goto('/dashboard');
    await expect(staff).toHaveURL(/login/);
    await oldSession.goto('/dashboard');
    await expect(oldSession).toHaveURL(/login/);
    await login(staff, 'password-browser@rhu.test', temporary);
    await expect(staff).toHaveURL(/password\/change/);
    await staff.goto('/analytics');
    await expect(staff).toHaveURL(/password\/change/);
    await staff.screenshot({ path: 'storage/framework/testing/required-password-change.png', fullPage: true });
    await change(staff, temporary, 'FinalPrivatePassword123!');
    await staff.goto('/analytics');
    await expect(staff.getByRole('heading', { name: 'My department analytics' })).toBeVisible();
    await login(oldSession, 'password-browser@rhu.test', temporary);
    await expect(oldSession).toHaveURL(/login/);
    await login(oldSession, 'password-browser@rhu.test', 'FinalPrivatePassword123!');
    await expect(oldSession).toHaveURL(/dashboard/);
  } finally {
    for (const context of contexts) await context.close();
  }
});
