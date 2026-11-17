import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { inspectPrint } from './helpers/print-document';

test('agent confirms a booking with exact down payment and reloads its permanent record', async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name === 'mobile',
        'The complete finance and PDF workflow runs in desktop Chromium; mobile layout is covered by the responsive browser suites.',
    );
    test.setTimeout(180000);
    const fixtures = JSON.parse(
        readFileSync('.runtime/booking-browser.json', 'utf8'),
    ) as Record<string, { draft: number; receipt: string }>;
    const fixture = fixtures[testInfo.project.name];
    expect(fixture).toBeDefined();
    const { draft, receipt } = fixture;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.booking@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto(`/sales/${draft}/confirm`);
    await expect(
        page.getByRole('heading', { name: 'Review booking', exact: true }),
    ).toBeVisible();
    await expect(page.getByAltText('Data Real Estate')).toBeVisible();
    await expect(page.getByAltText('Green Vista Housing Scheme')).toBeVisible();
    await page
        .getByRole('checkbox', { name: new RegExp(String(receipt)) })
        .check();
    await page.getByLabel(`Amount from ${receipt} (PKR)`).fill('99.99');
    await page.getByLabel(/I verified the purchaser identities/).check();
    await page
        .getByRole('button', { name: 'Confirm booking & allocate payment' })
        .click();
    await expect(page.getByRole('alert')).toContainText(
        'payable down payment after discount exactly',
    );
    await page.getByLabel(`Amount from ${receipt} (PKR)`).fill('100.01');
    await page
        .getByRole('button', { name: 'Confirm booking & allocate payment' })
        .click();
    await expect(page).toHaveURL(/\/bookings\/\d+$/);
    await expect(page.getByText('Plot booked', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Legal purchasers' }),
    ).toBeVisible();
    await expect(page.getByText('100.0000%', { exact: true })).toBeVisible();
    await expect(page.getByText('PKR 100.01', { exact: true })).toHaveCount(2);
    await inspectPrint(
        page,
        testInfo,
        'booking-confirmation',
        'Booking confirmed',
    );
    await page.reload();
    await expect(page.getByText('Plot booked', { exact: true })).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('confirmed-booking.png'),
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await page.goto(`/sales/${draft}`);
    await expect(page).toHaveURL(/\/bookings\/\d+$/);
    await page.goto(`/sales/${draft}/terms`);
    await expect(page).toHaveURL(/\/bookings\/\d+$/);
    const bookingUrl = page.url();
    await expect(
        page.getByRole('heading', {
            name: 'Agreed installment schedule',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Payments & ledger', exact: true }),
    ).toHaveCount(0);
    const ledgerResponse = await page.goto(`${bookingUrl}/ledger`);
    expect(ledgerResponse?.status()).toBe(404);
    await expect(
        page.getByRole('heading', { name: 'Page not found', exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('post-booking-ledger-disabled.png'),
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await page.goto(bookingUrl);
    await expect(
        page.getByRole('heading', {
            name: 'Agreed installment schedule',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText(
            'Subsequent society installments are handled outside EstateFlow.',
            { exact: false },
        ),
    ).toBeVisible();
    expect(errors).toEqual([]);
});
