import { expect, test } from '@playwright/test';
import { PAGES, wp } from './support';

// Build check 2. PHP errors are logged to wp-content/debug.log, never displayed, so
// the log is cleared first and read after every page has loaded.
const PHP_PROBLEM = /PHP (Warning|Notice|Fatal error|Parse error)/;

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  wp('eval', "@unlink( WP_CONTENT_DIR . '/debug.log' );");
});

for (const { slug, path } of PAGES) {
  test(`${slug} returns 200 with no console errors`, async ({ page, baseURL }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
      // Only this site's scripts and resources; third-party frames (the map) are not ours.
      if (message.type() === 'error' && message.location().url.startsWith(baseURL!)) {
        errors.push(`${message.text()} (${message.location().url})`);
      }
    });
    page.on('pageerror', (error) => errors.push(error.message));

    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('load');
    expect(errors).toEqual([]);
  });
}

test('no PHP warning, notice or error was logged', () => {
  const log = wp('eval', "echo (string) @file_get_contents( WP_CONTENT_DIR . '/debug.log' );");
  expect(log.split('\n').filter((line) => PHP_PROBLEM.test(line))).toEqual([]);
});
