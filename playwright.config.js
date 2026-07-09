// @ts-check
const { defineConfig, devices } = require('@playwright/test');

// Playwright runs against a deployed staging/preview URL rather than spinning up
// WordPress in CI. The CI workflow sets PLAYWRIGHT_BASE_URL (and BASE_URL) from
// the caller workflow's `preview_url` input.
//
// TODO: replace the placeholder below (and `preview_url` in .github/workflows/ci.yml)
// once a real staging/preview URL exists.
const baseURL =
  process.env.PLAYWRIGHT_BASE_URL ||
  process.env.BASE_URL ||
  'https://staging.example.com';

module.exports = defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'list',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
