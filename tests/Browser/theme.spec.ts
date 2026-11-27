import { test, expect, expectNoSideScroll, signIn, DEMO } from './support';

/* Theme color and appearance (CLAUDE.md §9, §16 "theme change"), saved to the account. */
test('pick a theme color and dark mode, kept after reload, then reset', async ({ page }) => {
    await signIn(page, DEMO.adviser);
    await page.goto('/adviser');
    await expectNoSideScroll(page);
    const accent = () => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent').trim());
    const before = await accent();

    // From the account menu, which is also there on phones.
    await page.getByLabel('Account menu').click();
    await page.getByRole('button', { name: 'Theme and appearance' }).click();
    await page.getByRole('button', { name: 'Maroon' }).click();
    await expect.poll(accent).not.toBe(before);
    await page.getByRole('button', { name: 'Dark' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    // Saved to the account: still there after a reload (wait for the debounced save).
    await page.waitForResponse((r) => r.url().includes('/account/theme') && r.status() === 200);
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expectNoSideScroll(page);

    await page.getByLabel('Account menu').click();
    await page.getByRole('button', { name: 'Theme and appearance' }).click();
    await page.getByRole('link', { name: 'Reset' }).click();
    await page.waitForResponse((r) => r.url().includes('/account/theme'));
});
