const { test, expect } = require('@playwright/test');
const { ADMIN_PASS } = require('./helpers/credentials');

// @wordpress only: wp-admin.
//
// The plugin updates itself from GitHub releases. The update checker it
// carries adds a "Check for updates" link to the plugin's row on the Plugins
// screen — if that link is there, the checker is loaded and watching the repo.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('the Plugins screen offers to check Clubhouse for updates @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/plugins.php', { waitUntil: 'domcontentloaded' });

  const row = page.locator('tr[data-slug="blueworx-labs-clubhouse"], tr[data-plugin="blueworx-labs-clubhouse/blueworx-labs-clubhouse.php"]').first();
  await expect(row).toBeVisible();
  await expect(row.getByRole('link', { name: 'Check for updates' })).toBeVisible();
});
