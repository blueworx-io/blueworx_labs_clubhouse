// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const { previewPort } = require('./bin/dev-ports');

// There are two local harnesses, and each spec belongs to exactly one of them.
// Rather than run the suite twice, the two projects below carry their own
// baseURL, so a single `playwright test` sends every spec to the harness that
// can actually serve it.
//
//   preview     the DB-free PHP preview (preview/index.php). Fast, no database.
//               Owns the @preview specs — they drive the preview's injected
//               .ch-switcher and its ?look= / ?demo=1 params, none of which
//               exist in WordPress (there the look is a persisted setting).
//   wordpress   everything else. Runs against real WordPress when
//               PLAYWRIGHT_BASE_URL points at the harness that
//               bin/wp-test.mjs provisions, and falls back to the preview
//               otherwise, so `npm test` alone still covers the whole suite.
//
// Portable specs navigate with `?clubhouse_page=<slug>` — WordPress's real query
// var (Frontend::QUERY_VAR), which preview/index.php also accepts. One URL form,
// both harnesses.
//
// Ports come from the plugin slug (bin/dev-ports.js) so two plugin repos in
// flight at the same time never contend for the same port.
const PORT = previewPort();
const previewURL = `http://127.0.0.1:${PORT}/preview/`;

// Set only when pointing at something this config did not boot — currently the
// local WordPress harness.
const externalBaseURL = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || '';

if (!externalBaseURL) {
  console.log('No WordPress URL set — skipping @wordpress specs. Run "npm run test:wp" for those.');
}

module.exports = defineConfig({
  testDir: './tests',
  // Seeds the site-wide state the specs cannot set themselves (demo mode).
  // No-ops when the run targets the preview.
  globalSetup: require.resolve('./tests/global-setup.js'),
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'list',
  // PHP's built-in server is single-threaded and PHP_CLI_SERVER_WORKERS is not
  // supported on Windows, so parallel workers against real WordPress produce
  // spurious timeouts. The preview alone is cheap enough to stay parallel.
  workers: externalBaseURL ? 1 : undefined,
  use: {
    trace: 'on-first-retry',
  },
  // Always booted: the preview project needs it even when the wordpress project
  // is pointed elsewhere.
  webServer: {
    command: `php -S 127.0.0.1:${PORT}`,
    url: previewURL,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
  projects: [
    {
      name: 'preview',
      grep: /@preview/,
      use: { ...devices['Desktop Chrome'], baseURL: previewURL },
    },
    {
      name: 'wordpress',
      // @preview specs belong to the preview harness. @wordpress specs need real
      // WordPress — against the preview they would pass while testing nothing, so
      // they are dropped when no WordPress URL is set, and the drop is announced.
      grepInvert: externalBaseURL ? /@preview/ : /@preview|@wordpress/,
      use: { ...devices['Desktop Chrome'], baseURL: externalBaseURL || previewURL },
    },
  ],
});
