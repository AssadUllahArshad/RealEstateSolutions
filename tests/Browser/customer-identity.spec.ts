import { expect, test } from '@playwright/test';

test('assigned staff records identity and uploads and downloads a private customer photograph', async ({
    page,
}, testInfo) => {
    test.setTimeout(150000);
    const name = `DEMO Private Identity ${testInfo.project.name}`;
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
    await page.getByLabel('CNIC', { exact: true }).fill('35202-1234567-1');
    await page
        .getByLabel('Address', { exact: true })
        .fill('DEMO private address for browser testing');
    await page
        .getByRole('button', { name: 'Create customer', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('35202-1234567-1', { exact: true }),
    ).toBeVisible();
    await page.getByLabel('Document type').selectOption('customer_photo');
    await page.getByLabel('Upload file').setInputFiles({
        name: 'synthetic-photo.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jT1cAAAAASUVORK5CYII=',
            'base64',
        ),
    });
    await page
        .getByRole('button', { name: 'Upload document', exact: true })
        .click();
    await expect(
        page.getByRole('link', { name: /Download Customer photograph/ }),
    ).toBeVisible();
    const waiting = page.waitForEvent('download');
    await page
        .getByRole('link', { name: /Download Customer photograph/ })
        .click();
    const download = await waiting;
    expect(download.suggestedFilename()).toMatch(/^customer_photo-\d+\.png$/);
    expect(await download.failure()).toBeNull();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('customer-identity.png'),
        fullPage: true,
    });
    await expect
        .poll(() =>
            page.evaluate(
                () => window.history.state?.page instanceof ArrayBuffer,
            ),
        )
        .toBeTruthy();
    if (testInfo.project.name === 'mobile')
        await page
            .getByRole('button', { name: 'Toggle Sidebar' })
            .first()
            .click();
    await page.getByRole('button', { name: /DEMO Agent/ }).click();
    await page.getByRole('menuitem', { name: /Log out/ }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goBack();
    await expect(
        page.getByRole('heading', {
            name: 'Welcome to EstateFlow',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText('35202-1234567-1', { exact: true }),
    ).toHaveCount(0);
    await page.reload();
    await expect(page).toHaveURL(/\/login$/);
});
