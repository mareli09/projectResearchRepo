import { test, expect, expectNoSideScroll, fixture, signIn, DEMO } from './support';

/*
 * M3 critical path (CLAUDE.md §7 M3, §16): the student submits with five files and the LLM box,
 * the adviser endorses, the coordinator reviews the similarity result, the URDC approves and
 * publishes, and the study can then be found in search. Runs on desktop, Pixel 7 and iPhone 14.
 */
test('submit, endorse, review, approve and find the study', async ({ page }, info) => {
    const project = info.project.name;
    const title = `End to end ${project} study on barangay flood risk mapping ${Date.now()}`;

    // Student: Submit a study.
    await signIn(page, DEMO.student);
    await page.goto('/student/submit');
    await expectNoSideScroll(page);
    await page.getByLabel('Title *').fill(title);
    await page.getByLabel(/^Abstract \*/).fill('This capstone project maps flood risk for each barangay using rainfall records, elevation data and community reports, and shows the result on a simple web map for planners.');
    await page.getByLabel('Research type *').selectOption('capstone_project');
    await page.getByLabel('Adviser *').selectOption({ label: 'Prof. Ramon Castillo' });
    await page.getByLabel(/^Keywords \*/).fill('flood risk, barangay, web map');
    await expect(page.getByText('LLM suggestion').first()).toBeVisible();
    for (const slot of ['abstract', 'full_paper', 'imrad', 'toa', 'references']) {
        await page.locator(`#sl-${slot}`).setInputFiles(fixture(project, slot));
    }
    await page.getByRole('button', { name: 'Submit for adviser endorsement' }).click();
    await expect(page).toHaveURL(/\/student\/status\?id=SUB-\d+/, { timeout: 30_000 });
    const id = new URL(page.url()).searchParams.get('id')!;
    await expect(page.getByText(id).first()).toBeVisible();
    await expectNoSideScroll(page);
    await page.context().clearCookies();

    // Adviser: endorse.
    await signIn(page, DEMO.adviser);
    await page.goto(`/adviser/review?id=${id}`);
    await expectNoSideScroll(page);
    await expect(page.getByText(title).first()).toBeVisible();
    await page.getByRole('button', { name: 'Endorse' }).click();
    await expect(page.getByText(/endorsed/i).first()).toBeVisible();
    await page.context().clearCookies();

    // Coordinator: similarity review, decision with a reason.
    await signIn(page, DEMO.coordinator);
    await page.goto(`/coordinator/screening?id=${id}`);
    await expectNoSideScroll(page);
    await page.getByLabel('Decision').selectOption('clear');
    await page.getByLabel(/^Reason/).fill('The scope and data differ from the closest matches; sending it to the URDC.');
    await page.getByRole('button', { name: 'Record decision' }).click();
    await expect(page.getByText(/reviewed|URDC/i).first()).toBeVisible();
    await page.context().clearCookies();

    // URDC: approve and publish.
    await signIn(page, DEMO.urdc);
    await page.goto('/urdc/approvals');
    await expectNoSideScroll(page);
    const form = page.locator('form', { has: page.locator(`input[name="id"][value="${id}"]`) });
    await form.getByRole('button', { name: 'Approve and publish' }).click();
    await expect(page.getByText(/published/i).first()).toBeVisible();

    // Anyone signed in can now find it.
    await page.goto(`/search?q=${encodeURIComponent(`End to end ${project}`)}`);
    await expect(page.getByText(title).first()).toBeVisible();
    await expectNoSideScroll(page);
});

test('analytics dashboard loads with charts and exports on every screen size', async ({ page }) => {
    await signIn(page, DEMO.urdc);
    await page.goto('/urdc/analytics');
    await expect(page.locator('[data-chart]').first()).toBeVisible();
    await expectNoSideScroll(page);
    const res = await page.request.get('/urdc/analytics/export/csv');
    expect(res.status()).toBe(200);
    expect(await res.text()).toContain('Research output per year');
});
