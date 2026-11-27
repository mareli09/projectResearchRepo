import { test as base, expect, type Page } from '@playwright/test';
import { readFileSync, existsSync } from 'node:fs';

export const PASSWORD = 'Password123';
export const DEMO = {
    student: 'jasmine.villanueva@tua.edu.ph',
    adviser: 'ramon.castillo@tua.edu.ph',
    coordinator: 'liza.ferrer@tua.edu.ph',
    urdc: 'urdc.admin@tua.edu.ph',
    sysadmin: 'paolo.reyes@tua.edu.ph',
};

/**
 * Every test fails if the browser reports a Content Security Policy violation or a script error,
 * so a header change can never silently break the pages (CLAUDE.md §14).
 */
export const test = base.extend<{ consoleErrors: string[] }>({
    consoleErrors: [async ({ page }, use) => {
        const errors: string[] = [];
        page.on('console', (m) => {
            if (m.type() === 'error' && /Content Security Policy|Refused to|Uncaught/i.test(m.text())) errors.push(m.text());
        });
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
        await use(errors);
        expect(errors, 'browser errors').toEqual([]);
    }, { auto: true }],
});
export { expect };

export async function signIn(page: Page, email: string, password = PASSWORD): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('TUA email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).not.toHaveURL(/\/login/);
}

export async function signOut(page: Page): Promise<void> {
    await page.context().clearCookies();
}

/** The page never scrolls sideways (CLAUDE.md §16: scrollWidth equals the viewport width). */
export async function expectNoSideScroll(page: Page): Promise<void> {
    const [scroll, width] = await page.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
    expect(scroll, `page is ${scroll}px wide in a ${width}px viewport`).toBeLessThanOrEqual(width + 1);
}

/** The newest match in the test server's log, where MAIL_MAILER=log writes every email. */
export async function fromMailLog(pattern: RegExp, after = 0): Promise<string> {
    for (let i = 0; i < 40; i++) {
        if (existsSync('storage/logs/e2e.log')) {
            const log = readFileSync('storage/logs/e2e.log', 'utf8').slice(after).replace(/=\r?\n/g, '').replace(/=3D/g, '=');
            const all = [...log.matchAll(new RegExp(pattern.source, 'g'))];
            if (all.length) return all[all.length - 1][1] ?? all[all.length - 1][0];
        }
        await new Promise((r) => setTimeout(r, 250));
    }
    throw new Error(`No ${pattern} in the mail log`);
}

export function logSize(): number {
    return existsSync('storage/logs/e2e.log') ? readFileSync('storage/logs/e2e.log', 'utf8').length : 0;
}

export function fixture(project: string, slot: string): string {
    const ext = slot === 'abstract' || slot === 'references' ? 'docx' : 'pdf';
    const name = { abstract: 'abstract', full_paper: 'paper', imrad: 'imrad', toa: 'toa', references: 'references' }[slot];
    return `tests/Browser/.fixtures/${project}-${name}.${ext}`;
}
