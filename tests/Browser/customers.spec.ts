import { expect, test } from '@playwright/test';

test('agent manages a customer, schedules and completes a follow-up, and finds the record globally', async ({
    page,
}, testInfo) => {
    test.setTimeout(150000);
    const name = `DEMO Customer ${testInfo.project.name}`;
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.agent@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/customers');
    await page
        .getByRole('button', { name: 'Add customer', exact: true })
        .first()
        .click();
    await page.getByLabel('Full name').fill(name);
    await page.getByLabel('Phone number').fill('0300-0000000');
    await expect(page.getByLabel('Assigned staff member')).toHaveCount(0);
    await page
        .getByRole('button', { name: 'Create customer', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    const profileUrl = page.url();
    await page
        .getByRole('button', { name: 'Edit customer', exact: true })
        .click();
    await page.getByLabel('Phone number').fill('0321-0000000');
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click();
    await expect(
        page.getByRole('link', { name: '0321-0000000', exact: true }),
    ).toBeVisible();
    await page.getByLabel('Follow-up note').fill('DEMO arrange a site visit');
    await page.getByLabel('Due date (Karachi)').fill('2026-11-05');
    await page.getByRole('button', { name: 'Save follow-up' }).click();
    await expect(
        page.getByText('DEMO arrange a site visit', { exact: true }),
    ).toBeVisible();
    await page.goto('/follow-ups');
    await expect(
        page.getByRole('cell', {
            name: 'DEMO arrange a site visit',
            exact: true,
        }),
    ).toBeVisible();
    await page.goto(profileUrl);
    await page.getByRole('button', { name: 'Complete', exact: true }).click();
    await expect(page.getByText('Completed', { exact: true })).toBeVisible();
    await page.getByLabel('Search plots and customers').fill(name);
    await page.getByLabel('Search plots and customers').press('Enter');
    await expect(page).toHaveURL(/\/search\?q=/);
    await page.getByRole('link', { name: new RegExp(name) }).click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('customer-profile.png'),
        fullPage: true,
    });
});
