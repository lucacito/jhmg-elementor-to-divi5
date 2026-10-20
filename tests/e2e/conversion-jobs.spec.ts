import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import * as path from 'path';

/**
 * Tools → Conversion Jobs, driven in a real browser against a real WordPress
 * (docker-compose) seeded with Elementor pages. PHPUnit proves the pieces; this
 * proves the screen: selecting, the start-panel rules, a run that survives the
 * browser, and Undo.
 */

const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
const root = path.resolve(__dirname, '../..');
const SHOTS = process.env.EDC_SHOTS || '';

function wp(args: string): any {
  const out = execSync(`docker-compose exec -T wordpress wp eval-file /tmp/jobs-e2e.php ${args} --allow-root 2>&1`, { cwd: root }).toString();
  const line = out.split('\n').find((l) => l.startsWith('JSON:'));
  if (!line) throw new Error('no JSON from helper: ' + out.slice(0, 400));
  return JSON.parse(line.slice(5));
}

function wpRaw(cmd: string) {
  return execSync(`docker-compose exec -T wordpress wp ${cmd} --allow-root 2>&1`, { cwd: root }).toString();
}

async function logIn(page: Page) {
  await page.goto(base + '/wp-login.php');
  await page.fill('input#user_login', 'admin');
  await page.fill('input#user_pass', 'admin');
  await page.click('input#wp-submit');
  await page.waitForURL(/wp-admin/, { timeout: 15000 });
}

const jobsUrl = `${base}/wp-admin/tools.php?page=edc-converter-jobs`;
const bar = (page: Page, key: string) => page.locator(`[data-edc-bar="${key}"]`);
const num = async (page: Page, key: string) => Number((await bar(page, key).innerText()).replace(/[^0-9]/g, ''));
async function shot(page: Page, name: string) {
  if (SHOTS) await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: false });
}

test.beforeAll(() => {
  execSync('docker-compose cp tests/e2e/helpers/jobs-e2e.php wordpress:/tmp/jobs-e2e.php', { cwd: root, stdio: 'ignore' });
  wp('reset');
});
test.afterAll(() => { wp('reset'); });

test.describe.serial('Conversion Jobs (Pro: no limit)', () => {
  test('select two pages, convert them as copies, and watch the numbers move', async ({ page }) => {
    test.setTimeout(90000);
    wp('seed 5');
    await logIn(page);
    await page.goto(jobsUrl);

    await expect(page.locator('#edc-bar')).toBeVisible();
    const pendingBefore = await num(page, 'pending');
    const convertedBefore = await num(page, 'converted');
    expect(pendingBefore).toBeGreaterThanOrEqual(5);
    await expect(page.getByText('E2E Job Page 001')).toBeVisible();

    await page.locator('#edc-pending-table input[name="post_ids[]"]').nth(0).check();
    await page.locator('#edc-pending-table input[name="post_ids[]"]').nth(1).check();
    await expect(page.locator('.edc-selected-count').first()).toHaveText('2');
    await page.locator('[data-edc-action="convert-selected"]').click();

    // 2 pages: copy is preselected, nothing to confirm.
    const panel = page.locator('#edc-start-panel');
    await expect(panel).toBeVisible();
    await expect(panel.locator('input[value="copy"]')).toBeChecked();
    await expect(panel.locator('.edc-start-panel__confirm')).toBeHidden();
    await expect(panel.locator('[data-edc-action="start-job"]')).toBeEnabled();
    await shot(page, '1-start-panel-small');

    await panel.locator('[data-edc-action="start-job"]').click();

    const job = page.locator('.edc-job').first();
    await expect(job).toBeVisible();
    await expect(job.locator('.edc-job__status')).toHaveText('Done', { timeout: 30000 });
    await expect(job.locator('.edc-job__counts')).toHaveText('2 of 2');

    // The bar moved without a reload.
    await expect.poll(() => num(page, 'converted')).toBe(convertedBefore + 2);
    await expect.poll(() => num(page, 'pending')).toBe(pendingBefore - 2);

    // A job you just watched finish stays open under you; on the next load it is collapsed.
    await page.reload();
    const closed = page.locator('.edc-job').first();
    await expect(closed).not.toHaveAttribute('open', '');
    await expect(closed.locator('.edc-job__items')).toBeHidden(); // results are not even fetched until opened
    await expect(closed.locator('.edc-items')).toHaveCount(0);
    await closed.locator('summary').click();
    await expect(closed.locator('table.edc-items')).toBeVisible();
    await expect(closed.locator('.edc-state--converted')).toHaveCount(2);
    const builder = closed.locator('a', { hasText: 'Open in Divi 5 builder' }).first();
    await expect(builder).toHaveAttribute('href', /et_fb=1/);
    await shot(page, '2-job-done');

    const st = wp('state');
    expect(st.copies).toBe(2);
    expect(st.still_elementor).toBe(5); // originals untouched
  });

  test('converting the same pages again is refused: they are no longer pending', async ({ page }) => {
    await logIn(page);
    await page.goto(jobsUrl);
    await expect(page.getByText('E2E Job Page 001')).toHaveCount(0);
    await expect(page.getByText('E2E Job Page 003')).toBeVisible();
  });

  test('more than 50 pages: replace is preselected, warned about, and needs a tick', async ({ page }) => {
    test.setTimeout(180000);
    wp('reset');
    wp('seed 60');
    await logIn(page);
    await page.goto(jobsUrl);

    const total = await num(page, 'pending');
    await page.locator('[data-edc-action="convert-all"]').click();

    const panel = page.locator('#edc-start-panel');
    await expect(panel).toBeVisible();
    await expect(panel.locator('input[value="replace"]')).toBeChecked();
    await expect(panel.locator('.edc-start-panel__warning')).toBeVisible();
    await expect(panel.locator('.edc-start-panel__warning')).toContainText('Undo restores it');
    await expect(panel.locator('.edc-start-panel__confirm')).toBeVisible();
    await expect(panel.locator('[data-edc-action="start-job"]')).toBeDisabled();
    await shot(page, '3-start-panel-bulk');

    // Switching to copies keeps the confirmation requirement.
    await panel.locator('input[value="copy"]').check();
    await expect(panel.locator('.edc-start-panel__warning')).toBeHidden();
    await expect(panel.locator('[data-edc-action="start-job"]')).toBeDisabled();
    await panel.locator('input[value="replace"]').check();

    await panel.locator('input[name="edc_confirm"]').check();
    await expect(panel.locator('[data-edc-action="start-job"]')).toBeEnabled();
    await panel.locator('[data-edc-action="start-job"]').click();

    const job = page.locator('.edc-job').first();
    await expect(job.locator('.edc-job__status')).toHaveText('Done', { timeout: 120000 });
    await expect(job.locator('.edc-job__counts')).toHaveText(`${total} of ${total}`);
    await expect.poll(() => num(page, 'pending')).toBe(0);

    const st = wp('state');
    expect(st.replaced).toBe(60);
    expect(st.copies).toBe(0); // nothing duplicated
    expect(st.still_elementor).toBe(0);
    await shot(page, '4-bulk-done');
  });

  test('Undo on a replacing job restores every original page', async ({ page }) => {
    test.setTimeout(120000);
    await logIn(page);
    await page.goto(jobsUrl);
    const job = page.locator('.edc-job').first();
    await expect(job).toContainText('originals replaced');

    page.once('dialog', (d) => d.accept());
    await job.locator('summary').click();
    await job.locator('a', { hasText: 'Undo job' }).click();

    await page.waitForURL(/edc-converter-jobs/);
    await expect(page.locator('.edc-rollback-notice')).toContainText('restored');
    const st = wp('state');
    expect(st.still_elementor).toBe(60); // Elementor owns them again
    expect(st.flagged).toBe(0); // so they are pending again
    await expect.poll(async () => { await page.reload(); return num(page, 'pending'); }).toBeGreaterThanOrEqual(60);
    const after = page.locator('.edc-job').first();
    await after.locator('summary').click(); // collapsed: finished jobs are
    await expect(after.locator('.edc-job__undone')).toBeVisible();
    await expect(after.locator('a', { hasText: 'Undo job' })).toHaveCount(0);
  });
});

