import { test, expect } from '@playwright/test';

test('doctor authorizes a nurse from the nurse list and medtech receives the attributed multi-test order', async ({ browser, baseURL }) => {
  test.setTimeout(90000);
  const contexts = [];
  const errors = [];
  async function login(role) {
    const context = await browser.newContext({ baseURL });
    contexts.push(context);
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill(`${role}@rhu.test`);
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    return page;
  }
  try {
    const doctor = await login('doctor');
    await doctor.goto('/consultations');
    await doctor.getByRole('row').filter({ hasText: 'DEMO-VISIT-1' }).getByRole('link', { name: 'Review visit' }).click();
    await doctor.getByRole('link', { name: 'Order laboratory tests' }).click();
    const url = doctor.url();
    await expect(doctor.getByRole('heading', { name: 'Nurse assessment', exact: true })).toBeVisible();
    await expect(doctor.getByText(/Recorded by /)).toBeVisible();
    await expect(doctor.getByRole('heading', { name: 'Nurse ordering' })).toHaveCount(0);

    await doctor.getByRole('link', { name: 'Nurse authorization', exact: true }).click();
    const nurseRow = doctor.getByRole('row').filter({ hasText: 'Demo Nurse' });
    await expect(nurseRow).toContainText('Off');
    await nurseRow.getByRole('link', { name: 'Manage Demo Nurse' }).click();
    const toggle = doctor.getByRole('switch', { name: 'Demo Nurse' });
    await expect(toggle).toHaveAttribute('aria-checked', 'false');
    await doctor.getByRole('radio', { name: 'Only the tests I select' }).check();
    const permitted = doctor.getByRole('group', { name: 'Permitted tests' });
    await permitted.getByRole('checkbox', { name: 'Urinalysis' }).check();
    await permitted.getByRole('checkbox', { name: 'Complete blood count (CBC)' }).check();
    await doctor.getByLabel('Instructions (optional)').fill('Fictional doctor instructions for nurse follow-up.');
    await doctor.getByRole('button', { name: 'Turn on and notify Demo Nurse' }).click();
    await expect(doctor.getByRole('status')).toContainText('Laboratory ordering turned on for Demo Nurse. They have been notified.');
    await expect(doctor.getByRole('switch', { name: 'Demo Nurse' })).toHaveAttribute('aria-checked', 'true');
    await expect(doctor.getByText('Permitted tests: Complete blood count (CBC), Urinalysis')).toBeVisible();
    await doctor.getByRole('link', { name: 'Back to nurse authorization' }).click();
    await expect(doctor.getByRole('row').filter({ hasText: 'Demo Nurse' })).toContainText('Complete blood count (CBC), Urinalysis');
    await doctor.screenshot({ path: 'storage/framework/testing/nurse-authorization-list.png', fullPage: true });

    const nurse = await login('nurse');
    await expect(nurse.getByText(/You can order laboratory tests for \d+ visits?/)).toHaveCount(0);
    await expect(nurse.getByText('Open authorized visits', { exact: true })).toHaveCount(0);
    await nurse.getByRole('button', { name: /^Notifications/ }).click();
    const bellPanel = nurse.getByRole('region', { name: 'Recent notifications' });
    await bellPanel.getByRole('link', { name: /Demo Doctor.*turned on your laboratory ordering authorization/ }).click();
    await expect(nurse).toHaveURL(/assessments\?authorized=1/);
    await expect(nurse.getByText(/Your authorized follow-up visits:/)).toHaveCount(0);
    await expect(nurse.getByText(/Pending reassessment requests:/)).toHaveCount(0);
    await nurse.getByRole('row').filter({ hasText: 'DEMO-VISIT-1' }).getByRole('link', { name: 'Order follow-up tests' }).click();
    await expect(nurse).toHaveURL(url);
    await expect(nurse.getByRole('button', { name: 'Notifications', exact: true })).toBeVisible();
    await expect(nurse.getByText('Fictional doctor instructions for nurse follow-up.')).toBeVisible();
    const choices = nurse.getByRole('group', { name: 'Tests to order' });
    await expect(choices.getByRole('checkbox')).toHaveCount(2);
    await choices.getByRole('checkbox', { name: 'Urinalysis' }).check();
    await choices.getByRole('checkbox', { name: 'Complete blood count (CBC)' }).check();
    await nurse.getByLabel('Follow-up findings and reason for test').fill('Fictional nurse follow-up observations and request.');
    await nurse.getByRole('checkbox', { name: 'I reviewed the assessment and confirm this request to laboratory.' }).check();
    await nurse.getByRole('button', { name: 'Send authorized tests to laboratory' }).click();
    await expect(nurse.getByRole('status')).toContainText('2 laboratory tests sent to the laboratory as Pending.');
    await expect(nurse.getByRole('button', { name: 'Send authorized tests to laboratory' })).toHaveCount(1);
    await nurse.setViewportSize({ width: 390, height: 844 });
    expect(await nurse.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await nurse.screenshot({ path: 'storage/framework/testing/authorized-followup-mobile.png', fullPage: true });
    await doctor.goto(url);
    await expect(doctor.getByRole('heading', { name: 'Urinalysis · Pending', exact: true })).toBeVisible();
    await expect(doctor.getByRole('heading', { name: 'Complete blood count (CBC) · Pending', exact: true })).toBeVisible();
    await expect(doctor.getByText('Followed up by nurse:', { exact: true })).toHaveCount(1);
    await nurse.setViewportSize({ width: 1440, height: 1000 });
    await nurse.getByRole('link', { name: 'Print lab request slip' }).click();
    await expect(nurse.getByRole('heading', { name: /Laboratory request/ })).toBeVisible();
    await expect(nurse.getByText('Add-on order by authorized nurse')).toBeVisible();
    await expect(nurse.getByText('Demo Doctor', { exact: false }).first()).toBeVisible();
    await nurse.screenshot({ path: 'storage/framework/testing/lab-request-slip.png', fullPage: true });

    const medtech = await login('medtech');
    await medtech.goto('/laboratory?record=with_records');
    const row = medtech.getByRole('row').filter({ hasText: 'DEMO-VISIT-1' });
    await row.getByRole('link').click();
    const request = medtech.getByRole('article').filter({ has: medtech.getByRole('heading', { name: 'Urinalysis', exact: true }) });
    await expect(request).toContainText('Demo Doctor');
    await expect(request).toContainText('Demo Nurse');
    await expect(request).toContainText('Fictional nurse follow-up observations and request.');
    await expect(medtech.getByRole('article').filter({ has: medtech.getByRole('heading', { name: 'Complete blood count (CBC)', exact: true }) })).toContainText('Demo Nurse');
    await request.getByRole('link', { name: 'Record results / update request' }).click();
    await expect(medtech.getByLabel('Requested test / service', { exact: true })).toBeDisabled();
    await medtech.getByLabel('Service status', { exact: true }).selectOption('EXTERNAL_ADVISED');
    await medtech.getByLabel('External laboratory advice', { exact: true }).fill('Fictional availability advice for browser testing.');
    await medtech.getByRole('button', { name: 'Save laboratory service' }).click();
    await expect(medtech.getByRole('status')).toBeVisible();
    await nurse.goto(url);
    await expect(nurse.getByRole('heading', { name: 'Urinalysis · External laboratory advised', exact: true })).toBeVisible();
    expect(errors).toEqual([]);
  } finally {
    for (const context of contexts) await context.close();
  }
});
