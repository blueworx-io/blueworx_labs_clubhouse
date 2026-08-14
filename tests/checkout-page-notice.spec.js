const { test, expect } = require('@playwright/test');

// @wordpress only: this is a wp-admin notice, and the DB-free preview has no
// admin.
//
// What is asserted here is the half a real WordPress can prove without
// SureCart: that a club with no shop is never nagged about a shop. That is the
// failure mode with actual cost — most clubhouse sites have no SureCart at all,
// and a warning about a missing checkout page on every admin screen of a site
// that will never sell anything would be pure noise.
//
// The other half — the notice appearing, and its button repairing the page —
// depends on SureCart being installed. Installing it here to assert our own
// notice would be testing SureCart, the same reasoning external-chrome.spec.js
// records; the decision itself is pure and covered branch by branch in
// tests/php/CheckoutPageTest.php.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('a club with no shop is never told its checkout page is missing @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).not.toContainText('checkout page');

  // The Clubhouse screens too — the notice hangs off admin_notices, which every
  // admin screen fires.
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).not.toContainText('checkout page');
});

test('membership tiers keep their fallback while there is no reachable checkout @wordpress', async ({ page }) => {
  // The other side of the same rule: no shop, or a shop whose checkout page is
  // gone, must leave every Join button pointing somewhere real rather than at a
  // half-built checkout URL.
  await page.goto('/?clubhouse_page=membership');
  const links = page.locator('.ch-tier a[href]');
  const count = await links.count();
  expect(count).toBeGreaterThan(0);
  for (let i = 0; i < count; i += 1) {
    const href = await links.nth(i).getAttribute('href');
    expect(href).toBeTruthy();
    expect(href).not.toBe('#');
  }
});
