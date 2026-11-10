import { expect, test } from '@playwright/test';
import { inspectPrint } from './helpers/print-document';

test('staff configures an account and prepares an unposted cheque report', async ({
    page,
}, testInfo) => {
    test.setTimeout(180000);
    const suffix = testInfo.project.name.toUpperCase();
    const customer = `DEMO Receipt ${suffix}`;
    const code = `FIN-${suffix}`;
    const businessDate = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Karachi',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
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
    await page
        .getByRole('button', { name: 'Create customer', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: customer, exact: true }),
    ).toBeVisible();
    await page.goto('/finance/accounts');
    await page.getByLabel('Account code *', { exact: true }).fill(code);
    await page
        .getByLabel('Account name *', { exact: true })
        .fill(`DEMO ${suffix} office bank`);
    await page
        .getByLabel('Account type *', { exact: true })
        .selectOption('bank');
    await page
        .getByRole('button', { name: 'Save account setup', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: `Edit ${code}`, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByLabel('Account code *', { exact: true }),
    ).toHaveValue(code);
    await page.screenshot({
        path: testInfo.outputPath('financial-accounts.png'),
        fullPage: true,
    });
    await page.goto('/business-settings/collections');
    await page.getByLabel('Cheque', { exact: true }).check();
    const savedSettings = page.waitForResponse(
        (response) =>
            response.request().method() === 'GET' &&
            response.url().endsWith('/business-settings/collections') &&
            response.status() === 200,
    );
    await page
        .getByRole('button', { name: 'Save settings', exact: true })
        .click();
    await savedSettings;
    await expect(
        page.getByRole('button', { name: 'Save settings', exact: true }),
    ).toBeEnabled();
    await page.goto('/finance/receipts/new');
    const customerId = await page
        .getByLabel('Customer *', { exact: true })
        .locator('option')
        .filter({ hasText: customer })
        .getAttribute('value');
    await page
        .getByLabel('Customer *', { exact: true })
        .selectOption(customerId!);
    const accountId = await page
        .getByLabel('Destination account *', { exact: true })
        .locator('option')
        .filter({ hasText: code })
        .getAttribute('value');
    await page
        .getByLabel('Destination account *', { exact: true })
        .selectOption(accountId!);
    await page
        .getByLabel('Reported amount (PKR) *', { exact: true })
        .fill('100000.25');
    await page
        .getByLabel('Reported payment date *', { exact: true })
        .fill(businessDate);
    await page.getByRole('radio', { name: 'Cheque', exact: true }).check();
    await page
        .getByLabel('External payment reference *', { exact: true })
        .fill(`DEMO-${suffix}-CHEQUE`);
    await page
        .getByLabel('Verification notes', { exact: true })
        .fill('DEMO cheque reported; bank clearance not verified.');
    await page
        .getByRole('button', { name: 'Save receipt preparation', exact: true })
        .click();
    await expect(page).toHaveURL(/\/finance\/receipts\/\d+$/);
    await expect(
        page.getByText('Saved preparation version 1', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Prepared · Unposted', { exact: true }),
    ).toBeVisible();
    await page
        .getByLabel('Reported amount (PKR) *', { exact: true })
        .fill('100000.26');
    await page
        .getByRole('button', { name: 'Save receipt preparation', exact: true })
        .click();
    await expect(
        page.getByText('Saved preparation version 2', { exact: true }),
    ).toBeVisible();
    const preparationUrl = page.url();
    await page.reload();
    await expect(
        page.getByLabel('Reported amount (PKR) *', { exact: true }),
    ).toHaveValue('100000.26');
    await expect(
        page.getByLabel('External payment reference *', { exact: true }),
    ).toHaveValue(`DEMO-${suffix}-CHEQUE`);
    await expect(
        page
            .getByRole('region', { name: 'Preparation history', exact: true })
            .getByText(/v2/),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('receipt-preparation.png'),
        fullPage: true,
    });
    await page
        .getByRole('link', { name: 'All preparations', exact: true })
        .click();
    await page
        .getByLabel('Search receipt customers', { exact: true })
        .fill(customer);
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    if (testInfo.project.name === 'mobile') {
        await expect(
            page
                .getByRole('list', { name: 'Receipt summaries', exact: true })
                .getByText('PKR 100,000.26', { exact: true }),
        ).toBeVisible();
    } else {
        await expect(
            page.getByRole('cell', { name: 'PKR 100,000.26', exact: true }),
        ).toBeVisible();
    }
    await page.screenshot({
        path: testInfo.outputPath('receipt-directory.png'),
        fullPage: true,
    });
    await expect(
        page.getByText(`DEMO-${suffix}-CHEQUE`, { exact: true }),
    ).toHaveCount(0);
    const saveSection = async (section: string) => {
        const response = page.waitForResponse(
            (result) =>
                result.request().method() === 'GET' &&
                result.url().endsWith(`/business-settings/${section}`) &&
                result.status() === 200,
        );
        await page
            .getByRole('button', { name: 'Save settings', exact: true })
            .click();
        await response;
        await expect(
            page.getByRole('button', { name: 'Save settings', exact: true }),
        ).toBeEnabled();
    };
    await page.goto('/business-settings/collections');
    await page
        .getByLabel('Installment allocation', { exact: true })
        .selectOption('oldest_first');
    await page
        .getByLabel('A cheque becomes collectible when', { exact: true })
        .selectOption('cleared');
    await page
        .getByLabel('Money above the current installment balance', {
            exact: true,
        })
        .selectOption('unallocated');
    await saveSection('collections');
    await page.goto('/business-settings/receipt_posting');
    await page
        .getByLabel('Official receipt prefix', { exact: true })
        .fill(`TEST-${suffix}`);
    await page
        .getByLabel('First number for a new prefix', { exact: true })
        .fill('100');
    await page
        .getByLabel('Receipt number minimum digits', { exact: true })
        .fill('6');
    await page
        .getByLabel('First open posting date', { exact: true })
        .fill(businessDate);
    await page
        .getByLabel('Maximum backdating (calendar days)', { exact: true })
        .fill('0');
    await page
        .getByLabel('Receipts before installment allocation', { exact: true })
        .selectOption('allow');
    await page
        .getByLabel('Payment destination rules', { exact: true })
        .selectOption('matching');
    await page
        .getByLabel('Duplicate non-cash references', { exact: true })
        .selectOption('global_method');
    await saveSection('receipt_posting');
    await page.goto(preparationUrl);
    const issue = page.getByRole('region', {
        name: 'Verify & issue receipt',
        exact: true,
    });
    await page
        .getByLabel('Reported amount (PKR) *', { exact: true })
        .fill('200.00');
    await expect(
        issue.getByText(
            'Save or discard your preparation edits before verification.',
            { exact: true },
        ),
    ).toBeVisible();
    await page
        .getByLabel('Reported amount (PKR) *', { exact: true })
        .fill('100000.26');
    await issue
        .getByLabel('Posting business date *', { exact: true })
        .fill(businessDate);
    await issue
        .getByLabel('Verified cheque stage *', { exact: true })
        .selectOption('deposited');
    await issue
        .getByLabel('Verification context *', { exact: true })
        .fill(
            'DEMO verified deposited cheque; awaiting actual bank clearance.',
        );
    await issue
        .getByRole('checkbox', { name: /I verified the saved amount/ })
        .check();
    await issue
        .getByRole('button', {
            name: 'Verify & issue official receipt',
            exact: true,
        })
        .click();
    await expect(page).toHaveURL(/\/finance\/posted\/\d+$/);
    await expect(
        page.getByRole('heading', {
            name: `TEST-${suffix}-000100`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText('Awaiting bank clearance', { exact: true }),
    ).toBeVisible();
    await inspectPrint(
        page,
        testInfo,
        'pending-receipt',
        'Awaiting bank clearance',
    );
    await expect(
        page
            .getByRole('region', { name: 'Receipt movements', exact: true })
            .getByRole('listitem'),
    ).toHaveCount(0);
    await page
        .getByLabel('Clearance business date *', { exact: true })
        .fill(businessDate);
    await page
        .getByLabel('Clearance evidence *', { exact: true })
        .fill('DEMO bank statement checked; exact cheque and amount cleared.');
    await page
        .getByRole('checkbox', { name: /I checked bank clearance evidence/ })
        .check();
    await page
        .getByRole('button', { name: 'Confirm bank clearance', exact: true })
        .click();
    await expect(
        page.getByText('Money recognized', { exact: true }),
    ).toBeVisible();
    await inspectPrint(
        page,
        testInfo,
        'recognized-receipt',
        'Recognized receipt',
    );
    await expect(
        page
            .getByRole('region', { name: 'Receipt movements', exact: true })
            .getByRole('listitem'),
    ).toHaveCount(1);
    await page.reload();
    await expect(
        page.getByText('Money recognized', { exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('recognized-receipt.png'),
        fullPage: true,
    });
    await page
        .getByLabel('Reversal business date *', { exact: true })
        .fill(businessDate);
    await page
        .getByLabel('Reversal reason *', { exact: true })
        .fill(
            'DEMO accounting-only correction for synthetic receipt; no refund paid.',
        );
    await page
        .getByRole('checkbox', {
            name: /I authorize this full accounting reversal/,
        })
        .check();
    await page
        .getByRole('button', {
            name: 'Record full accounting reversal',
            exact: true,
        })
        .click();
    await expect(page.getByText('Reversed', { exact: true })).toBeVisible();
    await inspectPrint(
        page,
        testInfo,
        'reversed-receipt',
        'REVERSED — not valid payment credit',
    );
    await expect(
        page
            .getByRole('region', { name: 'Receipt movements', exact: true })
            .getByRole('listitem'),
    ).toHaveCount(2);
    await page.screenshot({
        path: testInfo.outputPath('reversed-receipt.png'),
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    expect(errors).toEqual([]);
});
