import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const ROOT = process.cwd();

/*
 * End-to-end and mobile tests (CLAUDE.md §16 "Mobile tests", §18 definition of done).
 * The critical paths run on a desktop browser, "Pixel 7" and "iPhone 14".
 *
 * The app runs on PHP's built-in server against its own database (tua_repository_e2e), prepared by
 * `php artisan e2e:prepare` in the global setup, so the tests never touch the development data.
 * Run: npm run test:e2e   (first time: npx playwright install chromium webkit)
 */
const PORT = 8010;
const BASE = `http://127.0.0.1:${PORT}`;

export default defineConfig({
    testDir: './tests/Browser',
    globalSetup: './tests/Browser/global-setup.ts',
    timeout: 90_000,
    expect: { timeout: 15_000 },
    // PHP's built-in server answers one request at a time, so one test at a time.
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: BASE,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        locale: 'en-PH',
        timezoneId: 'Asia/Manila',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'pixel-7', use: { ...devices['Pixel 7'] } },
        { name: 'iphone-14', use: { ...devices['iPhone 14'] } },
    ],
    webServer: {
        // Laravel's router script for PHP's server expects to run from public/.
        command: `php -S 127.0.0.1:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: resolve(ROOT, 'public'),
        url: `${BASE}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: {
            APP_ENV: 'local',
            APP_DEBUG: 'true',
            APP_URL: BASE,
            DB_DATABASE: 'tua_repository_e2e',
            MAIL_MAILER: 'log',
            LOG_CHANNEL: 'single',
            LOG_PATH: resolve(ROOT, 'storage/logs/e2e.log'),
            QUEUE_CONNECTION: 'sync',
            QUEUE_LONG_CONNECTION: 'sync',
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            SESSION_SECURE_COOKIE: 'false',
            LLM_ENABLED: 'false',
            // The analytics service is not started for these tests: screening shows "queued".
            ANALYTICS_URL: 'http://127.0.0.1:1',
            ANALYTICS_TIMEOUT: '1',
        },
    },
});
