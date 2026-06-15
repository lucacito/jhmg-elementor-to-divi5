# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: conversion-workflow.spec.ts >> Conversion workflow: Elementor → Divi 5 >> converted page is recognized by Divi builder
- Location: tests/e2e/conversion-workflow.spec.ts:90:7

# Error details

```
TimeoutError: locator.waitFor: Timeout 15000ms exceeded.
Call log:
  - waiting for locator('button:has-text("Divi Builder"), a:has-text("Divi Builder"), [class*="divi-builder-button"]').first() to be visible
    33 × locator resolved to hidden <button type="button" data-editor="divi" class="is-button is-default is-large components-button editor-post-switch-to-divi">Use The Divi Builder</button>

```

# Page snapshot

```yaml
- generic [ref=e2]:
  - text:            
  - generic:
    - text: 
    - main:
      - generic:
        - generic:
          - heading "Edit Page" [level=1] [ref=e3]
          - generic [ref=e5]:
            - generic [ref=e6]:
              - region "Editor top bar" [ref=e7]:
                - generic [ref=e8]:
                  - generic [ref=e12]:
                    - link "View Pages" [ref=e13] [cursor=pointer]:
                      - /url: edit.php?post_type=page
                      - img [ref=e17]
                    - generic:
                      - img
                  - generic [ref=e19]:
                    - toolbar "Document tools" [ref=e20]:
                      - generic [ref=e21]:
                        - button "Block Inserter" [ref=e22] [cursor=pointer]:
                          - img [ref=e23]
                        - button "Undo" [disabled] [ref=e25]:
                          - img [ref=e26]
                        - button "Redo" [disabled] [ref=e28]:
                          - img [ref=e29]
                        - button "Document Overview" [ref=e31] [cursor=pointer]:
                          - img [ref=e32]
                    - generic [ref=e34]:
                      - text: 
                      - button "Return To Default Editor" [ref=e35] [cursor=pointer]
                  - 'button "Converted: Hero Page (Elementor) · Page ⌘K" [ref=e38] [cursor=pointer]':
                    - 'heading "Converted: Hero Page (Elementor) · Page" [level=1] [ref=e40]':
                      - generic [ref=e41]: "Converted: Hero Page (Elementor)"
                      - generic [ref=e42]: · Page
                    - generic [ref=e43]: ⌘K
                  - generic [ref=e44]:
                    - link "View Page" [ref=e45] [cursor=pointer]:
                      - /url: http://localhost:8000/?page_id=344
                      - img [ref=e46]
                    - button "View" [ref=e49] [cursor=pointer]:
                      - img [ref=e50]
                    - generic [ref=e52]:
                      - button "Settings" [ref=e53] [cursor=pointer]:
                        - img [ref=e54]
                      - button "Divi Settings" [ref=e56] [cursor=pointer]:
                        - img [ref=e57]: 
                    - button "Save" [ref=e60] [cursor=pointer]
                    - button "Options" [ref=e62] [cursor=pointer]:
                      - img [ref=e63]
              - generic [ref=e65]:
                - region "Editor content" [ref=e66]:
                  - generic [ref=e70]:
                    - textbox "Add title" [ref=e72]
                    - 'document "Block: Divi Builder" [ref=e74]':
                      - generic [ref=e75]:
                        - generic [ref=e76]: 
                        - heading "This Layout Is Built With Divi" [level=3] [ref=e77]
                        - button "Edit With The Divi Builder" [ref=e79] [cursor=pointer]
                    - iframe [ref=e80]:
                      
                  - region "Meta Boxes" [ref=e81]:
                    - generic [ref=e82]:
                      - button "Meta Boxes" [ref=e83] [cursor=pointer]:
                        - text: Meta Boxes
                        - img [ref=e84]
                      - separator "Drag to resize" [ref=e86]
                      - generic [ref=e87]: Use up and down arrow keys to resize the meta box pane.
                - region "Editor settings"
                - region "Editor publish":
                  - button "Open save panel" [disabled] [ref=e89]
            - region "Editor footer" [ref=e90]
  - generic [ref=e91]: M
  - generic [ref=e92]: M
```

