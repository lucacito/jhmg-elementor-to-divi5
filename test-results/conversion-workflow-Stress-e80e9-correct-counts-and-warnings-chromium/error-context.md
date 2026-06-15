# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: conversion-workflow.spec.ts >> Stress test: imperfect Elementor page conversion >> conversion report has correct counts and warnings
- Location: tests/e2e/conversion-workflow.spec.ts:256:7

# Error details

```
Error: unsupported count

expect(received).toBeGreaterThanOrEqual(expected)

Expected: >= 3
Received:    2
```

# Test source

```ts
  178 |     expect(errors, 'Frontend JS errors on converted page').toEqual([]);
  179 |   });
  180 | });
  181 | 
  182 | test.describe.serial('Stress test: imperfect Elementor page conversion', () => {
  183 |   let stressSourceId: string;
  184 |   let stressConvertedId: string;
  185 | 
  186 |   test.beforeAll(() => {
  187 |     fs.mkdirSync(screenshotsDir, { recursive: true });
  188 | 
  189 |     copyHelperScript('set-elementor-data.php');
  190 |     copyHelperScript('convert-to-new-page.php');
  191 | 
  192 |     stressSourceId = runDockerExec(
  193 |       `wp post create --post_type=page --post_status=publish --post_title=${shellEscape('Stress Test (Elementor)')} --porcelain --allow-root`
  194 |     ).trim();
  195 | 
  196 |     if (!stressSourceId) {
  197 |       throw new Error('Failed to create stress test source page');
  198 |     }
  199 | 
  200 |     runDockerExec(
  201 |       `FIXTURE_NAME=${shellEscape('stress-test')} PAGE_ID=${shellEscape(stressSourceId)} wp eval-file /tmp/set-elementor-data.php --allow-root`
  202 |     );
  203 | 
  204 |     stressConvertedId = runDockerExec(
  205 |       `SOURCE_PAGE_ID=${shellEscape(stressSourceId)} wp eval-file /tmp/convert-to-new-page.php --allow-root`
  206 |     ).trim();
  207 | 
  208 |     if (!stressConvertedId) {
  209 |       throw new Error('convert-to-new-page.php did not return a page ID for stress test');
  210 |     }
  211 |   });
  212 | 
  213 |   test('stress-test page renders without breaking layout', async ({ page }) => {
  214 |     const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
  215 |     const errors: string[] = [];
  216 | 
  217 |     page.on('console', (msg) => {
  218 |       if (msg.type() === 'error' && !isDiviNoise(msg.text())) {
  219 |         errors.push(`Console error: ${msg.text()}`);
  220 |       }
  221 |     });
  222 |     page.on('pageerror', (err) => {
  223 |       if (!isDiviNoise(err.message)) errors.push(`Page error: ${err.message}`);
  224 |     });
  225 | 
  226 |     await page.goto(`${base}/?page_id=${stressConvertedId}`);
  227 | 
  228 |     // Page must have at least 1 rendered Divi section despite unsupported widgets.
  229 |     await page.waitForSelector('.et_pb_section', { timeout: 15000 });
  230 |     expect(await page.locator('.et_pb_section').count()).toBeGreaterThanOrEqual(1);
  231 | 
  232 |     // Content from section 1 (background + multiple heading tags).
  233 |     await page.waitForSelector(
  234 |       'h1:has-text("Stress Test Heading H1"), h2:has-text("Stress Test Heading H1")',
  235 |       { timeout: 10000 }
  236 |     );
  237 | 
  238 |     // Buttons from sections 2 and 4.
  239 |     expect(await page.locator('text=External Link').isVisible()).toBe(true);
  240 |     expect(await page.locator('text=Explore Now').isVisible()).toBe(true);
  241 | 
  242 |     // Image with alt text (section 2).
  243 |     expect(await page.locator('img[alt="Team photo"]').isVisible()).toBe(true);
  244 | 
  245 |     // Heading from nested container (section 5).
  246 |     expect(await page.locator('h2:has-text("Nested Container Heading")').isVisible()).toBe(true);
  247 | 
  248 |     await page.screenshot({
  249 |       path: path.join(screenshotsDir, 'stress-test-frontend.png'),
  250 |       fullPage: true,
  251 |     });
  252 | 
  253 |     expect(errors, 'Frontend JS errors on stress-test page').toEqual([]);
  254 |   });
  255 | 
  256 |   test('conversion report has correct counts and warnings', () => {
  257 |     // Read the stored _edc_conversion_report from WP post meta.
  258 |     const raw = runDockerExec(
  259 |       `wp post meta get ${shellEscape(stressConvertedId)} _edc_conversion_report --allow-root`
  260 |     ).trim();
  261 | 
  262 |     expect(raw, '_edc_conversion_report meta must exist').toBeTruthy();
  263 | 
  264 |     const report = JSON.parse(raw) as {
  265 |       converted: Record<string, number>;
  266 |       warnings: string[];
  267 |       skipped_settings: string[];
  268 |       unsupported: Array<{ widgetType: string }>;
  269 |     };
  270 | 
  271 |     // Converted element counts.
  272 |     expect(report.converted.heading, 'heading count').toBeGreaterThanOrEqual(6);
  273 |     expect(report.converted.text,    'text count').toBeGreaterThanOrEqual(2);
  274 |     expect(report.converted.image,   'image count').toBeGreaterThanOrEqual(2);
  275 |     expect(report.converted.button,  'button count').toBeGreaterThanOrEqual(3);
  276 | 
  277 |     // Unsupported widgets: e-carousel, e-form, e-video.
> 278 |     expect(report.unsupported.length, 'unsupported count').toBeGreaterThanOrEqual(3);
      |                                                            ^ Error: unsupported count
  279 |     const unsupportedTypes = report.unsupported.map((u) => u.widgetType);
  280 |     expect(unsupportedTypes).toContain('e-carousel');
  281 |     expect(unsupportedTypes).toContain('e-form');
  282 |     expect(unsupportedTypes).toContain('e-video');
  283 | 
  284 |     // Warning: empty column (section 3 has only unsupported widgets).
  285 |     const hasEmptyWarning = report.warnings.some((w) => w.includes('Empty column'));
  286 |     expect(hasEmptyWarning, 'warning about empty column').toBe(true);
  287 | 
  288 |     // Warning: image without alt text.
  289 |     const hasAltWarning = report.warnings.some((w) => w.includes('missing alt text'));
  290 |     expect(hasAltWarning, 'warning about missing alt text').toBe(true);
  291 | 
  292 |     // Skipped settings: section 1 has background_color and custom_padding.
  293 |     const hasBackgroundColor = report.skipped_settings.some((s) => s.includes('background_color'));
  294 |     expect(hasBackgroundColor, 'skipped background_color').toBe(true);
  295 | 
  296 |     const hasPadding = report.skipped_settings.some((s) => s.includes('custom_padding'));
  297 |     expect(hasPadding, 'skipped custom_padding').toBe(true);
  298 |   });
  299 | });
  300 | 
```