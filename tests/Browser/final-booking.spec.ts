import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { inspectPrint } from './helpers/print-document';

test('agent confirms a booking with exact down payment and reloads its permanent record', async ({
    page,
}, testInfo) => {
    test.setTimeout(120000);
    const fixture = JSON.parse(
        readFileSync('.runtime/booking-browser.json', 'utf8'),
    ) as Record<string, number | string>;
    const draft = fixture[testInfo.project.name];
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
        .getByRole('checkbox', { name: new RegExp(String(fixture.receipt)) })
        .check();
    await page.getByLabel(`Amount from ${fixture.receipt} (PKR)`).fill('99.99');
    await page.getByLabel(/I verified the purchaser identities/).check();
    await page
        .getByRole('button', { name: 'Confirm booking & allocate payment' })
        .click();
    await expect(page.getByRole('alert')).toContainText(
        'planned down payment exactly',
    );
    await page
        .getByLabel(`Amount from ${fixture.receipt} (PKR)`)
        .fill('100.01');
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
    await page
        .getByRole('link', { name: 'Payments & ledger', exact: true })
        .click();
    await expect(
        page.getByRole('button', { name: 'Allocate receipt credit' }),
    ).toHaveCount(0);
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto(`${bookingUrl}/ledger`);
    const credit = page.getByLabel('Receipt credit', { exact: true });
    const receiptId = await credit
        .locator('option')
        .filter({ hasText: String(fixture.receipt) })
        .getAttribute('value');
    await credit.selectOption(receiptId!);
    await page
        .getByLabel('Amount to allocate (PKR)', { exact: true })
        .fill('25.00');
    await page
        .getByLabel(/I verified the customer, recognized receipt credit/)
        .check();
    await page
        .getByRole('button', { name: 'Allocate receipt credit', exact: true })
        .click();
    await expect(page.getByText('PKR 875.00', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByText('PKR 875.00', { exact: true })).toBeVisible();
    await inspectPrint(
        page,
        testInfo,
        'current-principal-statement',
        'Live principal balances',
        'Print statement / Save PDF',
        'PKR 875.00',
    );
    await page.screenshot({
        path: testInfo.outputPath('live-installment-ledger.png'),
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    expect(errors).toEqual([]);
});
