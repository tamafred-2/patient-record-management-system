import { test, expect } from '@playwright/test';

test('senior visit priority follows the visit date and prints the saved care level', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard/);
  await page.goto('/patients/create');
  const response = await page.request.post('/patients', { form: {
    _token: await page.locator('form[action$="/patients"] input[name="_token"]').inputValue(),
    first_name: 'Fictional', last_name: 'Seniorpriority', birth_date: '1960-01-10',
    sex: 'Female', barangay: 'Example', municipality: 'Calasiao', province: 'Pangasinan',
  } });
  expect(response.ok()).toBeTruthy();
  await page.goto(`${response.url()}/visits/create`);
  const regular = page.locator('input[name="priority"][value="REGULAR"]');
  const priority = page.getByRole('radio', { name: 'Priority', exact: true });
  await expect(regular).toBeHidden();
  await expect(regular).toBeDisabled();
  await expect(priority).toBeChecked();
  await expect(page.locator('#priority_reason')).toHaveValue('SENIOR');
  const date = page.locator('#visit_date');
  const today = await date.inputValue();
  await date.fill('2020-01-09');
  await expect(regular).toBeVisible();
  await expect(regular).toBeEnabled();
  await regular.check();
  await date.fill('2020-01-10');
  await expect(regular).toBeDisabled();
  await expect(priority).toBeChecked();
  await page.getByRole('radio', { name: 'Emergency', exact: true }).check();
  await expect(page.locator('#priority_reason')).toBeDisabled();
  await priority.check();
  await date.fill(today);
  await page.locator('#service_id').selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Start visit', exact: true }).click();
  await expect(page).toHaveURL(/patients\/\d+\/visits\/\d+$/);
  await page.goto(`${page.url()}/stub`);
  await expect(page.getByText('Priority care', { exact: false })).toBeVisible();
  await expect(page.locator('.priority')).toContainText('Senior citizen');
});
