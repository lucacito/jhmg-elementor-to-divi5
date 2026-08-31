import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  // Every spec drives the same WordPress container: they log in as the same
  // user, create fixture posts, and read them back. Playwright otherwise
  // defaults to roughly half the CPU cores, and the resulting concurrent logins
  // and post creation race each other — which shows up as an unrelated-looking
  // selector timeout on whichever spec loses. Not parallel-safe by nature.
  workers: 1,
  timeout: 30 * 1000,
  expect: {
    timeout: 5000,
  },
  use: {
    actionTimeout: 0,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
