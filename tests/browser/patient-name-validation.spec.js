import { test, expect } from '@playwright/test';

test('patient and guardian names show immediate digit errors and recover after correction', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('information@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard/);
  await page.goto('/patients/create');

  for (const name of ['first_name', 'last_name', 'middle_name', 'emergency_contact_name', 'guardian_name', 'guardian_relationship']) {
    const input = page.locator(`#${name}`);
    const error = page.locator(`#${name}-number-error`);
    await input.fill('Juan');
    await input.pressSequentially('123');
    await expect(error).toBeVisible();
    await expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(await input.evaluate(element => element.validity.customError)).toBe(true);
    await expect(input).toBeFocused();
    for (const value of ['0', 'Juan１２', 'Juan١٢']) {
      await input.fill(value);
      await expect(error).toBeVisible();
    }
    await input.fill("José D'Angelo-Santos");
    await expect(error).toBeHidden();
    await expect(input).not.toHaveAttribute('aria-invalid');
    expect(await input.evaluate(element => element.validity.valid)).toBe(true);
  }

  await page.locator('#middle_name').fill('');
  expect(await page.locator('#middle_name').evaluate(element => element.validity.valid)).toBe(true);
  for (const name of ['contact_number', 'emergency_contact_number', 'guardian_phone']) {
    const input = page.locator(`#${name}`);
    const error = page.locator(`#${name}-number-error`);
    for (const value of ['0', '0000000000', '000000000000', '0000000000a', '00000-00000', '０００００００００００']) {
      await input.fill(value);
      await expect(error).toBeVisible();
      await expect(input).toHaveAttribute('aria-invalid', 'true');
      expect(await input.evaluate(element => element.validity.valid)).toBe(false);
    }
    await input.fill('00000000000');
    await expect(error).toBeHidden();
    expect(await input.evaluate(element => element.validity.valid)).toBe(true);
    await expect(input).toHaveValue('00000000000');
    await input.fill('');
    await expect(error).toBeHidden();
    expect(await input.evaluate(element => element.validity.valid)).toBe(true);
  }
  await page.locator('#first_name').fill('Juan123');
  await page.getByRole('button', { name: 'Register patient', exact: true }).click();
  await expect(page).toHaveURL(/patients\/create$/);
  // The validation summary opens first; closing it returns focus to the first invalid field.
  await page.getByRole('button', { name: 'Return to form', exact: true }).click();
  await expect(page.locator('#first_name')).toBeFocused();
});
