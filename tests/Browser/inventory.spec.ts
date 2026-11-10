import { expect, test } from '@playwright/test';

test('administrator configures a society and maintains its custom plot catalogue', async ({
    page,
    browser,
}, testInfo) => {
    test.setTimeout(180000);
    const suffix = testInfo.project.name.toUpperCase();
    const code = `BROWSER-${suffix}`;
    const blockName = `Browser ${suffix} gardens`;
    const sizeLabel = `Browser ${suffix} custom size`;
    const number = `BROWSER-${suffix}-001`;
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo.admin@estateflow.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Sign in to EstateFlow' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/inventory/setup');
    await expect(
        page.getByRole('heading', { name: 'Society setup', exact: true }),
    ).toBeVisible();
    await page.getByLabel(/^Society name/).fill(`DEMO ${suffix} Housing`);
    await page.getByLabel('Office name').fill(`DEMO ${suffix} Office`);
    await page.getByRole('button', { name: 'Save society profile' }).click();
    await expect(
        page.getByRole('button', { name: 'Save society profile' }),
    ).toBeVisible();
    await page.getByLabel('Block code').fill(code);
    await page.getByLabel('Block name').fill(blockName);
    await page.getByRole('button', { name: 'Add block', exact: true }).click();
    await expect(page.getByLabel('Block code')).toHaveValue('');
    await expect(page.getByText(blockName, { exact: false })).toBeVisible();
    await page.getByLabel('Size label').fill(sizeLabel);
    await page.getByLabel('Area in marla').fill('6.75');
    await page.getByRole('button', { name: 'Add size', exact: true }).click();
    await expect(page.getByLabel('Size label')).toHaveValue('');
    await expect(page.getByText(sizeLabel, { exact: true })).toBeVisible();
    await page.goto('/inventory');
    await page.getByRole('button', { name: 'Add plot', exact: true }).click();
    await page.getByLabel('Plot number *', { exact: true }).fill(number);
    await page
        .getByLabel('Block *', { exact: true })
        .selectOption({ label: `${code} · ${blockName}` });
    await page
        .getByLabel('Plot size *', { exact: true })
        .selectOption({ label: `${sizeLabel} · 6.75 marla` });
    await page.getByLabel('List price', { exact: false }).fill('3200000.25');
    await page.getByRole('button', { name: 'Save plot' }).click();
    await expect(page.getByRole('heading', { name: 'Add a plot' })).toHaveCount(
        0,
    );
    await page.getByLabel('Search plot number').fill(number);
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(
        page.getByText('1 matching plots', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: number, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'PKR 3,200,000.25', exact: true }),
    ).toBeVisible();
    await page.getByRole('button', { name: `Edit plot ${number}` }).click();
    await page.getByLabel('List price', { exact: false }).fill('3200000.75');
    await page.getByRole('button', { name: 'Save plot' }).click();
    await expect(
        page.getByRole('heading', { name: 'Edit plot', exact: true }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('cell', { name: 'PKR 3,200,000.75', exact: true }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Block grid view' }).click();
    await expect(
        page.getByRole('heading', { name: number, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(
            'Manual holds remain until an authorized admin releases them.',
            {
                exact: false,
            },
        ),
    ).toBeVisible();
    const downloading = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Export filtered CSV' }).click();
    const download = await downloading;
    expect(download.suggestedFilename()).toBe('estateflow-inventory.csv');
    expect(await download.failure()).toBeNull();
    // An independent agent session places the hold without an admin approval step.
    const agentContext = await browser.newContext({
        viewport: page.viewportSize(),
    });
    try {
        const agentPage = await agentContext.newPage();
        await agentPage.goto('http://127.0.0.1:18084/login');
        await agentPage
            .getByLabel('Email address')
            .fill('demo.agent@estateflow.test');
        await agentPage
            .getByLabel('Password', { exact: true })
            .fill(process.env.E2E_PASSWORD ?? '');
        await agentPage
            .getByRole('button', { name: 'Sign in to EstateFlow' })
            .click();
        await expect(agentPage).toHaveURL(/\/dashboard$/);
        await agentPage.goto(`http://127.0.0.1:18084/inventory?q=${number}`);
        await agentPage
            .getByRole('link', { name: `View hold for ${number}` })
            .click();
        await expect(agentPage).toHaveURL(/\/inventory\/plots\/\d+\/hold$/);
        const holdUrl = agentPage.url();
        await agentPage
            .getByLabel('Hold or block purpose')
            .fill('DEMO site visit');
        await agentPage.getByRole('button', { name: 'Confirm hold' }).click();
        await expect(
            agentPage.getByRole('heading', {
                name: 'Active hold',
                exact: true,
            }),
        ).toBeVisible();
        await expect(agentPage.getByText('Placed by DEMO Agent')).toBeVisible();
        await expect(
            agentPage.getByRole('button', { name: 'Release hold or block' }),
        ).toHaveCount(0);
        await page.goto(
            `http://127.0.0.1:18084/inventory?hold=reservation&q=${number}`,
        );
        await expect(page.getByLabel('Filter by hold state')).toHaveValue(
            'reservation',
        );
        await expect(
            page.getByText('1 matching plots', { exact: true }),
        ).toBeVisible();
        await page
            .getByRole('link', { name: `View hold for ${number}` })
            .click();
        await expect(page).toHaveURL(holdUrl);
        await page.getByLabel('Release reason').fill('DEMO visit completed');
        await page
            .getByRole('button', { name: 'Release hold or block' })
            .click();
        await expect(
            page.getByRole('heading', {
                name: 'No active hold or block',
                exact: true,
            }),
        ).toBeVisible();
        await page.getByLabel('Action', { exact: true }).selectOption('block');
        await page
            .getByLabel('Hold or block purpose')
            .fill('DEMO admin review');
        await page
            .getByRole('button', { name: 'Block plot', exact: true })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Plot blocked', exact: true }),
        ).toBeVisible();
        await agentPage.goto(
            `http://127.0.0.1:18084/inventory?hold=block&q=${number}`,
        );
        await expect(
            agentPage.getByText('1 matching plots', { exact: true }),
        ).toBeVisible();
        await agentPage
            .getByRole('link', { name: `View hold for ${number}` })
            .click();
        await expect(
            agentPage.getByRole('heading', {
                name: 'Plot blocked',
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            agentPage.getByRole('button', { name: 'Confirm hold' }),
        ).toHaveCount(0);
        await page
            .getByLabel('Release reason')
            .fill('DEMO admin review completed');
        await page
            .getByRole('button', { name: 'Release hold or block' })
            .click();
        await expect(
            page.getByRole('heading', {
                name: 'No active hold or block',
                exact: true,
            }),
        ).toBeVisible();
        await page.goto(`http://127.0.0.1:18084/inventory?q=${number}`);
        await page.getByLabel('Filter by hold state').selectOption('none');
        await page.getByRole('button', { name: 'Apply filters' }).click();
        await expect(
            page.getByText('1 matching plots', { exact: true }),
        ).toBeVisible();
    } finally {
        await agentContext.close();
    }
    await page.screenshot({
        path: testInfo.outputPath('inventory.png'),
        fullPage: true,
    });
});
