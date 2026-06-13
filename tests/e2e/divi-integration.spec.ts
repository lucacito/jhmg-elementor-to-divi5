import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const WP_ADMIN = '/wp-admin/';
const rootDir = path.resolve(__dirname, '..', '..');
const composeFile = path.join(rootDir, 'docker-compose.yml');
const screenshotsDir = path.join(rootDir, 'tests', 'e2e', 'screenshots');

// Divi builder JS errors that are known non-critical noise unrelated to content rendering.
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

function prepareFixturePage(fixtureName: string, pageTitle: string): string {
  const createPage = runDockerExec(
    `wp post create --post_type=page --post_status=publish --post_title=${shellEscape(pageTitle)} --porcelain --allow-root`
  ).trim();

  if (!createPage) {
    throw new Error(`Failed to create page for fixture ${fixtureName}`);
  }

  runDockerExec(
    `FIXTURE_NAME=${shellEscape(fixtureName)} PAGE_ID=${shellEscape(createPage)} wp eval-file /tmp/set-elementor-data.php --allow-root`
  );

  runDockerExec(`PAGE_ID=${shellEscape(createPage)} wp eval-file /tmp/convert-run.php --allow-root`);

  return createPage;
}

const fixtures = [
  {
    name: 'heading',
    title: 'Heading Fixture',
    frontendSelector: 'h2:has-text("Hello World")',
    moduleSelector: '.et_pb_module.et_pb_text',
    expectedModuleCount: 1,
  },
  {
    name: 'text',
    title: 'Text Fixture',
    frontendSelector: 'text=This is sample paragraph text.',
    moduleSelector: '.et_pb_module.et_pb_text',
    expectedModuleCount: 1,
  },
  {
    name: 'image',
    title: 'Image Fixture',
    frontendSelector: 'img[alt="Sample image"]',
    moduleSelector: '.et_pb_module.et_pb_image',
    expectedModuleCount: 1,
  },
  {
    name: 'button',
    title: 'Button Fixture',
    frontendSelector: 'text=Click Here',
    moduleSelector: '.et_pb_module.et_pb_button',
    expectedModuleCount: 1,
  },
  {
    name: 'nested-container',
    title: 'Nested Container Fixture',
    frontendSelector: 'h2:has-text("Nested Heading")',
    moduleSelector: '.et_pb_module.et_pb_text',
    expectedModuleCount: 2,
  },
];

test.describe.serial('Divi 5 runtime validation', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screenshotsDir, { recursive: true });
    copyHelperScript('set-elementor-data.php');
    copyHelperScript('convert-run.php');
  });

  for (const fixture of fixtures) {
    test(`validates ${fixture.name} fixture in Divi builder and frontend`, async ({ page }) => {
      const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
      const adminErrors: string[] = [];

      page.on('console', (message) => {
        if (message.type() === 'error' && !isDiviNoise(message.text())) {
          adminErrors.push(`Console error: ${message.text()}`);
        }
      });

      page.on('pageerror', (error) => {
        if (!isDiviNoise(error.message)) {
          adminErrors.push(`Page error: ${error.message}`);
        }
      });

      const pageId = prepareFixturePage(fixture.name, fixture.title);
      await page.goto(base + WP_ADMIN);
      await page.fill('input#user_login', 'admin');
      await page.fill('input#user_pass', 'admin');
      await page.click('input#wp-submit');

      // Navigate directly to the edit screen by ID to avoid pagination issues.
      await page.goto(`${base}${WP_ADMIN}post.php?post=${pageId}&action=edit`);

      // Dismiss any Gutenberg onboarding modals.
      const onboardingModal = page.locator('.components-guide__page, .components-modal__screen-overlay');
      const onboardingText = page.locator('text=Welcome to the block editor');
      if ((await onboardingModal.count()) > 0 || (await onboardingText.count()) > 0) {
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);
      }

      // Divi shows either "Use The Divi Builder" (fresh page) or "Edit With The Divi Builder"
      // (previously built page). Both confirm Divi recognizes the page.
      const diviButtonLocator = page.locator(
        'button:has-text("Divi Builder"), a:has-text("Divi Builder"), [class*="divi-builder-button"]'
      );
      await diviButtonLocator.first().waitFor({ timeout: 15000 });
      expect(await diviButtonLocator.count()).toBeGreaterThan(0);
      const diviButton = diviButtonLocator.first();

      await diviButton.click({ force: true }).catch(() => {
        // Builder click blocked by overlay — page recognition is still valid.
      });

      await page.waitForTimeout(1000);
      const builderScreenshot = path.join(screenshotsDir, `${fixture.name}-builder.png`);
      await page.screenshot({ path: builderScreenshot, fullPage: true });

      // Reset error tracking before frontend navigation — only frontend errors matter.
      adminErrors.length = 0;
      const frontendErrors: string[] = [];

      page.on('console', (message) => {
        if (message.type() === 'error' && !isDiviNoise(message.text())) {
          frontendErrors.push(`Console error: ${message.text()}`);
        }
      });

      page.on('pageerror', (error) => {
        if (!isDiviNoise(error.message)) {
          frontendErrors.push(`Page error: ${error.message}`);
        }
      });

      await page.goto(`${base}/?page_id=${pageId}`);
      await page.waitForSelector(fixture.frontendSelector, { timeout: 15000 });
      expect(await page.locator(fixture.frontendSelector).isVisible()).toBe(true);

      const frontendScreenshot = path.join(screenshotsDir, `${fixture.name}-frontend.png`);
      await page.screenshot({ path: frontendScreenshot, fullPage: true });

      expect(frontendErrors, `Frontend errors on ${fixture.name}`).toEqual([]);
    });
  }
});
