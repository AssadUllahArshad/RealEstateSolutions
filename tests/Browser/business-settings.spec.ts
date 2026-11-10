import { expect, test } from '@playwright/test';

test('main admin configures an exact owner percentage and reviews policy history', async ({
    page,
}, testInfo) => {
    test.setTimeout(120000);
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/business-settings/owners');
    await expect(page.getByLabel('Default owner share (%)')).toBeVisible();
    const versionLabel = page.getByText(/^Saved version \d+$/);
    const priorVersion = (await versionLabel.count())
        ? await versionLabel.textContent()
        : null;
    const version = priorVersion ? Number(priorVersion.match(/\d+/)?.[0]) : 0;
    await page.getByLabel('Default owner share (%)').fill('25.1256');
    await page
        .getByLabel('Owner percentage applies to')
        .selectOption('cleared_collections');
    await page
        .getByLabel('Owner entitlement is recognized on')
        .selectOption('collection_cleared');
    await page
        .getByLabel('Cancellation/refund handling')
        .selectOption('admin_review');
    await page
        .getByRole('button', { name: 'Save settings', exact: true })
        .click();
    await expect(
        page.getByText(`Saved version ${version + 1}`, { exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel('Default owner share (%)')).toHaveValue(
        '25.1256',
    );
    await page.getByLabel('Default owner share (%)').fill('30.5');
    await page
        .getByRole('button', { name: 'Save settings', exact: true })
        .click();
    await expect(
        page.getByText(`Saved version ${version + 2}`, { exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel('Default owner share (%)')).toHaveValue(
        '30.5000',
    );
    const latestHistory = page
        .getByLabel('Settings history')
        .locator('details')
        .first();
    await latestHistory.locator('summary').click();
    await expect(
        latestHistory.getByText('Before: 25.1256', { exact: true }),
    ).toBeVisible();
    await expect(
        latestHistory.getByText('After: 30.5000', { exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('business-settings.png'),
        fullPage: true,
    });
    await page
        .getByRole('navigation', { name: 'Business settings sections' })
        .getByRole('link', { name: /Late payments and charges/ })
        .click();
    await page
        .getByLabel('Late payment fees', { exact: true })
        .selectOption('disabled');
    const savedCharges = page.waitForResponse(
        (response) =>
            response.request().method() === 'GET' &&
            response.url().endsWith('/business-settings/charges') &&
            response.status() === 200,
    );
    await page
        .getByRole('button', { name: 'Save settings', exact: true })
        .click();
    expect((await (await savedCharges).json()).props.values.late_fee_mode).toBe(
        'disabled',
    );
    await expect(
        page.getByLabel('Late payment fees', { exact: true }),
    ).toHaveValue('disabled');
});
