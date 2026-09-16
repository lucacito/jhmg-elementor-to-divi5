import { test, type Page } from '@playwright/test';
import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { PAGES, login } from './support';

// Build check 4. verify.sh runs this twice: PW_STAGE=originals under Hello Elementor,
// then PW_STAGE=converted under Divi after commit-conversions.php.
const OUTPUT = join(process.cwd(), 'demo', 'output');
const SHOTS = join(OUTPUT, 'screenshots');
mkdirSync(SHOTS, { recursive: true });

test.describe.configure({ mode: 'serial' });

/** Scroll through the page so lazy images load and entrance animations finish. */
async function settle(page: Page): Promise<void> {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 150));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(1000);
}

if (process.env.PW_STAGE === 'originals') {
  for (const { slug, path } of PAGES) {
    test(`Elementor original: ${slug}`, async ({ page }) => {
      await page.goto(path);
      await settle(page);
      await page.screenshot({ path: join(SHOTS, `${slug}-elementor.png`), fullPage: true });
    });
  }
}

if (process.env.PW_STAGE === 'converted') {
  const converted: { slug: string; draft_id: number }[] = JSON.parse(
    readFileSync(join(OUTPUT, 'converted.json'), 'utf8'),
  );

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  for (const { slug, draft_id } of converted) {
    test(`Divi draft: ${slug}`, async ({ page }) => {
      await page.goto(`/?page_id=${draft_id}&preview=true`);
      await settle(page);
      await page.screenshot({ path: join(SHOTS, `${slug}-divi.png`), fullPage: true });
    });
  }
}
