import { expect, test } from '@playwright/test';

test('staff can sign in, use the workspace and sign out', async ({
    page,
}, testInfo) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(
        page.getByRole('heading', { name: 'Welcome, DEMO.' }),
    ).toBeVisible();
    await expect(
        page.getByRole('region', { name: 'Office overview' }),
    ).toBeVisible();
    await expect(page.getByText('Asia/Karachi · PKR')).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('workspace.png'),
        fullPage: true,
    });
    if (testInfo.project.name === 'mobile') {
        await page
            .getByRole('button', { name: 'Toggle Sidebar' })
            .first()
            .click();
    }
    await page.getByRole('link', { name: 'Team & access' }).click();
    await expect(
        page.getByRole('heading', { name: 'Team & access' }),
    ).toBeVisible();
    await expect(page.getByText('DEMO Agent', { exact: true })).toBeVisible();
    // A new document closes the mobile drawer and exercises server rendering again.
    await page.goto('/dashboard');
    if (testInfo.project.name === 'mobile') {
        await page
            .getByRole('button', { name: 'Toggle Sidebar' })
            .first()
            .click();
    }
    await page.getByRole('button', { name: /DEMO Admin/ }).click();
    await page.getByRole('menuitem', { name: /Log out/ }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/team');
    await expect(page).toHaveURL(/\/login$/);
    expect(errors).toEqual([]);
});

test('agent cannot open the staff directory and login is keyboard usable', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.agent@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByLabel('Password', { exact: true }).press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);
    const response = await page.goto('/team');
    expect(response?.status()).toBe(403);
    await expect(
        page.getByRole('heading', { name: 'Access restricted' }),
    ).toBeVisible();
    await expect(page.getByText(/Request reference:/)).toBeVisible();
    await page.getByRole('link', { name: 'Return to workspace' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    const inventory = await page.goto('/inventory');
    expect(inventory?.status()).toBe(200);
    await expect(
        page.getByRole('heading', { name: 'Plot inventory', exact: true }),
    ).toBeVisible();
});

test('missing pages offer a safe return and reject an expired form session', async ({
    page,
}, testInfo) => {
    const missing = await page.goto('/missing-page');
    expect(missing?.status()).toBe(404);
    await expect(
        page.getByRole('heading', { name: 'Page not found' }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('not-found.png'),
        fullPage: true,
    });
    await page.getByRole('link', { name: 'Return to workspace' }).click();
    await expect(page).toHaveURL(/\/login$/);
    const expired = await page.request.post('/login', {
        headers: { 'X-Inertia': 'true' },
        form: {
            email: 'demo.agent@estateflow.test',
            password: 'unused-test-value',
        },
    });
    expect(expired.status()).toBe(419);
    expect(await expired.json()).toMatchObject({
        component: 'errors/status',
        props: { status: 419 },
    });
});
