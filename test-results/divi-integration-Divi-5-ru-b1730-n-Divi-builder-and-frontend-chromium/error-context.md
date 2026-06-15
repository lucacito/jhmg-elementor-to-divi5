# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: divi-integration.spec.ts >> Divi 5 runtime validation >> validates heading fixture in Divi builder and frontend
- Location: tests/e2e/divi-integration.spec.ts:118:9

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
                  - button "Heading Fixture · Page ⌘K" [ref=e38] [cursor=pointer]:
                    - heading "Heading Fixture · Page" [level=1] [ref=e40]:
                      - generic [ref=e41]: Heading Fixture
                      - generic [ref=e42]: · Page
                    - generic [ref=e43]: ⌘K
                  - generic [ref=e44]:
                    - link "View Page" [ref=e45] [cursor=pointer]:
                      - /url: http://localhost:8000/?page_id=343
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
  56  |     `wp post create --post_type=page --post_status=publish --post_title=${shellEscape(pageTitle)} --porcelain --allow-root`
  57  |   ).trim();
  58  | 
  59  |   if (!createPage) {
  60  |     throw new Error(`Failed to create page for fixture ${fixtureName}`);
  61  |   }
  62  | 
  63  |   runDockerExec(
  64  |     `FIXTURE_NAME=${shellEscape(fixtureName)} PAGE_ID=${shellEscape(createPage)} wp eval-file /tmp/set-elementor-data.php --allow-root`
  65  |   );
  66  | 
  67  |   runDockerExec(`PAGE_ID=${shellEscape(createPage)} wp eval-file /tmp/convert-run.php --allow-root`);
  68  | 
  69  |   return createPage;
  70  | }
  71  | 
  72  | const fixtures = [
  73  |   {
  74  |     name: 'heading',
  75  |     title: 'Heading Fixture',
  76  |     frontendSelector: 'h2:has-text("Hello World")',
  77  |     moduleSelector: '.et_pb_module.et_pb_text',
  78  |     expectedModuleCount: 1,
  79  |   },
  80  |   {
  81  |     name: 'text',
  82  |     title: 'Text Fixture',
  83  |     frontendSelector: 'text=This is sample paragraph text.',
  84  |     moduleSelector: '.et_pb_module.et_pb_text',
  85  |     expectedModuleCount: 1,
  86  |   },
  87  |   {
  88  |     name: 'image',
  89  |     title: 'Image Fixture',
  90  |     frontendSelector: 'img[alt="Sample image"]',
  91  |     moduleSelector: '.et_pb_module.et_pb_image',
  92  |     expectedModuleCount: 1,
  93  |   },
  94  |   {
  95  |     name: 'button',
  96  |     title: 'Button Fixture',
  97  |     frontendSelector: 'text=Click Here',
  98  |     moduleSelector: '.et_pb_module.et_pb_button',
  99  |     expectedModuleCount: 1,
  100 |   },
  101 |   {
  102 |     name: 'nested-container',
  103 |     title: 'Nested Container Fixture',
  104 |     frontendSelector: 'h2:has-text("Nested Heading")',
  105 |     moduleSelector: '.et_pb_module.et_pb_text',
  106 |     expectedModuleCount: 2,
  107 |   },
  108 | ];
  109 | 
  110 | test.describe.serial('Divi 5 runtime validation', () => {
  111 |   test.beforeAll(() => {
  112 |     fs.mkdirSync(screenshotsDir, { recursive: true });
  113 |     copyHelperScript('set-elementor-data.php');
  114 |     copyHelperScript('convert-run.php');
  115 |   });
  116 | 
  117 |   for (const fixture of fixtures) {
  118 |     test(`validates ${fixture.name} fixture in Divi builder and frontend`, async ({ page }) => {
  119 |       const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
  120 |       const adminErrors: string[] = [];
  121 | 
  122 |       page.on('console', (message) => {
  123 |         if (message.type() === 'error' && !isDiviNoise(message.text())) {
  124 |           adminErrors.push(`Console error: ${message.text()}`);
  125 |         }
  126 |       });
  127 | 
  128 |       page.on('pageerror', (error) => {
  129 |         if (!isDiviNoise(error.message)) {
  130 |           adminErrors.push(`Page error: ${error.message}`);
  131 |         }
  132 |       });
  133 | 
  134 |       const pageId = prepareFixturePage(fixture.name, fixture.title);
  135 |       await page.goto(base + WP_ADMIN);
  136 |       await page.fill('input#user_login', 'admin');
  137 |       await page.fill('input#user_pass', 'admin');
  138 |       await page.click('input#wp-submit');
  139 | 
  140 |       // Navigate directly to the edit screen by ID to avoid pagination issues.
  141 |       await page.goto(`${base}${WP_ADMIN}post.php?post=${pageId}&action=edit`);
  142 | 
  143 |       // Dismiss any Gutenberg onboarding modals.
  144 |       const onboardingModal = page.locator('.components-guide__page, .components-modal__screen-overlay');
  145 |       const onboardingText = page.locator('text=Welcome to the block editor');
  146 |       if ((await onboardingModal.count()) > 0 || (await onboardingText.count()) > 0) {
  147 |         await page.keyboard.press('Escape');
  148 |         await page.waitForTimeout(500);
  149 |       }
  150 | 
  151 |       // Divi shows either "Use The Divi Builder" (fresh page) or "Edit With The Divi Builder"
  152 |       // (previously built page). Both confirm Divi recognizes the page.
  153 |       const diviButtonLocator = page.locator(
  154 |         'button:has-text("Divi Builder"), a:has-text("Divi Builder"), [class*="divi-builder-button"]'
  155 |       );
> 156 |       await diviButtonLocator.first().waitFor({ timeout: 15000 });
      |                                       ^ TimeoutError: locator.waitFor: Timeout 15000ms exceeded.
  157 |       expect(await diviButtonLocator.count()).toBeGreaterThan(0);
  158 |       const diviButton = diviButtonLocator.first();
  159 | 
  160 |       await diviButton.click({ force: true }).catch(() => {
  161 |         // Builder click blocked by overlay — page recognition is still valid.
  162 |       });
  163 | 
  164 |       await page.waitForTimeout(1000);
  165 |       const builderScreenshot = path.join(screenshotsDir, `${fixture.name}-builder.png`);
  166 |       await page.screenshot({ path: builderScreenshot, fullPage: true });
  167 | 
  168 |       // Reset error tracking before frontend navigation — only frontend errors matter.
  169 |       adminErrors.length = 0;
  170 |       const frontendErrors: string[] = [];
  171 | 
  172 |       page.on('console', (message) => {
  173 |         if (message.type() === 'error' && !isDiviNoise(message.text())) {
  174 |           frontendErrors.push(`Console error: ${message.text()}`);
  175 |         }
  176 |       });
  177 | 
  178 |       page.on('pageerror', (error) => {
  179 |         if (!isDiviNoise(error.message)) {
  180 |           frontendErrors.push(`Page error: ${error.message}`);
  181 |         }
  182 |       });
  183 | 
  184 |       await page.goto(`${base}/?page_id=${pageId}`);
  185 |       await page.waitForSelector(fixture.frontendSelector, { timeout: 15000 });
  186 |       expect(await page.locator(fixture.frontendSelector).isVisible()).toBe(true);
  187 | 
  188 |       // Verify Divi CSS assets are present. In Docker the et-cache dir is root-owned so
  189 |       // CSS is emitted as inline <style id="et-..."> blocks rather than external files.
  190 |       const diviStylePresent = await page.evaluate(() => {
  191 |         const styles = Array.from(document.querySelectorAll('style[id]'));
  192 |         return styles.some((el) => el.id.startsWith('et-'));
  193 |       });
  194 |       expect(diviStylePresent, `Divi CSS must be enqueued on ${fixture.name} frontend`).toBe(true);
  195 | 
  196 |       // Verify Divi section module has non-zero padding — confirms styles are actually applied.
  197 |       const sectionPadding = await page.locator('.et_pb_section').first().evaluate(
  198 |         (el) => getComputedStyle(el).paddingTop
  199 |       );
  200 |       expect(sectionPadding, `Divi section padding must be non-zero on ${fixture.name} frontend`).not.toBe('0px');
  201 | 
  202 |       const frontendScreenshot = path.join(screenshotsDir, `${fixture.name}-frontend.png`);
  203 |       await page.screenshot({ path: frontendScreenshot, fullPage: true });
  204 | 
  205 |       expect(frontendErrors, `Frontend errors on ${fixture.name}`).toEqual([]);
  206 |     });
  207 |   }
  208 | });
  209 | 
```