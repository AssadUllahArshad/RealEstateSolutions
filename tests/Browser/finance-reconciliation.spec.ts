import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test('accountant prepares and admin finalizes a bank reconciliation', async ({
    page,
}, testInfo) => {
    test.setTimeout(180000);
    execFileSync('php', ['artisan', 'cache:clear', '--env=e2e']);
    const suffix =
        `${testInfo.project.name}-${Date.now().toString(36)}`.toUpperCase();
    const code = `REC-${suffix}`;
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

    await page.goto('/finance/accounts');
    await page.getByLabel('Account code *', { exact: true }).fill(code);
    await page
        .getByLabel('Account name *', { exact: true })
        .fill(`DEMO ${suffix} reconciliation bank`);
    await page
        .getByLabel('Account type *', { exact: true })
        .selectOption('bank');
    await page
        .getByRole('button', { name: 'Save account setup', exact: true })
        .click();
    await expect(page).toHaveURL(/\/finance\/accounts\/\d+$/);

    await page.getByLabel('Opening amount (PKR)').fill('5000.00');
    await page
        .getByLabel('Verified source note')
        .fill(`DEMO ${suffix} verified bank opening statement`);
    await page
        .getByLabel('Statement or count reference')
        .fill(`DEMO-${suffix}-OPEN`);
    await page
        .getByRole('checkbox', { name: /I verified this amount/ })
        .check();
    await page
        .getByRole('button', { name: 'Record immutable opening balance' })
        .click();
    await expect(page.getByText('PKR 5,000.00', { exact: true })).toHaveCount(
        2,
    );

    await page.context().clearCookies();
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('demo.accountant@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/finance/expenses');
    await page.getByRole('button', { name: 'Post expense' }).click();
    const expenseAccount = await page
        .getByLabel('Payment account')
        .locator('option')
        .filter({ hasText: code })
        .getAttribute('value');
    await page.getByLabel('Payment account').selectOption(expenseAccount!);
    await page
        .getByLabel('Expense category')
        .fill(`DEMO ${suffix} bank service`);
    await page.getByLabel('Amount (PKR)').fill('1000.00');
    await page.getByLabel('Paid to').fill('DEMO Bank');
    await page
        .getByLabel('Verification note')
        .fill(`DEMO ${suffix} bank statement fee verified`);
    await page
        .getByRole('checkbox', { name: /I verified the amount, account/ })
        .check();
    await page.getByRole('button', { name: 'Post expense' }).last().click();
    await expect(
        page.getByText(`DEMO ${suffix} bank service`, { exact: true }),
    ).toBeVisible();

    await page.goto('/finance/reconciliation');
    const bankAccount = await page
        .getByLabel('Bank account')
        .locator('option')
        .filter({ hasText: code })
        .getAttribute('value');
    await page.getByLabel('Bank account').selectOption(bankAccount!);
    await page.getByLabel('Statement period start').fill(businessDate);
    await page.getByLabel('Statement period end').fill(businessDate);
    await page.getByRole('button', { name: 'Load statement period' }).click();
    await expect(
        page.getByRole('heading', { name: 'Prepare bank reconciliation' }),
    ).toBeVisible();
    await expect(page.getByText('PKR 5,000.00', { exact: true })).toBeVisible();
    await expect(page.getByText('PKR 4,000.00', { exact: true })).toBeVisible();
    const entry = page.getByLabel('Match cashbook entry');
    await entry.selectOption({ index: 1 });
    await page.getByLabel('Statement direction').selectOption('outflow');
    await page.getByLabel('Statement date').fill(businessDate);
    await page
        .getByLabel('Statement description')
        .fill(`DEMO ${suffix} bank service fee`);
    await page.getByLabel('Statement amount (PKR)').fill('1000.00');
    await page.getByLabel('Statement reference').fill(`DEMO-${suffix}-FEE`);
    await page.getByLabel('Statement opening balance').fill('5000.00');
    await page.getByLabel('Statement closing balance').fill('4000.00');
    await page
        .getByLabel('Review note')
        .fill(`DEMO ${suffix} statement exact matches reviewed`);
    await page
        .getByRole('checkbox', { name: /I verified the source statement/ })
        .check();
    await page.getByRole('button', { name: 'Submit for admin review' }).click();
    await expect(page.getByText('Awaiting admin review')).toBeVisible();
    const reconciliation = page.getByRole('article').filter({ hasText: code });
    await expect(reconciliation).toBeVisible();

    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/finance/reconciliation');
    const pending = page.getByRole('article').filter({ hasText: code });
    await pending
        .getByLabel('Admin review note')
        .fill(`DEMO ${suffix} independent statement review`);
    await pending
        .getByRole('checkbox', { name: /I reviewed the statement/ })
        .check();
    await pending
        .getByRole('button', { name: 'Finalize reconciliation' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Reconciliation history' }),
    ).toBeVisible();
    await expect(pending.getByText('finalized', { exact: true })).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('bank-reconciliation.png'),
        fullPage: true,
    });
    expect(errors).toEqual([]);
});
