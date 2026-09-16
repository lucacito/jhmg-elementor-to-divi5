import { expect, test, type Locator, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { login } from './support';

// Build check 7 (demo/verify.sh render). Each test names the Elementor widget it stands
// for and asserts what a viewer of the Divi draft sees, not what the converter wrote.
const OUTPUT = join(process.cwd(), 'demo', 'output');
const converted: { slug: string; kind: string; draft_id: number }[] = JSON.parse(
  readFileSync(join(OUTPUT, 'converted.json'), 'utf8'),
);

function draft(slug: string): string {
  const entry = converted.find((c) => c.slug === slug);
  if (!entry) throw new Error(`${slug} is not in converted.json; run demo/verify.sh render`);
  return `/?page_id=${entry.draft_id}&preview=true`;
}

/** Scroll through the page so lazy images load and counters, countdowns and animations run. */
async function settle(page: Page): Promise<void> {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 150));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(2500);
}

async function css(locator: Locator, property: string): Promise<string> {
  return locator.evaluate((el, prop) => getComputedStyle(el).getPropertyValue(prop), property);
}

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
  await login(page);
});

test.describe('probe: core widgets', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('core-widgets'));
    await settle(page);
  });

  test('heading (h1): renders as a styled heading', async ({ page }) => {
    await expect(page.locator('.et_pb_heading h1', { hasText: 'Probe hero heading' })).toBeVisible();
  });

  test('column with a cover background image: fills the row, not a strip', async ({ page }) => {
    // The hero's left column: Divi numbers columns per page, header/footer ones get a _tb_ suffix.
    const column = page.locator('.et_pb_column_0');
    const box = await column.boundingBox();
    expect(box?.height ?? 0).toBeGreaterThan(400);
    expect(await css(column, 'background-image')).toContain('hero-lounge');
    expect(await css(column, 'background-size')).toBe('cover');
  });

  test('heading (header_size span): keeps its 12vw typography and colour', async ({ page }) => {
    const word = page.locator('.et_pb_text span', { hasText: 'eramic' }).first();
    await expect(word).toBeVisible();
    expect(parseFloat(await css(word, 'font-size'))).toBeGreaterThan(100); // 12vw at 1440px = 172.8px
    expect(await css(word, 'color')).toBe('rgb(200, 100, 59)');
    await expect(page.locator('.et_pb_heading', { hasText: 'eramic' })).toHaveCount(0);
  });

  test('button with only a global background: accent background, white text', async ({ page }) => {
    const button = page.locator('a.et_pb_button', { hasText: 'Probe button' });
    await expect(button).toBeVisible();
    expect(await css(button, 'background-color')).toBe('rgb(200, 100, 59)');
    expect(await css(button, 'color')).toBe('rgb(255, 255, 255)');
    expect(await css(button, 'border-top-left-radius')).toBe('3px');
  });

  test('counter: bare number, percent sign only for %', async ({ page }) => {
    await page.waitForTimeout(3000); // the count-up animation
    const numbers = page.locator('.et_pb_number_counter .percent p');
    await expect(numbers).toHaveCount(2);
    await expect(numbers.nth(0)).toHaveText('58%');
    await expect(numbers.nth(1)).toHaveText('240');
  });
});

test.describe('Theme Builder header and footer (HFE templates)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('home'));
    await settle(page);
  });

  test('button (HFE header): background colour and white text', async ({ page }) => {
    const button = page.locator('.et-l--header a.et_pb_button, header a.et_pb_button', { hasText: 'Book a tour' }).first();
    await expect(button).toBeVisible();
    expect(await css(button, 'background-color')).toBe('rgb(200, 100, 59)');
    expect(await css(button, 'color')).toBe('rgb(255, 255, 255)');
  });
});
