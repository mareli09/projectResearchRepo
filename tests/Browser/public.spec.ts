import { test, expect, expectNoSideScroll } from './support';

/* Guests: landing page, public catalog, installable app (CLAUDE.md §4, §8, §16). */

test('landing page works on every screen size and can be installed', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', /manifest\.webmanifest/);
    await expectNoSideScroll(page);

    const manifest = await (await page.request.get('/manifest.webmanifest')).json();
    expect(manifest.short_name).toBe('TUA Repository');
    expect(manifest.display).toBe('standalone');
});

test('a guest searches the public catalog and opens a study, without files', async ({ page }) => {
    await page.goto('/catalog?q=flood');
    await expectNoSideScroll(page);
    const first = page.locator('a[href*="/catalog/TUA-"]').first();
    await expect(first).toBeVisible();
    await first.click();
    await expect(page.getByText('Abstract', { exact: false }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: /download/i })).toHaveCount(0);
    await expectNoSideScroll(page);
});

test('security headers are sent', async ({ page }) => {
    const res = await page.goto('/login');
    const h = res!.headers();
    expect(h['content-security-policy']).toContain("default-src 'self'");
    expect(h['content-security-policy']).toContain("object-src 'none'");
    expect(h['x-content-type-options']).toBe('nosniff');
    expect(h['x-frame-options']).toBe('SAMEORIGIN');
});