test.describe.serial('Conversion Jobs (free: one page at a time)', () => {
  test.beforeAll(() => {
    wp('reset');
    wpRaw('plugin deactivate jhmg-converter-for-elementor-to-divi-pro');
    wp('seed 4');
  });
  test.afterAll(() => {
    wpRaw('plugin activate jhmg-converter-for-elementor-to-divi-pro');
  });

  test('convert all is an inline Pro upsell that quotes the pending count', async ({ page }) => {
    await logIn(page);
    await page.goto(jobsUrl);

    const remaining = await num(page, 'pending');
    const upsell = page.locator('a.edc-upsell');
    await expect(upsell).toHaveText(`Convert all ${remaining} remaining with Pro`);
    await expect(upsell).toHaveAttribute('href', /divi5lab\.com\/plugins\/elementor-to-divi-5/);
    await expect(page.locator('[data-edc-action="convert-all"]')).toHaveCount(0);
    await shot(page, '5-free-upsell');
  });

  test('selecting several pages shows the upsell instead of a panel the server would refuse', async ({ page }) => {
    await logIn(page);
    await page.goto(jobsUrl);

    await page.locator('#edc-select-all').check();
    await page.locator('[data-edc-action="convert-selected"]').click();

    await expect(page.locator('#edc-start-panel')).toBeHidden();
    await expect(page.locator('.edc-upsell-inline a')).toContainText('selected pages with Pro');
  });

  test('a single page converts normally on free', async ({ page }) => {
    test.setTimeout(60000);
    await logIn(page);
    await page.goto(jobsUrl);

    await page.locator('#edc-pending-table input[name="post_ids[]"]').first().check();
    await page.locator('[data-edc-action="convert-selected"]').click();
    await page.locator('#edc-start-panel [data-edc-action="start-job"]').click();

    await expect(page.locator('.edc-job').first().locator('.edc-job__status')).toHaveText('Done', { timeout: 30000 });
  });

  test('the server refuses a multi-page job even if the browser is bypassed', async ({ page }) => {
    await logIn(page);
    await page.goto(jobsUrl);

    const r = await page.evaluate(async () => {
      const cfg = (window as any).edcJobs;
      const ids = Array.from(document.querySelectorAll<HTMLInputElement>('#edc-pending-table input[name="post_ids[]"]')).slice(0, 2).map((i) => i.value);
      const body = new URLSearchParams({ action: 'edc_jobs_create', nonce: cfg.nonce, mode: 'copy' });
      ids.forEach((i) => body.append('post_ids[]', i));
      return (await fetch(cfg.ajaxUrl, { method: 'POST', body })).json();
    });
    expect(r.ok).toBe(false);
    expect(r.code).toBe('pro_required');
  });
});
