import { expect, test } from '@playwright/test';

for (const user of [
    { role: 'office admin', email: 'demo.office-admin@estateflow.test' },
    { role: 'accountant', email: 'demo.accountant@estateflow.test' },
]) {
    test(`${user.role} can access the sales-agent workspace`, async ({
        page,
    }) => {
        await page.goto('/login');
        await page.getByLabel('Email address').fill(user.email);
        await page
            .getByLabel('Password', { exact: true })
            .fill(process.env.E2E_PASSWORD ?? '');
        await page
            .getByRole('button', { name: 'Sign in to EstateFlow' })
            .click();
        await expect(page).toHaveURL(/\/dashboard$/);

        for (const [path, title] of [
            ['/inventory', 'Plot inventory'],
            ['/customers', 'Customers'],
            ['/sales', 'Sales desk'],
            ['/finance/receipts', 'Customer receipts'],
        ]) {
            await page.goto(path);
            await expect(
                page.getByRole('heading', { name: title, exact: true }),
            ).toBeVisible();
        }
    });
}
