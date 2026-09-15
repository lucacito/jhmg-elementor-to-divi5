import { defineConfig, devices } from '@playwright/test';

// Demo site checks, run by demo/verify.sh from the repository root. Separate from the
// root playwright.config.ts so `npm run test:browser` is unaffected.
export default defineConfig({
  testDir: './tests',
  // Screenshots only run when the `converted` check sets PW_STAGE.
  testIgnore: process.env.PW_STAGE ? [] : ['**/screenshots.spec.ts'],
  outputDir: './output/test-results',
  workers: 1,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  reporter: 'list',
  use: {
    baseURL: 'http://localhost:8040',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
  ],
});
