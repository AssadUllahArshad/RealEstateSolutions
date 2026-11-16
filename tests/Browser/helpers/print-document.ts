import { expect, type Page, type TestInfo } from '@playwright/test';
import { readFileSync } from 'node:fs';

export async function inspectPrint(
    page: Page,
    testInfo: TestInfo,
    label: string,
    expectedStatus: string,
    linkName = 'Print / Save PDF',
    expectedAmount?: string,
) {
    const popupPromise = page.waitForEvent('popup');
    await page.getByRole('link', { name: linkName, exact: true }).click();
    const preview = await popupPromise;
    await preview.waitForLoadState('networkidle');
    expect(
        await preview
            .locator('.letterhead img')
            .evaluateAll((images) =>
                images.every(
                    (image) =>
                        image instanceof HTMLImageElement &&
                        image.complete &&
                        image.naturalWidth > 0,
                ),
            ),
    ).toBe(true);
    await expect(preview.getByAltText('Data Real Estate')).toBeVisible();
    await expect(
        preview.getByAltText('Green Vista Housing Scheme'),
    ).toBeVisible();
    await expect(
        preview.getByText(expectedStatus, { exact: true }),
    ).toBeVisible();
    await expect(preview.getByText(/Current status copy/)).toBeVisible();
    await expect(preview.locator('body')).not.toContainText('3520212345671');
    if (expectedAmount) {
        await expect(preview.locator('body')).toContainText(expectedAmount);
    }
    expect(
        await preview.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await preview.screenshot({
        path: testInfo.outputPath(`${label}-preview.png`),
        fullPage: true,
    });
    // Check the print action without opening an OS dialog or using a physical printer.
    await preview.evaluate(() => {
        window.print = () => {
            document.body.dataset.printInvoked = 'yes';
        };
    });
    await preview
        .getByRole('button', { name: 'Print / Save PDF', exact: true })
        .click();
    await expect(preview.locator('body')).toHaveAttribute(
        'data-print-invoked',
        'yes',
    );
    await preview.emulateMedia({ media: 'print' });
    await expect(
        preview.getByRole('navigation', { name: 'Document controls' }),
    ).toBeHidden();
    const file = testInfo.outputPath(`${label}.pdf`);
    await preview.pdf({
        path: file,
        format: 'A4',
        printBackground: true,
        preferCSSPageSize: true,
    });
    const pdf = readFileSync(file);
    expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');
    expect(pdf.length).toBeGreaterThan(5000);
    await preview.close();
}
