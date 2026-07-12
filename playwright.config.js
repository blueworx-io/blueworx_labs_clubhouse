// @ts-check
const { defineConfig, devices } = require('@playwright/test');

// Playwright runs against the plugin's DB-free PHP preview (preview/index.php) —
// the same Page_Renderer output WordPress `template_include` will later echo. The
// webServer below boots `php -S` (docroot = plugin root) so no deployed staging
// site is needed; in CI it always starts fresh, locally it reuses a running one.
//
// The foundation CI passes PLAYWRIGHT_BASE_URL from its `preview_url` input, which
// is set to the preview URL below so the two agree.
const PORT = 8124;
const previewURL = `http://127.0.0.1:${PORT}/preview/`;
const baseURL =
  process.env.PLAYWRIGHT_BASE_URL ||
  process.env.BASE_URL ||
  previewURL;

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
  webServer: {
    command: `php -S 127.0.0.1:${PORT}`,
    url: previewURL,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
