const { test, expect } = require('@playwright/test');
const { ADMIN_PASS } = require('./helpers/credentials');

// @wordpress only: admin screens, which the DB-free preview does not have.
//
// ClubHouse's guides moved to the Guides page of the WordPress Enhancements
// plugin, which the harness does not carry — so what can be pinned here is the
// other half of the move: the User guide screen of ClubHouse's own is gone,
// and its address no longer answers. The guides themselves are pinned by
// tests/php/GuidesTest.php.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('the user guide screen is no longer part of clubhouse @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  const clubhouse = page.locator('#adminmenu li.menu-top', { hasText: 'Clubhouse' }).first();
  await expect(clubhouse.locator('.wp-submenu li a', { hasText: 'User guide' })).toHaveCount(0);

  // The old address is refused rather than drawing a screen nothing links to.
  await page.goto('/wp-admin/admin.php?page=clubhouse-guide', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'How ClubHouse works' })).toHaveCount(0);
  await expect(page.locator('body')).toContainText(/not allowed|permission|not found/i);
});
