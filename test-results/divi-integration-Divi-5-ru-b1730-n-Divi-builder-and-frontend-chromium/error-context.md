# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: divi-integration.spec.ts >> Divi 5 runtime validation >> validates heading fixture in Divi builder and frontend
- Location: tests/e2e/divi-integration.spec.ts:94:9

# Error details

```
TimeoutError: page.waitForSelector: Timeout 15000ms exceeded.
Call log:
  - waiting for locator('text=Hello World') to be visible

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - generic [ref=e2]:
    - navigation "Toolbar":
      - list:
        - listitem [ref=e3]:
          - link "About WordPress" [ref=e4] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/about.php
            - generic [ref=e5]: 
            - generic [ref=e6]: About WordPress
        - listitem [ref=e7]:
          - link " Test" [ref=e8] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/
        - listitem [ref=e9]:
          - link "4 updates available" [ref=e10] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/update-core.php
            - generic [ref=e11]: 
            - generic [ref=e12]: "4"
            - generic [ref=e13]: 4 updates available
        - listitem [ref=e14]:
          - link "0 Comments in moderation" [ref=e15] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/edit-comments.php
            - generic [ref=e16]: 
            - generic [ref=e17]: "0"
            - generic [ref=e18]: 0 Comments in moderation
        - listitem [ref=e19]:
          - link "New" [ref=e20] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/post-new.php
            - generic [ref=e21]: 
            - generic [ref=e22]: New
        - listitem [ref=e23]:
          - link " Edit Page" [ref=e24] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/post.php?post=23&action=edit
        - listitem [ref=e25]:
          - link " Edit With Divi" [ref=e26] [cursor=pointer]:
            - /url: http://localhost:8000/?page_id=23&et_fb=1&PageSpeed=off
        - text: 
      - list [ref=e27]:
        - listitem [ref=e28]:
          - generic [ref=e30]:
            - text: 
            - textbox "Search" [ref=e31] [cursor=pointer]
            - generic [ref=e32]: Search
        - listitem [ref=e33]:
          - link "Howdy, admin" [ref=e34] [cursor=pointer]:
            - /url: http://localhost:8000/wp-admin/profile.php
    - link "Log Out" [ref=e35] [cursor=pointer]:
      - /url: http://localhost:8000/wp-login.php?action=logout&_wpnonce=f7f4dc5e85
  - generic [ref=e36]:
    - banner [ref=e37]:
      - generic [ref=e38]:
        - link "Test" [ref=e40] [cursor=pointer]:
          - /url: http://localhost:8000/
          - img "Test" [ref=e41]
        - generic [ref=e42]:
          - navigation [ref=e43]:
            - list [ref=e44]:
              - listitem [ref=e45]:
                - link "Elementor Test Page" [ref=e46] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=4
              - listitem [ref=e47]:
                - link "Elementor Test Page" [ref=e48] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=5
              - listitem [ref=e49]:
                - link "Elementor Test Page" [ref=e50] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=6
              - listitem [ref=e51]:
                - link "Heading Fixture" [ref=e52] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=23
              - listitem [ref=e53]:
                - link "Heading Fixture" [ref=e54] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=21
              - listitem [ref=e55]:
                - link "Heading Fixture" [ref=e56] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=20
              - listitem [ref=e57]:
                - link "Heading Fixture" [ref=e58] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=19
              - listitem [ref=e59]:
                - link "Heading Fixture" [ref=e60] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=18
              - listitem [ref=e61]:
                - link "Heading Fixture" [ref=e62] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=17
              - listitem [ref=e63]:
                - link "Heading Fixture" [ref=e64] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=16
              - listitem [ref=e65]:
                - link "Heading Fixture" [ref=e66] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=15
              - listitem [ref=e67]:
                - link "Heading Fixture" [ref=e68] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=14
              - listitem [ref=e69]:
                - link "Heading Fixture" [ref=e70] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=13
              - listitem [ref=e71]:
                - link "Heading Fixture" [ref=e72] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=12
              - listitem [ref=e73]:
                - link "Heading Fixture" [ref=e74] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=11
              - listitem [ref=e75]:
                - link "Heading Fixture" [ref=e76] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=9
              - listitem [ref=e77]:
                - link "Sample Page" [ref=e78] [cursor=pointer]:
                  - /url: http://localhost:8000/?page_id=2
              - listitem [ref=e79]:
                - link "Uncategorized" [ref=e80] [cursor=pointer]:
                  - /url: http://localhost:8000/?cat=1
          - generic [ref=e82]: Select Page
      - search [ref=e85]:
        - searchbox "Search for:" [ref=e86]
    - generic:
      - generic:
        - article
```

# Test source

