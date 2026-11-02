import { test, expect } from '@playwright/test';

test('nurse orders laboratory tests directly and medtech receives the attributed multi-test order', async ({ browser, baseURL }) => {
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
    await expect(doctor.getByRole('link', { name: 'Nurse authorization', exact: true })).toHaveCount(0);
    await doctor.goto('/consultations');
    await doctor.getByRole('row').filter({ hasText: 'DEMO-VISIT-1' }).getByRole('link', { name: 'Review visit' }).click();
    await doctor.getByRole('link', { name: 'Order laboratory tests' }).click();
    const url = doctor.url();
    await expect(doctor.getByRole('heading', { name: 'Nurse assessment', exact: true })).toBeVisible();
    await expect(doctor.getByText(/Recorded by /)).toBeVisible();

    // The nurse needs no doctor authorization: the order form is available from the assessment.
    const nurse = await login('nurse');
    await nurse.goto('/assessments?all=1&search=DEMO-PATIENT-1');
    await nurse.getByRole('row').filter({ hasText: 'DEMO-VISIT-1' }).getByRole('link', { name: 'Order lab tests' }).click();
    await expect(nurse).toHaveURL(url);
    const choices = nurse.getByRole('group', { name: 'Tests to order' });
    await choices.getByRole('checkbox', { name: 'Urinalysis' }).check();
    await choices.getByRole('checkbox', { name: 'Complete blood count (CBC)' }).check();
    await nurse.getByLabel('Follow-up findings and reason for test').fill('Fictional nurse observations and request.');
    await nurse.getByRole('checkbox', { name: 'I reviewed the assessment and confirm this request to laboratory.' }).check();
    await nurse.getByRole('button', { name: 'Send tests to laboratory' }).click();
    await expect(nurse.getByRole('status')).toContainText('2 laboratory tests sent to the laboratory as Pending.');
    await nurse.setViewportSize({ width: 390, height: 844 });
    expect(await nurse.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await nurse.screenshot({ path: 'storage/framework/testing/nurse-lab-order-mobile.png', fullPage: true });
    await doctor.goto(url);
    await expect(doctor.getByRole('heading', { name: 'Urinalysis · Pending', exact: true })).toBeVisible();
    await expect(doctor.getByRole('heading', { name: 'Complete blood count (CBC) · Pending', exact: true })).toBeVisible();
    await expect(doctor.getByText('Ordered by nurse:', { exact: true })).toHaveCount(1);
    await nurse.setViewportSize({ width: 1440, height: 1000 });
    await nurse.getByRole('link', { name: 'Print lab request slip' }).click();
    await expect(nurse.getByRole('heading', { name: /Laboratory request/ })).toBeVisible();
    await expect(nurse.getByText('Ordered by nurse', { exact: true })).toBeVisible();
    await nurse.screenshot({ path: 'storage/framework/testing/lab-request-slip.png', fullPage: true });

    const medtech = await login('medtech');
    await medtech.goto('/laboratory?record=with_records');
    const row = medtech.getByRole('row').filter({ hasText: 'DEMO-VISIT-1' });
    await row.getByRole('link').click();
    const request = medtech.getByRole('article').filter({ has: medtech.getByRole('heading', { name: 'Urinalysis', exact: true }) });
    await expect(request).toContainText('Ordered by nurse:');
    await expect(request).toContainText('Demo Nurse');
    await expect(request).toContainText('Fictional nurse observations and request.');
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
