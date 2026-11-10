import { expect, test } from '@playwright/test';

test('staff creates and revises a booking draft without changing plot holds', async ({
    page,
}, testInfo) => {
    test.setTimeout(180000);
    const clientErrors: string[] = [];
    page.on('pageerror', (error) => clientErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error' && /same key/i.test(message.text()))
            clientErrors.push(message.text());
    });
    const suffix = testInfo.project.name.toUpperCase();
    const customer = `DEMO Sales ${suffix}`;
    const code = `SALES-${suffix}`;
    const number = `DRAFT-${suffix}-001`;
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
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
    await page.getByLabel('Full name').fill(customer);
    await page.getByLabel('CNIC', { exact: true }).fill('1234512345671');
    await page
        .getByLabel('Address', { exact: true })
        .fill('DEMO Sales purchaser address');
    await page
        .getByRole('button', { name: 'Create customer', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: customer, exact: true }),
    ).toBeVisible();
    await page.goto('/inventory/setup');
    await page.getByLabel(/^Society name/).fill('DEMO Sales Housing');
    await page.getByLabel('Office name').fill('DEMO Sales Office');
    await page.getByRole('button', { name: 'Save society profile' }).click();
    await expect(
        page.getByRole('button', { name: 'Save society profile' }),
    ).toBeEnabled();
    await page.getByLabel('Block code').fill(code);
    await page.getByLabel('Block name').fill(`DEMO ${code}`);
    await page.getByRole('button', { name: 'Add block', exact: true }).click();
    await expect(page.getByLabel('Block code')).toHaveValue('');
    await page.getByLabel('Size label').fill(code);
    await page.getByLabel('Area in marla').fill('5.00');
    await page.getByRole('button', { name: 'Add size', exact: true }).click();
    await expect(page.getByLabel('Size label')).toHaveValue('');
    await page.goto('/inventory');
    await page.getByRole('button', { name: 'Add plot', exact: true }).click();
    await page.getByLabel('Plot number *', { exact: true }).fill(number);
    await page
        .getByLabel('Block *', { exact: true })
        .selectOption({ label: `${code} · DEMO ${code}` });
    await page
        .getByLabel('Plot size *', { exact: true })
        .selectOption({ label: `${code} · 5.00 marla` });
    await page.getByLabel('List price', { exact: false }).fill('5000000.25');
    await page.getByRole('button', { name: 'Save plot' }).click();
    await expect(page.getByRole('heading', { name: 'Add a plot' })).toHaveCount(
        0,
    );
    await page.goto('/sales');
    await page.getByRole('link', { name: 'New booking draft' }).click();
    const customerId = await page
        .getByLabel('Customer contact *', { exact: true })
        .locator('option')
        .filter({ hasText: customer })
        .getAttribute('value');
    await page
        .getByLabel('Customer contact *', { exact: true })
        .selectOption(customerId!);
    const plotId = await page
        .getByLabel('Proposed plot *', { exact: true })
        .locator('option')
        .filter({ hasText: number })
        .getAttribute('value');
    await page
        .getByLabel('Proposed plot *', { exact: true })
        .selectOption(plotId!);
    await page.getByLabel('Quoted price (PKR)').fill('4900000.15');
    await page
        .getByLabel('Conversation notes')
        .fill('DEMO proposed site visit and quote');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page).toHaveURL(/\/sales\/\d+$/);
    await expect(
        page.getByText('Saved version 1', { exact: true }),
    ).toBeVisible();
    const preparation = page.getByRole('region', {
        name: 'Booking preparation',
    });
    await expect(preparation).toBeVisible();
    await expect(preparation.getByRole('listitem')).toHaveCount(7);
    await expect(
        preparation.getByText('Verified down payment', { exact: true }),
    ).toBeVisible();
    await expect(
        preparation.getByText('Coming next', { exact: true }),
    ).toHaveCount(0);
    const purchasers = page.getByRole('region', {
        name: 'Proposed purchasers',
        exact: true,
    });
    await purchasers
        .getByRole('button', { name: 'Add purchaser', exact: true })
        .click();
    await purchasers
        .getByLabel('Purchaser 1', { exact: true })
        .selectOption(customerId!);
    await purchasers
        .getByLabel('Ownership share (%)', { exact: true })
        .fill('99.9999');
    await purchasers
        .getByRole('button', { name: 'Save purchasers', exact: true })
        .click();
    await expect(
        purchasers.getByText('Ownership shares must total exactly 100%.', {
            exact: true,
        }),
    ).toBeVisible();
    await purchasers
        .getByLabel('Ownership share (%)', { exact: true })
        .fill('100');
    await purchasers
        .getByRole('button', { name: 'Save purchasers', exact: true })
        .click();
    await expect(
        purchasers.getByText('Purchaser version 1', { exact: true }),
    ).toBeVisible();
    await expect(
        purchasers.getByLabel('Ownership share (%)', { exact: true }),
    ).toHaveValue('100.0000');
    await expect(
        page.getByText('Snapshot: PKR 5,000,000.25', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('No active hold', { exact: true }),
    ).toBeVisible();
    await page.getByLabel('Quoted price (PKR)').fill('4800000.99');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(
        page.getByText('Saved version 2', { exact: true }),
    ).toBeVisible();
    const draftUrl = page.url();
    await expect(
        page.getByRole('heading', { name: 'Proposed terms', exact: true }),
    ).toHaveCount(1);
    await expect(
        page.getByRole('button', { name: 'Save draft', exact: true }),
    ).toBeEnabled();
    await expect(
        page.getByRole('cell', { name: 'PKR 4,900,000.15', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'PKR 4,800,000.99', exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('sales-draft.png'),
        fullPage: true,
    });
    await page.getByRole('link', { name: 'All drafts' }).click();
    await page.getByLabel('Search drafts').fill(customer);
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(
        page.getByRole('cell', { name: 'PKR 4,800,000.99', exact: true }),
    ).toBeVisible();
    const saveSettings = async (section: string) => {
        const saved = page.waitForResponse(
            (response) =>
                response.request().method() === 'GET' &&
                response.url().endsWith(`/business-settings/${section}`) &&
                response.status() === 200,
        );
        await page
            .getByRole('button', { name: 'Save settings', exact: true })
            .click();
        await saved;
        await expect(
            page.getByRole('button', { name: 'Save settings', exact: true }),
        ).toBeEnabled();
    };
    await page.goto('/business-settings/booking');
    await page
        .getByLabel('Minimum down payment basis', { exact: true })
        .selectOption('percentage');
    await page
        .getByLabel('Minimum down payment value', { exact: true })
        .fill('10');
    await page.getByLabel('Signed booking form', { exact: true }).check();
    await page
        .getByLabel('Who may book a plot already on hold', { exact: true })
        .selectOption('holder_or_admin');
    await saveSettings('booking');
    await page.goto('/business-settings/installments');
    await page.getByLabel('Monthly', { exact: true }).check();
    await page
        .getByLabel('First due date', { exact: true })
        .selectOption('entered_per_booking');
    await page
        .getByLabel('When a day does not exist in a due month', { exact: true })
        .selectOption('clamp');
    await page
        .getByLabel('PKR rounding to two decimal places', { exact: true })
        .selectOption('half_even');
    await page
        .getByLabel('Place any rounding remainder in', { exact: true })
        .selectOption('last');
    await saveSettings('installments');
    await page.goto(`${draftUrl}/terms`);
    await expect(
        page.getByRole('heading', {
            name: 'Pricing & installments',
            exact: true,
        }),
    ).toBeVisible();
    await page
        .getByLabel('Admin discount (PKR)', { exact: true })
        .fill('100000.99');
    await page
        .getByLabel('Discount reason', { exact: true })
        .fill('DEMO admin agreed reduction');
    await page
        .getByLabel('Planned down payment (PKR) *', { exact: true })
        .fill('500000.00');
    await page
        .getByLabel('Payment plan *', { exact: true })
        .selectOption('monthly');
    await page
        .getByLabel('Number of installments *', { exact: true })
        .fill('7');
    await page
        .getByLabel('First installment date *', { exact: true })
        .fill('2028-01-31');
    await page
        .getByRole('button', {
            name: 'Calculate & save proposed terms',
            exact: true,
        })
        .click();
    await expect(
        page.getByText('Terms version 1', { exact: true }),
    ).toBeVisible();
    const schedule = page.getByRole('region', {
        name: 'Saved pricing and schedule',
        exact: true,
    });
    await expect(schedule.getByRole('row')).toHaveCount(8);
    await expect(
        schedule.getByRole('cell', { name: '2028-02-29', exact: true }),
    ).toBeVisible();
    await expect(
        schedule.getByText('PKR 4,700,000.00', { exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('pricing-and-installments.png'),
        fullPage: true,
    });
    await page.reload();
    await expect(
        page.getByLabel('Admin discount (PKR)', { exact: true }),
    ).toHaveValue('100000.99');
    await expect(
        page
            .getByRole('region', {
                name: 'Saved pricing and schedule',
                exact: true,
            })
            .getByRole('row'),
    ).toHaveCount(8);
    expect(clientErrors).toEqual([]);
});
