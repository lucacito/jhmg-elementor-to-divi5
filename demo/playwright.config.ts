import { defineConfig, devices } from '@playwright/test';

// Demo site checks, run by demo/verify.sh from the repository root. Separate from the
// root playwright.config.ts so `npm run test:browser` is unaffected.
export default defineConfig({
  testDir: './tests',
  // Screenshots run only under PW_STAGE=originals|converted; render assertions only under PW_STAGE=render.
  testIgnore: [
    ...(process.env.PW_STAGE === 'originals' || process.env.PW_STAGE === 'converted' ? [] : ['**/screenshots.spec.ts']),
    ...(process.env.PW_STAGE === 'render' ? [] : ['**/render.spec.ts']),
  ],
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
