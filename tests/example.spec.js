const { test, expect } = require('@playwright/test');

// Placeholder smoke test. Skipped until a real staging/preview URL is wired up
// (set it in playwright.config.js and the `preview_url` input in
// .github/workflows/ci.yml). Then remove `.skip` and assert against the real page.
test.skip('home page loads', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/.+/);
});