# Test source

```ts
  23  | 
  24  | function getContainerId(): string {
  25  |   const result = execFileSync('docker-compose', ['-f', composeFile, 'ps', '-q', 'wordpress'], {
  26  |     cwd: rootDir,
  27  |     encoding: 'utf8',
  28  |   }).toString().trim();
  29  | 
  30  |   if (!result) {
  31  |     throw new Error('Unable to find running WordPress container via docker-compose.');
  32  |   }
  33  | 
  34  |   return result;
  35  | }
  36  | 
  37  | function copyHelperScript(scriptName: string): void {
  38  |   const containerId = getContainerId();
  39  |   execFileSync('docker', ['cp', path.join(rootDir, 'scripts', 'docker', scriptName), `${containerId}:/tmp/${scriptName}`], {
  40  |     cwd: rootDir,
  41  |     stdio: 'inherit',
  42  |   });
  43  | }
  44  | 
  45  | function runDockerExec(command: string): string {
  46  |   const containerId = getContainerId();
  47  |   return execFileSync('docker', ['exec', '-i', containerId, 'bash', '-lc', command], {
  48  |     cwd: rootDir,
  49  |     encoding: 'utf8',
  50  |   });
  51  | }
  52  | 
  53  | test.describe.serial('Conversion workflow: Elementor → Divi 5', () => {
  54  |   let sourcePageId: string;
  55  |   let convertedPageId: string;
  56  | 
  57  |   test.beforeAll(() => {
  58  |     fs.mkdirSync(screenshotsDir, { recursive: true });
  59  | 
  60  |     copyHelperScript('set-elementor-data.php');
  61  |     copyHelperScript('convert-to-new-page.php');
  62  | 
  63  |     // The fixtures/ directory is volume-mounted read-only into the container
  64  |     // at ABSPATH/fixtures/, so hero-page.json is already accessible without docker cp.
  65  | 
  66  |     // Create the source Elementor page.
  67  |     sourcePageId = runDockerExec(
  68  |       `wp post create --post_type=page --post_status=publish --post_title=${shellEscape('Hero Page (Elementor)')} --porcelain --allow-root`
  69  |     ).trim();
  70  | 
  71  |     if (!sourcePageId) {
  72  |       throw new Error('Failed to create source Elementor page');
  73  |     }
  74  | 
  75  |     // Attach the hero-page Elementor fixture to it.
  76  |     runDockerExec(
  77  |       `FIXTURE_NAME=${shellEscape('hero-page')} PAGE_ID=${shellEscape(sourcePageId)} wp eval-file /tmp/set-elementor-data.php --allow-root`
  78  |     );
  79  | 
  80  |     // Run the conversion — produces a new Divi 5 page.
  81  |     convertedPageId = runDockerExec(
  82  |       `SOURCE_PAGE_ID=${shellEscape(sourcePageId)} wp eval-file /tmp/convert-to-new-page.php --allow-root`
  83  |     ).trim();
  84  | 
  85  |     if (!convertedPageId) {
  86  |       throw new Error('convert-to-new-page.php did not return a page ID');
  87  |     }
  88  |   });
  89  | 
  90  |   test('converted page is recognized by Divi builder', async ({ page }) => {
  91  |     const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
  92  |     const errors: string[] = [];
  93  | 
  94  |     page.on('console', (msg) => {
  95  |       if (msg.type() === 'error' && !isDiviNoise(msg.text())) {
  96  |         errors.push(`Console error: ${msg.text()}`);
  97  |       }
  98  |     });
  99  |     page.on('pageerror', (err) => {
  100 |       if (!isDiviNoise(err.message)) errors.push(`Page error: ${err.message}`);
  101 |     });
  102 | 
  103 |     // Log in.
  104 |     await page.goto(base + WP_ADMIN);
  105 |     await page.fill('input#user_login', 'admin');
  106 |     await page.fill('input#user_pass', 'admin');
  107 |     await page.click('input#wp-submit');
  108 | 
  109 |     // Navigate directly to the converted page's edit screen.
  110 |     await page.goto(`${base}${WP_ADMIN}post.php?post=${convertedPageId}&action=edit`);
  111 | 
  112 |     // Dismiss any Gutenberg onboarding modal.
  113 |     const onboardingModal = page.locator('.components-guide__page, .components-modal__screen-overlay');
  114 |     if ((await onboardingModal.count()) > 0) {
  115 |       await page.keyboard.press('Escape');
  116 |       await page.waitForTimeout(500);
  117 |     }
  118 | 
  119 |     // Divi shows "Use The Divi Builder" (fresh page) or "Edit With The Divi Builder" (built page).
  120 |     const diviButton = page.locator(
  121 |       'button:has-text("Divi Builder"), a:has-text("Divi Builder"), [class*="divi-builder-button"]'
  122 |     );
> 123 |     await diviButton.first().waitFor({ timeout: 15000 });
      |                              ^ TimeoutError: locator.waitFor: Timeout 15000ms exceeded.
  124 |     expect(await diviButton.count()).toBeGreaterThan(0);
  125 | 
  126 |     await page.screenshot({
  127 |       path: path.join(screenshotsDir, 'conversion-workflow-builder.png'),
  128 |       fullPage: true,
  129 |     });
  130 |   });
  131 | 
  132 |   test('converted page renders on the frontend with all content', async ({ page }) => {
  133 |     const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
  134 |     const errors: string[] = [];
  135 | 
  136 |     page.on('console', (msg) => {
  137 |       if (msg.type() === 'error' && !isDiviNoise(msg.text())) {
  138 |         errors.push(`Console error: ${msg.text()}`);
  139 |       }
  140 |     });
  141 |     page.on('pageerror', (err) => {
  142 |       if (!isDiviNoise(err.message)) errors.push(`Page error: ${err.message}`);
  143 |     });
  144 | 
  145 |     await page.goto(`${base}/?page_id=${convertedPageId}`);
  146 | 
  147 |     // Header / navigation menu.
  148 |     await page.waitForSelector('nav.et-menu-nav, #main-nav, #top-header', { timeout: 10000 });
  149 |     expect(await page.locator('nav.et-menu-nav, #main-nav, #top-header').count()).toBeGreaterThan(0);
  150 | 
  151 |     // At least one Divi section must be rendered.
  152 |     await page.waitForSelector('.et_pb_section', { timeout: 15000 });
  153 |     const sectionCount = await page.locator('.et_pb_section').count();
  154 |     expect(sectionCount, 'Expected at least 3 Divi sections').toBeGreaterThanOrEqual(3);
  155 | 
  156 |     // Hero heading.
  157 |     await page.waitForSelector('h1:has-text("Welcome to Our Site"), h2:has-text("Welcome to Our Site")', { timeout: 10000 });
  158 |     expect(
  159 |       await page.locator('h1:has-text("Welcome to Our Site"), h2:has-text("Welcome to Our Site")').isVisible()
  160 |     ).toBe(true);
  161 | 
  162 |     // Hero CTA button.
  163 |     expect(await page.locator('text=Get Started').isVisible()).toBe(true);
  164 | 
  165 |     // About section image.
  166 |     await page.waitForSelector('img[alt="About our team"]', { timeout: 10000 });
  167 |     expect(await page.locator('img[alt="About our team"]').isVisible()).toBe(true);
  168 | 
  169 |     // CTA section heading and button.
  170 |     expect(await page.locator('h2:has-text("Ready to Begin?")').isVisible()).toBe(true);
  171 |     expect(await page.locator('text=Contact Us').isVisible()).toBe(true);
  172 | 
  173 |     await page.screenshot({
  174 |       path: path.join(screenshotsDir, 'conversion-workflow-frontend.png'),
  175 |       fullPage: true,
  176 |     });
  177 | 
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
```