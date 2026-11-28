import { test, expect, expectNoSideScroll, fromMailLog, logSize, signIn } from './support';

/* M1 critical paths (CLAUDE.md §7 M1, §16): register, activate, sign in, account recovery. */

test('register with a TUA email, activate from the email, sign in, then recover the password', async ({ page }, info) => {
    const email = `e2e.${info.project.name}.${Date.now()}@tua.edu.ph`;

    // A Gmail address is refused.
    await page.goto('/register');
    await expectNoSideScroll(page);
    await page.getByLabel('Full name').fill('Test Student');
    await page.getByLabel('TUA email').fill('someone@gmail.com');
    await page.getByLabel('Student').check();
    const ceis = await page.locator('#f-unit option', { hasText: 'CEIS' }).getAttribute('value');
    await page.getByLabel('College').selectOption(ceis!);
    await page.getByLabel('Program').selectOption({ index: 1 });
    await page.getByLabel('Password', { exact: true }).fill('Tua2026check');
    await page.getByLabel('Confirm password').fill('Tua2026check');
    await page.locator('input[name="privacy"]').check();
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page.getByText(/tua\.edu\.ph/).first()).toBeVisible();
    await expect(page).toHaveURL(/register/);

    // The right domain works; the account waits for activation.
    const before = logSize();
    await page.getByLabel('TUA email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('Tua2026check');
    await page.getByLabel('Confirm password').fill('Tua2026check');
    await page.locator('input[name="privacy"]').check();
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).not.toHaveURL(/\/register$/);

    const link = await fromMailLog(/(http:\/\/127\.0\.0\.1:8010\/activate\/[A-Za-z0-9]{64})/, before);
    await page.goto(link);
    await page.getByRole('button', { name: 'Activate my account' }).click();
    await expect(page.getByText('Your account is active')).toBeVisible();

    await signIn(page, email, 'Tua2026check');
    await expectNoSideScroll(page);
    // Let late requests (manifest, service worker) finish first: their responses carry the
    // session cookie and, on WebKit, could set it again after it was cleared.
    await page.waitForLoadState('networkidle');
    await page.context().clearCookies();

    // Forgot password: email, channel, 6-digit code, new password.
    await page.goto('/forgot-password');
    await page.getByLabel('TUA email').fill(email);
    await page.getByRole('button', { name: 'Continue' }).click();
    const beforeCode = logSize();
    await page.getByRole('button', { name: 'Send code' }).click();
    const code = await fromMailLog(/\b(\d{6})\b/, beforeCode);
    await page.getByLabel('6-digit code').fill(code);
    await page.getByLabel('New password', { exact: true }).fill('Tua2026newpass');
    await page.getByLabel('Confirm new password').fill('Tua2026newpass');
    await page.getByRole('button', { name: 'Set new password' }).click();
    await expect(page).toHaveURL(/login/);

    await signIn(page, email, 'Tua2026newpass');
});
