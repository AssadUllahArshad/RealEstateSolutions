import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    timeout: 60000,
    expect: { timeout: 30000 },
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:18084',
        channel: process.env.PLAYWRIGHT_CHANNEL,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        {
            name: 'mobile',
            use: { ...devices['iPhone 13'], browserName: 'chromium' },
        },
    ],
    webServer: {
        command:
            'php artisan serve --env=e2e --host=127.0.0.1 --port=18084 --no-reload',
        url: 'http://127.0.0.1:18084/login',
        reuseExistingServer: false,
        timeout: 60000,
    },
});
