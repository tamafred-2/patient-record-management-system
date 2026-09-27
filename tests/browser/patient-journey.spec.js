import { test, expect } from '@playwright/test';

test('one fictional patient passes through all implemented operational roles', async ({ browser, baseURL }) => {
  test.setTimeout(180000);
  let context;
  const errors = [];
  async function asRole(role) {
    if (context) await context.close();
    context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill(role + '@rhu.test');
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    return page;
  }
  try {
    let page = await asRole('information');
    await page.goto('/patients/create');
    await page.getByLabel('First name (required)', { exact: true }).fill('Fictional Journey');
    await page.getByLabel('Last name (required)', { exact: true }).fill('Browser');
    await page.getByRole('button', { name: 'Register patient', exact: true }).click();
    await expect(page).toHaveURL(/\/patients\/\d+$/);
    const patient = new URL(page.url()).pathname.split('/').pop();
    await page.getByRole('link', { name: 'Start visit', exact: true }).click();
    await page.getByLabel('Requested service (required)').selectOption({ label: 'Consultation' });
    await page.getByLabel('Existing queue reference (required)').fill('TEST-JOURNEY-001');
    await page.getByRole('button', { name: 'Start visit', exact: true }).click();
    await expect(page).toHaveURL(/\/visits\/\d+$/);
    const visit = new URL(page.url()).pathname.split('/').pop();
    const scope = patient + '/visits/' + visit;

    page = await asRole('nurse');
    await page.goto('/assessments/' + scope);
    await page.getByLabel('Systolic BP (mmHg)', { exact: true }).fill('120');
    await page.getByLabel('Diastolic BP (mmHg)', { exact: true }).fill('80');
    await page.getByLabel('Temperature (Celsius)', { exact: true }).fill('36.5');
    await page.getByRole('button', { name: 'Save vital signs' }).click();
    await expect(page.getByRole('status')).toBeVisible();

    page = await asRole('admin');
    await page.goto('/eligibility/' + scope);
    await page.getByRole('radio', { name: /^With record/ }).check();
    await page.getByLabel('Remarks (optional)', { exact: true }).fill('Fictional browser test, no external system checked.');
    await page.getByRole('button', { name: 'Save confirmation' }).click();
    await expect(page.getByRole('status')).toBeVisible();
    expect((await page.goto('/consultations/' + scope)).status()).toBe(403);

    page = await asRole('doctor');
    await page.goto('/consultations/' + scope);
    await expect(page.getByText('With record', { exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Open Digital ITR' }).click();
    await page.getByRole('button', { name: 'Edit notes', exact: true }).click();
    await page.getByLabel('Assessment', { exact: true }).fill('Fictional journey assessment only.');
    await page.getByLabel('Planning', { exact: true }).fill('Fictional journey plan only.');
    await page.getByLabel('Remarks', { exact: true }).fill('Fictional journey remarks only.');
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    await page.getByRole('button', { name: 'Edit diagnoses', exact: true }).click();
    const diagnoses = page.getByLabel('Diagnoses for analytics (optional)', { exact: true });
    await diagnoses.fill('x'.repeat(151));
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    await page.getByRole('button', { name: 'Save ITR', exact: true }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(diagnoses).toHaveValue('x'.repeat(151));
    await expect(page.getByRole('dialog')).toContainText('Each diagnosis must be no more than 150 characters.');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'storage/framework/testing/diagnoses-modal-mobile.png' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await diagnoses.fill('Fictional journey condition');
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    await page.getByRole('button', { name: 'Save ITR', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.getByRole('button', { name: 'Edit notes', exact: true }).click();
    await expect(page.getByLabel('Assessment', { exact: true })).toHaveValue('Fictional journey assessment only.');
    await expect(page.getByLabel('Planning', { exact: true })).toHaveValue('Fictional journey plan only.');
    await expect(page.getByLabel('Remarks', { exact: true })).toHaveValue('Fictional journey remarks only.');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'storage/framework/testing/itr-notes-modal-mobile.png' });
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByRole('button', { name: 'Edit diagnoses', exact: true }).click();
    await expect(diagnoses).toHaveValue('Fictional journey condition');
    await diagnoses.fill('');
    await page.getByLabel('Previously recorded diagnosis', { exact: true }).selectOption('fictional journey condition');
    await page.getByRole('button', { name: 'Use diagnosis label' }).click();
    await page.getByRole('button', { name: 'Use diagnosis label' }).click();
    await expect(diagnoses).toHaveValue('fictional journey condition');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await page.getByRole('button', { name: 'Save ITR', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.goto('/prescriptions/' + scope);
    await page.getByLabel('Medicine name', { exact: true }).fill('Fictional journey medicine');
    await page.getByLabel('Dosage', { exact: true }).fill('Example only');
    await page.getByLabel('Frequency', { exact: true }).fill('Example only');
    await page.getByLabel('Quantity prescribed', { exact: true }).fill('2');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Issue saved prescription' }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.goto('/supporting/' + scope);
    await page.getByLabel('Receiving facility (optional)', { exact: true }).fill('Fictional referral facility');
    await page.getByLabel('Specialty (optional)', { exact: true }).fill('Fictional specialty');
    await page.getByLabel('Record type', { exact: true }).selectOption('CERTIFICATE_REQUEST');
    await expect(page.getByLabel('Receiving facility (optional)', { exact: true })).not.toBeVisible();
    await expect(page.getByLabel('Receiving facility (optional)', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Specialty (optional)', { exact: true })).toBeDisabled();
    await page.getByLabel('Record type', { exact: true }).selectOption('ADVISED_HIGHER_FACILITY');
    await expect(page.getByLabel('Receiving facility (optional)', { exact: true })).toHaveValue('Fictional referral facility');
    await expect(page.getByLabel('Reason for higher-facility advice (required)')).toBeVisible();
    await page.getByLabel('Record type', { exact: true }).selectOption('CERTIFICATE_REQUEST');
    await page.getByLabel('Certificate request purpose (required)').fill('Fictional journey request');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Save certificate request' }).click();
    await expect(page.getByText('Fictional journey request', { exact: true })).toBeVisible();

    page = await asRole('medtech');
    await page.goto('/laboratory/' + scope);
    await page.getByLabel('Requested test / service (required)').fill('Fictional journey laboratory service');
    await page.getByLabel('Service status (required)').selectOption('EXTERNAL_ADVISED');
    await page.getByLabel('External laboratory advice', { exact: true }).fill('Fictional external advice only');
    await page.getByRole('button', { name: 'Save laboratory service' }).click();
    await expect(page.getByRole('heading', { name: 'Fictional journey laboratory service' })).toBeVisible();

    page = await asRole('pharmacist');
    await page.goto('/pharmacy/' + scope);
    for (const state of ['Partially dispensed', 'Fully dispensed']) {
      await page.getByLabel('Quantity released now', { exact: true }).fill('1');
      await page.getByRole('checkbox').check();
      await page.getByRole('button', { name: 'Record release', exact: true }).click();
      await expect(page.getByText(state, { exact: true })).toBeVisible();
    }
    await expect(page.getByRole('button', { name: 'Record release', exact: true })).toHaveCount(0);

    page = await asRole('information');
    await page.goto('/patients/' + scope);
    await page.getByRole('link', { name: 'Review and complete visit', exact: true }).click();
    await expect(page.getByText('Issued: all prescribed quantities released', { exact: true })).toBeVisible();
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Complete visit', exact: true }).click();
    await expect(page.getByText('COMPLETED', { exact: true })).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/visit-completed.png', fullPage: true });

    page = await asRole('doctor');
    await page.goto('/history/' + patient);
    await expect(page.getByText('Medicine release recorded').first()).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/patient-journey-history.png', fullPage: true });
    page = await asRole('admin');
    await page.goto('/analytics');
    await page.getByRole('button', { name: 'Flip Most recorded diagnoses card', exact: true }).click();
    await expect(page.getByRole('row').filter({ hasText: 'fictional journey condition' })).toContainText('1');
    await page.goto('/audit');
    await expect(page.getByRole('cell', { name: 'Medicine release recorded', exact: true }).first()).toBeVisible();
    expect(errors).toEqual([]);
  } finally {
    if (context) await context.close();
  }
});
