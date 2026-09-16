import { expect, test, type Locator, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { login } from './support';

// Build check 7 (demo/verify.sh render). Each test names the Elementor widget it stands
// for and asserts what a viewer of the Divi draft sees, not what the converter wrote.
// The number of $photo() items in demo/content/pages/spaces.php's filterable gallery.
const SPACES_GALLERY_IMAGES = 8;

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

  test('image carousel (4 slides): a four-column gallery of full-size images', async ({ page }) => {
    const images = page.locator('.et_pb_gallery').first().locator('.et_pb_gallery_image img');
    await expect(images).toHaveCount(4);
    for (const img of await images.all()) {
      expect((await img.boundingBox())?.width ?? 0).toBeGreaterThan(200);
    }
    // Divi always prints the pagination container; only its page links would mean a second page.
    await expect(page.locator('.et_pb_gallery').first().locator('.et_pb_gallery_pagination a')).toHaveCount(0);
  });

  test('image gallery: three items', async ({ page }) => {
    await expect(page.locator('.et_pb_gallery').nth(1).locator('.et_pb_gallery_item')).toHaveCount(3);
  });

  test('google_maps: the embed loads a Google map at least 300px tall', async ({ page }) => {
    const frame = page.locator('iframe[src*="maps.google.com"]').first();
    await frame.scrollIntoViewIfNeeded(); // loading="lazy", as in Elementor's own embed
    await expect(frame).toBeVisible();
    expect((await frame.boundingBox())?.height ?? 0).toBeGreaterThan(300);
    await expect(page.frameLocator('iframe[src*="maps.google.com"]').locator('body')).toContainText('Google', { timeout: 20_000 });
  });

  test('social-icons: Instagram and LinkedIn, nothing else', async ({ page }) => {
    // The probe's second section; the Theme Builder footer has its own social list.
    const items = page.locator('.et_pb_section_1 .et_pb_social_media_follow li');
    await expect(items).toHaveCount(2);
    await expect(items.nth(0)).toHaveClass(/et-social-instagram/);
    await expect(items.nth(1)).toHaveClass(/et-social-linkedin/);
  });
});

test.describe('spaces', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('spaces'));
    await settle(page);
  });

  test('filterable gallery (EAEL): exactly its own images, not the media library', async ({ page }) => {
    await expect(page.locator('.et_pb_gallery .et_pb_gallery_item')).toHaveCount(SPACES_GALLERY_IMAGES);
    await expect(page.locator('.et_pb_gallery_pagination a')).toHaveCount(0);
  });

  test('flip boxes (EAEL): titles and text render as blurbs', async ({ page }) => {
    const blurbs = page.locator('.et_pb_blurb');
    expect(await blurbs.count()).toBeGreaterThanOrEqual(3);
    await expect(blurbs.first().locator('.et_pb_module_header')).not.toBeEmpty();
    await expect(blurbs.first().locator('.et_pb_blurb_description')).not.toBeEmpty();
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

  test('navigation-menu (HFE): no white bar behind the menu', async ({ page }) => {
    const menu = page.locator('.et_pb_menu').first();
    await expect(menu).toBeVisible();
    expect(await css(menu, 'background-color')).toBe('rgba(0, 0, 0, 0)');
  });

  test('footer social-icons (HFE footer): Instagram and LinkedIn', async ({ page }) => {
    const items = page.locator('.et-l--footer .et_pb_social_media_follow li, footer .et_pb_social_media_follow li');
    await expect(items).toHaveCount(2);
    await expect(items.nth(0)).toHaveClass(/et-social-instagram/);
    await expect(items.nth(1)).toHaveClass(/et-social-linkedin/);
  });
});

test.describe('home', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('home'));
    await settle(page);
  });

  test('info boxes (EAEL): title, body and icon render', async ({ page }) => {
    const blurbs = page.locator('.et_pb_blurb');
    expect(await blurbs.count()).toBeGreaterThanOrEqual(3);
    await expect(blurbs.first().locator('.et_pb_module_header')).not.toBeEmpty();
    await expect(blurbs.first().locator('.et_pb_blurb_description')).not.toBeEmpty();
    await expect(blurbs.first().locator('.et_pb_main_blurb_image .et-pb-icon')).toBeVisible();
  });

  test('testimonials (EAEL): portrait, author and position', async ({ page }) => {
    const first = page.locator('.et_pb_testimonial').first();
    await expect(first.locator('.et_pb_testimonial_author')).toHaveText('Priya Raman');
    await expect(first.locator('.et_pb_testimonial_position')).toHaveText('Brand designer');
    await expect(first.locator('.et_pb_testimonial_portrait')).toBeVisible();
    expect(await css(first.locator('.et_pb_testimonial_portrait'), 'background-image')).toContain('member-1');
  });
});

test.describe('memberships', () => {
  test('pricing tables (EAEL): title, price, period, features, button', async ({ page }) => {
    await page.goto(draft('memberships'));
    await settle(page);
    const tables = page.locator('.et_pb_pricing_table');
    await expect(tables).toHaveCount(3);
    const first = tables.first();
    await expect(first.locator('.et_pb_pricing_title')).not.toBeEmpty();
    await expect(first.locator('.et_pb_sum')).not.toBeEmpty();
    await expect(first.locator('.et_pb_frequency')).not.toBeEmpty();
    expect(await first.locator('ul.et_pb_pricing li').count()).toBeGreaterThan(0);
    await expect(first.locator('a.et_pb_button')).toBeVisible();
  });

  test.fixme('pricing tables (EAEL): subtitle (needs the reseeded site, Task 15)', async ({ page }) => {
    await page.goto(draft('memberships'));
    await expect(page.locator('.et_pb_pricing_table').first().locator('.et_pb_best_value')).not.toBeEmpty();
  });
});

test.describe('about', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('about'));
    await settle(page);
  });

  test('team members (EAEL): photo, position and description', async ({ page }) => {
    const members = page.locator('.et_pb_team_member');
    await expect(members).toHaveCount(4);
    await expect(members.first().locator('.et_pb_team_member_image img')).toBeVisible();
    await expect(members.first().locator('.et_pb_member_position')).toHaveText('Founder');
    await expect(members.first().locator('.et_pb_team_member_description_content')).toContainText('design studio');
    await expect(members.first().locator('.et_pb_linkedin_icon')).toHaveCount(1);
  });
});

test.describe('events', () => {
  test('countdown (EAEL): counts down to a future date', async ({ page }) => {
    await page.goto(draft('events'));
    await settle(page);
    const timer = page.locator('.et_pb_countdown_timer').first();
    const end = Number(await timer.locator('[data-end-timestamp]').first().getAttribute('data-end-timestamp'));
    expect(end).toBeGreaterThan(Date.now() / 1000);
    await expect(timer.locator('.days .value')).not.toHaveText('000');
  });
});
