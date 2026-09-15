const { test, expect } = require('@playwright/test');
const { ADMIN_PASS } = require('./helpers/credentials');

// @wordpress only: wp-admin.
//
// Issue #330. Collections is a heading over six lists, with no screen of its
// own. The sidebar never links to it, but wp-admin's command search lists
// every menu by its slug, and picking "Collections" there opened "Cannot load
// clubhouse-content". The heading's address now lands on the first list.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('the Collections heading address lands on the Sports list @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-content', { waitUntil: 'domcontentloaded' });

  await expect(page).toHaveURL(/edit\.php\?post_type=clubhouse_sport/);
  await expect(page.locator('body')).not.toContainText('Cannot load');
  await expect(page.getByRole('heading', { name: 'Sports' })).toBeVisible();
});
