import { test, expect } from '@playwright/test';

test('doctor waits for vitals then requests and receives a fresh nurse assessment', async ({ browser, baseURL }) => {
  const nurseContext = await browser.newContext({ baseURL });
  const doctorContext = await browser.newContext({ baseURL });
  const nurse = await nurseContext.newPage();
  const doctor = await doctorContext.newPage();
  const errors = [];
  nurse.on('pageerror', error => errors.push(error.message));
  doctor.on('pageerror', error => errors.push(error.message));
  async function login(page, role) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(`${role}@rhu.test`);
    await page.getByLabel('Password', { exact: true }).fill('Password!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
  }
  try {
    await login(nurse, 'nurse');
    await login(doctor, 'doctor');
    await nurse.goto('/assessments');
    await nurse.getByRole('row').filter({ hasText: 'DEMO-VISIT-2' }).getByRole('link', { name: 'Open assessment' }).click();
    const assessmentUrl = nurse.url();
    await doctor.goto('/consultations');
    await expect(doctor.getByRole('row').filter({ hasText: 'DEMO-VISIT-2' })).toHaveCount(0);
    await nurse.locator('#pulse_rate').fill('72');
    await nurse.getByLabel('Nurse assessment summary').fill('Fictional initial observations for doctor review.');
    await nurse.getByRole('button', { name: 'Save vital signs' }).click();
    await expect(nurse.getByRole('status')).toBeVisible();
    await doctor.reload();
    await doctor.getByRole('row').filter({ hasText: 'DEMO-VISIT-2' }).getByRole('link', { name: 'Review visit' }).click();
    await expect(doctor.getByText('Fictional initial observations for doctor review.', { exact: true })).toBeVisible();
    await doctor.getByRole('button', { name: /^Reassessment requests/ }).click();
    await doctor.getByLabel('Reason for requesting a new assessment').fill('Fictional repeat measurement request before consultation.');
    await doctor.getByRole('button', { name: 'Request latest nurse assessment' }).click();
    await expect(doctor.getByText(/waiting for nurse\. The values below/)).toBeVisible();
    await doctor.getByRole('button', { name: /^Reassessment requests Pending/ }).click();
    await expect(doctor.getByRole('heading', { name: /waiting for nurse/ })).toBeVisible();
    await doctor.getByRole('button', { name: 'Close reassessment requests' }).click();
    await nurse.goto('/dashboard');
    await nurse.getByRole('link', { name: /Reassessment requests/ }).click();
    await nurse.getByRole('row').filter({ hasText: 'DEMO-VISIT-2' }).getByRole('link', { name: 'Open assessment' }).click();
    await expect(nurse).toHaveURL(assessmentUrl);
    await expect(nurse.getByText('Fictional repeat measurement request before consultation.', { exact: true })).toBeVisible();
    await nurse.locator('#pulse_rate').fill('75');
    await nurse.getByLabel('Nurse assessment summary').fill('Fictional follow-up observations after reassessment.');
    await nurse.getByRole('checkbox', { name: /I have reassessed this patient/ }).check();
    await nurse.getByRole('button', { name: 'Save vital signs' }).click();
    await expect(nurse.getByRole('status')).toBeVisible();
    await expect(nurse.getByRole('heading', { name: 'Reassessment requested', exact: true })).toHaveCount(0);
    await doctor.reload();
    await expect(doctor.getByText('Fictional follow-up observations after reassessment.', { exact: true })).toBeVisible();
    await expect(doctor.getByText('75 bpm', { exact: true })).toBeVisible();
    await expect(doctor.getByRole('heading', { name: /waiting for nurse/ })).toHaveCount(0);
    await doctor.getByRole('button', { name: /^Reassessment requests/ }).click();
    await doctor.getByText('Recent reassessment requests', { exact: true }).click();
    await expect(doctor.getByText('Completed', { exact: true })).toBeVisible();
    await doctor.setViewportSize({ width: 390, height: 844 });
    expect(await doctor.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(errors).toEqual([]);
  } finally {
    await nurseContext.close();
    await doctorContext.close();
  }
});
