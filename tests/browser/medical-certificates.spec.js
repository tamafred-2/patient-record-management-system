import { test, expect } from '@playwright/test';

test('doctor prepares and prints both reference certificate templates', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('dialog', dialog => dialog.accept());
  await page.goto('/login');
  await page.getByLabel('Email address').fill('doctor@rhu.test');
  await page.getByLabel('Password', { exact: true }).fill('Password!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/dashboard/);
  await page.goto('/consultations?all=1');
  await page.getByRole('link', { name: 'Review visit', exact: true }).first().click();
  await page.getByRole('link', { name: 'Medical certificates', exact: true }).click();
  const formUrl = page.url();
  const doctorName = await page.locator('#signatory_name').inputValue();
  for (const template of ['consultation', 'examination']) {
    await page.goto(formUrl);
    await page.getByLabel('Certificate template').selectOption(template);
    await page.getByRole('button', { name: 'Use selected template wording' }).click();
    const body = page.locator('#body');
    const wording = (await body.inputValue()).replace('____________________', template === 'consultation' ? 'the condition documented during consultation' : 'as described in the findings below').replaceAll('_____', 'Example');
    await body.fill(wording);
    const preview = page.frameLocator('[data-certificate-preview]');
    await expect(preview.locator('[data-certificate-field="body"]')).toHaveText(wording);
    await expect(preview.locator('header')).toContainText('Municipality of Calasiao');
    await expect(preview.locator('header img')).toHaveCount(2);
    await expect(preview.locator('h1')).toHaveText('MUNICIPAL HEALTH OFFICE');
    await page.locator('#findings').fill('Fictional test findings supplied by the examining doctor.');
    await page.locator('#remarks').fill('Fictional print-layout test only.');
    await expect(page.locator('#signatory_name')).toHaveValue(doctorName);
    await expect(page.locator('#signatory_name')).toHaveAttribute('readonly', '');
    await page.locator('#license_number').fill('1234567');
    if (template === 'examination') {
      await expect(page.locator('#blood_pressure')).toHaveText('120/80');
      await expect(page.locator('#pulse_rate')).toHaveText('70');
      await expect(page.locator('#respiratory_rate')).toHaveText('18');
      await expect(page.locator('#temperature')).toHaveText('36.50');
    } else {
      await expect(page.locator('#blood_pressure')).toBeHidden();
    }
    await page.getByRole('checkbox', { name: /I reviewed the patient/ }).check();
    if (template === 'consultation') {
      await body.fill('This examination finding is ____________________.');
      await page.getByRole('button', { name: 'Prepare for print / PDF' }).click();
      const validation = page.locator('#validation-modal');
      await expect(validation).toBeVisible();
      await expect(validation).toContainText('Complete Certificate wording');
      await validation.getByRole('button', { name: 'Return to form' }).click();
      await expect(page.locator('#body')).toBeFocused();
      await page.locator('#body').fill(wording);
      await page.getByRole('checkbox', { name: /I reviewed the patient/ }).check();
    }
    await page.getByRole('button', { name: 'Prepare for print / PDF' }).click();
    await expect(page).toHaveURL(/\/print$/);
    await expect(page.locator('.paper')).toContainText(wording);
    expect(await page.locator('header img').evaluateAll(images => images.every(img => img.complete && img.naturalWidth > 0))).toBe(true);
    await page.emulateMedia({ media: 'print' });
    await page.setViewportSize({ width: 986, height: 688 });
    await expect(page.getByRole('button', { name: 'Print / Save as PDF' })).toBeHidden();
    // Printable height on A4 landscape after the configured top/bottom margins.
    expect(await page.locator('.paper').evaluate(element => element.getBoundingClientRect().height)).toBeLessThan(688);
    await page.screenshot({ path: `storage/framework/testing/certificate-${template}-print.png`, fullPage: true });
    await page.emulateMedia({ media: 'screen' });
    await page.reload();
    await expect(page.locator('.paper')).toContainText(wording);
  }
  await page.goto(formUrl);
  await expect(page.getByRole('heading', { name: 'Saved certificates' })).toHaveCount(0);
  await expect(page.locator('#signatory_name')).toHaveValue(doctorName);
  const size = await page.locator('[data-certificate-preview-container]').evaluate(el => ({ width: el.clientWidth, paper: el.querySelector('iframe').getBoundingClientRect().width }));
  expect(Math.abs(size.width - size.paper)).toBeLessThan(2);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.frameLocator('[data-certificate-preview]').locator('[data-certificate-field="signatory_name"]')).toHaveText(doctorName);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  expect(errors).toEqual([]);
});
