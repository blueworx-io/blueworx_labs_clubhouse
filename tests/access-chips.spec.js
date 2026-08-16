const { test, expect } = require('@playwright/test');

// @wordpress only: these are admin screens, which the DB-free preview does not
// have.
//
// The chips in a ClubHouse screen's top bar tell an administrator which roles
// can reach the page they are looking at. Three screens asked their controller
// for them and two never did, so Search & sharing and the User guide claimed
// nothing about who could open them.

const SCREENS = [
  { slug: 'clubhouse-setup', name: 'Clubhouse Setup' },
  { slug: 'clubhouse-site-content', name: 'Clubhouse Content' },
  { slug: 'clubhouse-import', name: 'Import' },
  { slug: 'clubhouse-seo', name: 'Search & sharing' },
  { slug: 'clubhouse-guide', name: 'User guide' },
];

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('every clubhouse screen tells an administrator who can reach it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  for (const screen of SCREENS) {
    await page.goto(`/wp-admin/admin.php?page=${screen.slug}`, { waitUntil: 'domcontentloaded' });

    const chips = page.locator('.clubhouse-head .clubhouse-roletags');
    await expect(chips, `${screen.name} shows no access chips`).toHaveCount(1);
    // Administrator can reach all five, so it is the one label common to every
    // screen — the rest differ by page and are not worth pinning here.
    await expect(chips, screen.name).toContainText('Administrator');
  }
});
