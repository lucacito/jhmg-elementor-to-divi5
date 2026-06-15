import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const WP_ADMIN = '/wp-admin/';
const rootDir = path.resolve(__dirname, '..', '..');
const composeFile = path.join(rootDir, 'docker-compose.yml');
const screenshotsDir = path.join(rootDir, 'tests', 'e2e', 'screenshots');

const KNOWN_DIVI_NOISE = [
  'Transition was skipped',
  'ResizeObserver loop',
];

function isDiviNoise(msg: string): boolean {
  return KNOWN_DIVI_NOISE.some((s) => msg.includes(s));
}

function shellEscape(value: string): string {
  return `'${value.replace(/'/g, `'\\''`)}'`;
}

function getContainerId(): string {
  const result = execFileSync('docker-compose', ['-f', composeFile, 'ps', '-q', 'wordpress'], {
    cwd: rootDir,
    encoding: 'utf8',
  }).toString().trim();

  if (!result) {
    throw new Error('Unable to find running WordPress container via docker-compose.');
  }

  return result;
}

function copyHelperScript(scriptName: string): void {
  const containerId = getContainerId();
  execFileSync('docker', ['cp', path.join(rootDir, 'scripts', 'docker', scriptName), `${containerId}:/tmp/${scriptName}`], {
    cwd: rootDir,
    stdio: 'inherit',
  });
}

function runDockerExec(command: string): string {
  const containerId = getContainerId();
  return execFileSync('docker', ['exec', '-i', containerId, 'bash', '-lc', command], {
    cwd: rootDir,
    encoding: 'utf8',
  });
}

test.describe.serial('Conversion workflow: Elementor → Divi 5', () => {
  let sourcePageId: string;
  let convertedPageId: string;

  test.beforeAll(() => {
    fs.mkdirSync(screenshotsDir, { recursive: true });

    copyHelperScript('set-elementor-data.php');
    copyHelperScript('convert-to-new-page.php');

    // The fixtures/ directory is volume-mounted read-only into the container
    // at ABSPATH/fixtures/, so hero-page.json is already accessible without docker cp.

    // Create the source Elementor page.
    sourcePageId = runDockerExec(
      `wp post create --post_type=page --post_status=publish --post_title=${shellEscape('Hero Page (Elementor)')} --porcelain --allow-root`
    ).trim();

    if (!sourcePageId) {
      throw new Error('Failed to create source Elementor page');
    }

    // Attach the hero-page Elementor fixture to it.
    runDockerExec(
      `FIXTURE_NAME=${shellEscape('hero-page')} PAGE_ID=${shellEscape(sourcePageId)} wp eval-file /tmp/set-elementor-data.php --allow-root`
    );

    // Run the conversion — produces a new Divi 5 page.
    convertedPageId = runDockerExec(
      `SOURCE_PAGE_ID=${shellEscape(sourcePageId)} wp eval-file /tmp/convert-to-new-page.php --allow-root`
    ).trim();

    if (!convertedPageId) {
      throw new Error('convert-to-new-page.php did not return a page ID');
    }
  });

  test('converted page is recognized by Divi builder', async ({ page }) => {
    const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !isDiviNoise(msg.text())) {
        errors.push(`Console error: ${msg.text()}`);
      }
    });
    page.on('pageerror', (err) => {
      if (!isDiviNoise(err.message)) errors.push(`Page error: ${err.message}`);
    });

    // Log in.
    await page.goto(base + WP_ADMIN);
    await page.fill('input#user_login', 'admin');
    await page.fill('input#user_pass', 'admin');
    await page.click('input#wp-submit');

    // Navigate directly to the converted page's edit screen.
    await page.goto(`${base}${WP_ADMIN}post.php?post=${convertedPageId}&action=edit`);

    // Dismiss any Gutenberg onboarding modal.
    const onboardingModal = page.locator('.components-guide__page, .components-modal__screen-overlay');
    if ((await onboardingModal.count()) > 0) {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);
    }

    // Divi shows "Use The Divi Builder" (fresh page) or "Edit With The Divi Builder" (built page).
    const diviButton = page.locator(
      'button:has-text("Divi Builder"), a:has-text("Divi Builder"), [class*="divi-builder-button"]'
    );
    await diviButton.first().waitFor({ state: 'attached', timeout: 15000 });
    expect(await diviButton.count()).toBeGreaterThan(0);

    await page.screenshot({
      path: path.join(screenshotsDir, 'conversion-workflow-builder.png'),
      fullPage: true,
    });
  });

  test('converted page renders on the frontend with all content', async ({ page }) => {
    const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !isDiviNoise(msg.text())) {
        errors.push(`Console error: ${msg.text()}`);
      }
    });
    page.on('pageerror', (err) => {
      if (!isDiviNoise(err.message)) errors.push(`Page error: ${err.message}`);
    });

    await page.goto(`${base}/?page_id=${convertedPageId}`);

    // Header / navigation menu.
    await page.waitForSelector('nav.et-menu-nav, #main-nav, #top-header', { timeout: 10000 });
    expect(await page.locator('nav.et-menu-nav, #main-nav, #top-header').count()).toBeGreaterThan(0);

    // At least one Divi section must be rendered.
    await page.waitForSelector('.et_pb_section', { timeout: 15000 });
    const sectionCount = await page.locator('.et_pb_section').count();
    expect(sectionCount, 'Expected at least 3 Divi sections').toBeGreaterThanOrEqual(3);

    // Hero heading.
    await page.waitForSelector('h1:has-text("Welcome to Our Site"), h2:has-text("Welcome to Our Site")', { timeout: 10000 });
    expect(
      await page.locator('h1:has-text("Welcome to Our Site"), h2:has-text("Welcome to Our Site")').isVisible()
    ).toBe(true);

    // Hero CTA button.
    expect(await page.locator('text=Get Started').isVisible()).toBe(true);

    // About section image.
    await page.waitForSelector('img[alt="About our team"]', { timeout: 10000 });
    expect(await page.locator('img[alt="About our team"]').isVisible()).toBe(true);

    // CTA section heading and button.
    expect(await page.locator('h2:has-text("Ready to Begin?")').isVisible()).toBe(true);
    expect(await page.locator('text=Contact Us').isVisible()).toBe(true);

    await page.screenshot({
      path: path.join(screenshotsDir, 'conversion-workflow-frontend.png'),
      fullPage: true,
    });

    expect(errors, 'Frontend JS errors on converted page').toEqual([]);
  });
});

test.describe.serial('Stress test: imperfect Elementor page conversion', () => {
  let stressSourceId: string;
  let stressConvertedId: string;

  test.beforeAll(() => {
    fs.mkdirSync(screenshotsDir, { recursive: true });

    copyHelperScript('set-elementor-data.php');
    copyHelperScript('convert-to-new-page.php');

    stressSourceId = runDockerExec(
      `wp post create --post_type=page --post_status=publish --post_title=${shellEscape('Stress Test (Elementor)')} --porcelain --allow-root`
    ).trim();

    if (!stressSourceId) {
      throw new Error('Failed to create stress test source page');
    }

    runDockerExec(
      `FIXTURE_NAME=${shellEscape('stress-test')} PAGE_ID=${shellEscape(stressSourceId)} wp eval-file /tmp/set-elementor-data.php --allow-root`
    );

    stressConvertedId = runDockerExec(
      `SOURCE_PAGE_ID=${shellEscape(stressSourceId)} wp eval-file /tmp/convert-to-new-page.php --allow-root`
    ).trim();

    if (!stressConvertedId) {
      throw new Error('convert-to-new-page.php did not return a page ID for stress test');
    }
  });

  test('stress-test page renders without breaking layout', async ({ page }) => {
    const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !isDiviNoise(msg.text())) {
        errors.push(`Console error: ${msg.text()}`);
      }
    });
    page.on('pageerror', (err) => {
      if (!isDiviNoise(err.message)) errors.push(`Page error: ${err.message}`);
    });

    await page.goto(`${base}/?page_id=${stressConvertedId}`);

    // Page must have at least 1 rendered Divi section despite unsupported widgets.
    await page.waitForSelector('.et_pb_section', { timeout: 15000 });
    expect(await page.locator('.et_pb_section').count()).toBeGreaterThanOrEqual(1);

    // Content from section 1 (background + multiple heading tags).
    await page.waitForSelector(
      'h1:has-text("Stress Test Heading H1"), h2:has-text("Stress Test Heading H1")',
      { timeout: 10000 }
    );

    // Buttons from sections 2 and 4.
    expect(await page.locator('text=External Link').isVisible()).toBe(true);
    expect(await page.locator('text=Explore Now').isVisible()).toBe(true);

    // Image with alt text (section 2).
    expect(await page.locator('img[alt="Team photo"]').isVisible()).toBe(true);

    // Heading from nested container (section 5).
    expect(await page.locator('h2:has-text("Nested Container Heading")').isVisible()).toBe(true);

    await page.screenshot({
      path: path.join(screenshotsDir, 'stress-test-frontend.png'),
      fullPage: true,
    });

    expect(errors, 'Frontend JS errors on stress-test page').toEqual([]);
  });

  test('conversion report has correct counts and warnings', () => {
    // Read the stored _edc_conversion_report from WP post meta.
    const raw = runDockerExec(
      `wp post meta get ${shellEscape(stressConvertedId)} _edc_conversion_report --allow-root`
    ).trim();

    expect(raw, '_edc_conversion_report meta must exist').toBeTruthy();

    const report = JSON.parse(raw) as {
      converted: Record<string, number>;
      warnings: string[];
      skipped_settings: string[];
      unsupported: Array<{ widgetType: string }>;
    };

    // Converted element counts.
    expect(report.converted.heading, 'heading count').toBeGreaterThanOrEqual(6);
    expect(report.converted.text,    'text count').toBeGreaterThanOrEqual(2);
    expect(report.converted.image,   'image count').toBeGreaterThanOrEqual(2);
    expect(report.converted.button,  'button count').toBeGreaterThanOrEqual(3);

    // Unsupported widgets: e-carousel, e-form, e-video.
    expect(report.unsupported.length, 'unsupported count').toBeGreaterThanOrEqual(3);
    const unsupportedTypes = report.unsupported.map((u) => u.widgetType);
    expect(unsupportedTypes).toContain('e-carousel');
    expect(unsupportedTypes).toContain('e-form');
    expect(unsupportedTypes).toContain('e-video');

    // Warning: empty column (section 3 has only unsupported widgets).
    const hasEmptyWarning = report.warnings.some((w) => w.includes('Empty column'));
    expect(hasEmptyWarning, 'warning about empty column').toBe(true);

    // Warning: image without alt text.
    const hasAltWarning = report.warnings.some((w) => w.includes('missing alt text'));
    expect(hasAltWarning, 'warning about missing alt text').toBe(true);

    // Skipped settings: section 1 has custom_padding (background_color is now fully mapped by StyleMapper).
    const hasPadding = report.skipped_settings.some((s) => s.includes('custom_padding'));
    expect(hasPadding, 'skipped custom_padding').toBe(true);
  });
});
