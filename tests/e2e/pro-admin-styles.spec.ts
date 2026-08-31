import { test, expect } from '@playwright/test';

/**
 * Pro renders a 1,100-line admin screen using free's `edc-*` class names but
 * ships no CSS of its own, and free's enqueue was gated on the hook containing
 * 'edc-converter' — which Pro's 'edcp-kit' does not. Every Pro page therefore
 * rendered completely unstyled.
 */

const WP_ADMIN = '/wp-admin/';
const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';

async function logIn(page: import('@playwright/test').Page) {
  await page.goto(base + WP_ADMIN);
  await page.fill('input#user_login', 'admin');
  await page.fill('input#user_pass', 'admin');
  await page.click('input#wp-submit');
  await page.waitForURL(/wp-admin/, { timeout: 15000 });
}

test.describe('Pro admin styles', () => {
  test('the shared stylesheet is present on both plugins’ screens', async ({ page }) => {
    await logIn(page);

    // Free's own screen — the case that always worked, as a control.
    await page.goto(`${base}${WP_ADMIN}tools.php?page=edc-converter`);
    await expect(
      page.locator('#edc-admin-inline-css'),
      'free screen must carry the stylesheet'
    ).toHaveCount(1);

    // Pro's screen — the case that did not.
    await page.goto(`${base}${WP_ADMIN}tools.php?page=edcp-kit`);
    await expect(
      page.locator('#edc-admin-inline-css'),
      'Pro screen must carry the same stylesheet'
    ).toHaveCount(1);

    const css = await page.locator('#edc-admin-inline-css').innerText();
    expect(css.length, 'stylesheet must not be empty').toBeGreaterThan(100);
    expect(css).toContain('.edc-import-section');
  });

  test('Pro’s markup is actually styled, not just served the rules', async ({ page }) => {
    await logIn(page);
    await page.goto(`${base}${WP_ADMIN}tools.php?page=edcp-kit`);

    const section = page.locator('.edc-import-section').first();

    // The Convert tab is where the import form lives.
    if ((await section.count()) === 0) {
      await page.goto(`${base}${WP_ADMIN}tools.php?page=edcp-kit&tab=convert`);
    }

    await expect(page.locator('.edc-import-section').first()).toBeVisible();

    // An element carrying a class the stylesheet targets must end up with the
    // rule applied — proof the CSS reached the page, not merely that markup
    // with the right class name exists.
    const description = page.locator('.edc-description').first();
    await expect(description).toBeVisible();

    // Assert the exact declared value — `.edc-description { color: #555 }`.
    // A looser check (merely "not black") passes even with the stylesheet
    // missing, because WordPress admin CSS already colours this text.
    const color = await description.evaluate(
      (el) => getComputedStyle(el).color
    );
    expect(color, 'the edc-admin rule must be the one that applied').toBe('rgb(85, 85, 85)');

    await page.screenshot({
      path: 'tests/e2e/screenshots/pro-kit-page-styled.png',
      fullPage: true,
    });
  });
});
