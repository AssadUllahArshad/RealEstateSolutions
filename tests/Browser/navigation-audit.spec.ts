import { expect, test } from '@playwright/test';

const password = process.env.E2E_PASSWORD ?? '';

async function signIn(page: import('@playwright/test').Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('every role can open its workspace pages and admin controls remain usable in both languages', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name === 'mobile');
    test.setTimeout(240000);
    const clientErrors: string[] = [];
    page.on('pageerror', (error) => clientErrors.push(error.message));

    const adminPages = [
        '/dashboard',
        '/inventory',
        '/sales',
        '/calculator',
        '/customers',
        '/follow-ups',
        '/finance/receipts',
        '/finance/expenses',
        '/finance/salaries',
        '/finance/accounts',
        '/reports/owners',
        '/reports/operations',
        '/imports',
        '/inventory/setup',
        '/business-settings/booking',
        '/team',
        '/settings/profile',
        '/settings/appearance',
    ];

    await signIn(page, 'demo.admin@estateflow.test');
    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await expect(
        page.locator('[data-slot="sidebar"][data-state="collapsed"]'),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
        'collapsed navigation must remain inside the viewport',
    ).toBe(true);
    await page.getByRole('button', { name: 'Toggle sidebar' }).click();

    for (const path of adminPages) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('main')).toBeVisible();
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth <=
                    window.innerWidth + 1,
            ),
            `${path} must not overflow horizontally`,
        ).toBe(true);
    }

    await page.goto('/inventory');
    const interactiveTriggers = page.locator(
        'button[aria-haspopup="dialog"], button[aria-haspopup="menu"]',
    );
    for (let index = 0; index < (await interactiveTriggers.count()); index++) {
        const trigger = interactiveTriggers.nth(index);
        if (await trigger.isVisible()) {
            await trigger.click();
            await page.keyboard.press('Escape');
        }
    }

    await page.goto('/dashboard');
    await page.getByRole('button', { name: 'Dark theme' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.getByRole('button', { name: 'Light theme' }).click();
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await page.locator('form[action="/locale"] button').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ur');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await page.locator('form[action="/locale"] button').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    const sharedPages = ['/dashboard', '/inventory', '/sales', '/customers'];
    for (const email of [
        'demo.office-admin@estateflow.test',
        'demo.accountant@estateflow.test',
        'demo.agent@estateflow.test',
    ]) {
        await page.context().clearCookies();
        await signIn(page, email);
        for (const path of sharedPages) {
            const response = await page.goto(path);
            expect(response?.status(), `${email}: ${path}`).toBe(200);
        }
    }

    expect(clientErrors).toEqual([]);
});
