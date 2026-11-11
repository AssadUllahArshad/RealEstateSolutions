import { expect, test } from '@playwright/test';

test('filter controls stay aligned and within the page at desktop and mobile widths', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    for (const [path, selector] of [
        ['/inventory', 'form[aria-label="Plot filters"]'],
        ['/customers', 'form[aria-label="Customer filters"]'],
        ['/sales', 'form[action="/sales"]'],
        ['/finance/receipts', 'form[action="/finance/receipts"]'],
        ['/finance/receivables', 'form[aria-label="Receivables filters"]'],
        ['/finance/movements', 'form[aria-label="Cashbook filters"]'],
        ['/finance', 'form[aria-label="Finance period filters"]'],
        ['/finance/expenses', 'form[aria-label="Expense date filters"]'],
        ['/finance/commissions', 'form[aria-label="Commission filters"]'],
        ['/reports/owners', 'form[aria-label="Owner report filters"]'],
        [
            '/finance/reconciliation',
            'form[aria-label="Statement period filters"]',
        ],
    ]) {
        await page.goto(path);
        const form = page.locator(selector).first();
        await expect(form).toBeVisible();
        const layout = await form.evaluate((element) => {
            const formRect = element.getBoundingClientRect();
            const controls = Array.from(
                element.querySelectorAll('input, select, button'),
            )
                .filter((control) => {
                    const rect = control.getBoundingClientRect();
                    return rect.width > 0 && rect.height > 0;
                })
                .map((control) => {
                    const rect = control.getBoundingClientRect();
                    return {
                        tag: control.tagName,
                        className: control.className,
                        label:
                            control.getAttribute('aria-label') ??
                            control.textContent?.trim(),
                        height: rect.height,
                        left: rect.left,
                        right: rect.right,
                    };
                });

            return {
                clientWidth: element.clientWidth,
                scrollWidth: element.scrollWidth,
                left: formRect.left,
                right: formRect.right,
                controls,
            };
        });

        expect(
            layout.controls.length,
            `${path} should expose filter controls`,
        ).toBeGreaterThan(0);
        expect(
            layout.scrollWidth,
            `${path} filter form should not overflow horizontally`,
        ).toBeLessThanOrEqual(layout.clientWidth + 1);
        for (const control of layout.controls) {
            expect(
                control.height,
                `${path} filter control heights should align: ${JSON.stringify(layout.controls)}`,
            ).toBe(44);
            expect(
                control.left,
                `${path} filter control should remain inside the form`,
            ).toBeGreaterThanOrEqual(layout.left - 1);
            expect(
                control.right,
                `${path} filter control should remain inside the form`,
            ).toBeLessThanOrEqual(layout.right + 1);
        }
    }
});

test('refund form labels remain associated and its controls stay within the page', async ({
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('demo.accountant@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/finance/refunds');

    for (const label of [
        'Cancelled booking',
        'Payment account',
        'Refund amount (PKR)',
        'Approved deduction (PKR)',
        'Deduction reason',
        'Reference',
        'Verification note',
    ]) {
        await expect(page.getByLabel(label, { exact: true })).toBeVisible();
    }

    const section = page.locator('section').filter({
        has: page.getByRole('heading', {
            name: 'Prepare refund',
            exact: true,
        }),
    });
    const form = section.locator('form');
    const dimensions = await form.evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    expect(dimensions.scrollWidth).toBeLessThanOrEqual(
        dimensions.clientWidth + 1,
    );
    const submit = page.getByRole('button', {
        name: 'Submit for admin approval',
    });
    await submit.scrollIntoViewIfNeeded();
    await expect(submit).toBeInViewport();
});