```ts
  44  | function prepareFixturePage(fixtureName: string, pageTitle: string): string {
  45  |   const createPage = runDockerExec(
  46  |     `wp post create --post_type=page --post_status=publish --post_title=${shellEscape(pageTitle)} --porcelain --allow-root`
  47  |   ).trim();
  48  | 
  49  |   if (!createPage) {
  50  |     throw new Error(`Failed to create page for fixture ${fixtureName}`);
  51  |   }
  52  | 
  53  |   runDockerExec(
  54  |     `FIXTURE_NAME=${shellEscape(fixtureName)} PAGE_ID=${shellEscape(createPage)} wp eval-file /tmp/set-elementor-data.php --allow-root`
  55  |   );
  56  | 
  57  |   runDockerExec(`PAGE_ID=${shellEscape(createPage)} wp eval-file /tmp/convert-run.php --allow-root`);
  58  | 
  59  |   return createPage;
  60  | }
  61  | 
  62  | const fixtures = [
  63  |   {
  64  |     name: 'heading',
  65  |     title: 'Heading Fixture',
  66  |     frontendSelector: 'text=Hello World',
  67  |     moduleSelector: '.et_pb_module.et_pb_text',
  68  |     expectedModuleCount: 1,
  69  |   },
  70  |   {
  71  |     name: 'image',
  72  |     title: 'Image Fixture',
  73  |     frontendSelector: 'img[alt="Sample image"]',
  74  |     moduleSelector: '.et_pb_module.et_pb_image',
  75  |     expectedModuleCount: 1,
  76  |   },
  77  |   {
  78  |     name: 'button',
  79  |     title: 'Button Fixture',
  80  |     frontendSelector: 'text=Click Here',
  81  |     moduleSelector: '.et_pb_module.et_pb_button',
  82  |     expectedModuleCount: 1,
  83  |   },
  84  | ];
  85  | 
  86  | test.describe.serial('Divi 5 runtime validation', () => {
  87  |   test.beforeAll(() => {
  88  |     fs.mkdirSync(screenshotsDir, { recursive: true });
  89  |     copyHelperScript('set-elementor-data.php');
  90  |     copyHelperScript('convert-run.php');
  91  |   });
  92  | 
  93  |   for (const fixture of fixtures) {
  94  |     test(`validates ${fixture.name} fixture in Divi builder and frontend`, async ({ page }) => {
  95  |       const base = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
  96  |       const errors: string[] = [];
  97  | 
  98  |       page.on('console', (message) => {
  99  |         if (message.type() === 'error') {
  100 |           errors.push(`Console error: ${message.text()}`);
  101 |         }
  102 |       });
  103 | 
  104 |       page.on('pageerror', (error) => {
  105 |         errors.push(`Page error: ${error.message}`);
  106 |       });
  107 | 
  108 |       const pageId = prepareFixturePage(fixture.name, fixture.title);
  109 |       await page.goto(base + WP_ADMIN);
  110 |       await page.fill('input#user_login', 'admin');
  111 |       await page.fill('input#user_pass', 'admin');
  112 |       await page.click('input#wp-submit');
  113 | 
  114 |       await page.goto(base + WP_ADMIN + 'edit.php?post_type=page');
  115 |       await page.locator('a.row-title', { hasText: fixture.title }).first().click();
  116 | 
  117 |       await page.locator('text=This Layout Is Built With Divi').first().waitFor({ timeout: 15000 });
  118 |       const onboardingModal = page.locator('.components-guide__page, .components-modal__screen-overlay');
  119 |       const onboardingText = page.locator('text=Welcome to the block editor');
  120 |       if ((await onboardingModal.count()) > 0 || (await onboardingText.count()) > 0) {
  121 |         await page.keyboard.press('Escape');
  122 |         await page.waitForTimeout(500);
  123 |       }
  124 | 
  125 |       let diviButton = page.locator('button', { hasText: 'Edit With The Divi Builder' }).first();
  126 |       if (await diviButton.count() === 0) {
  127 |         diviButton = page.locator('a', { hasText: 'Edit With The Divi Builder' }).first();
  128 |       }
  129 |       if (await diviButton.count() === 0) {
  130 |         diviButton = page.locator('text=Edit With The Divi Builder').first();
  131 |       }
  132 |       expect(await diviButton.count()).toBeGreaterThan(0);
  133 | 
  134 |       await diviButton.click({ force: true }).catch(() => {
  135 |         // If Divi builder click is blocked by the editor overlay or does not load,
  136 |         // page recognition is still valid because the Divi prompt/button exists.
  137 |       });
  138 | 
  139 |       await page.waitForTimeout(1000);
  140 |       const builderScreenshot = path.join(screenshotsDir, `${fixture.name}-builder.png`);
  141 |       await page.screenshot({ path: builderScreenshot, fullPage: true });
  142 | 
  143 |       await page.goto(`${base}/?page_id=${pageId}`);
> 144 |       await page.waitForSelector(fixture.frontendSelector, { timeout: 15000 });
      |                  ^ TimeoutError: page.waitForSelector: Timeout 15000ms exceeded.
  145 |       expect(await page.locator(fixture.frontendSelector).isVisible()).toBe(true);
  146 | 
  147 |       const frontendScreenshot = path.join(screenshotsDir, `${fixture.name}-frontend.png`);
  148 |       await page.screenshot({ path: frontendScreenshot, fullPage: true });
  149 | 
  150 |       expect(errors).toEqual([]);
  151 |     });
  152 |   }
  153 | });
  154 | 
```