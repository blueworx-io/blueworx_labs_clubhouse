const { test, expect } = require('@playwright/test');

// @wordpress only: this is about rows in the database, which the DB-free
// preview does not have.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('every club page has a real page behind it @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');
  for (const title of ['About', 'Membership', 'Contact', 'News', 'Sports', 'Teams', 'Events', 'Calendar', 'Privacy', 'Terms']) {
    await expect(page.locator('#the-list a.row-title', { hasText: new RegExp(`^${title}$`) })).toHaveCount(1);
  }
});

test('a club page renders from its own page, not the rewrite rule @wordpress', async ({ page }) => {
  await page.goto('/about/');
  await expect(page.locator('.ch-nav')).toHaveCount(1);
  const isPage = await page.evaluate(() => document.body.className.includes('page-id-'));
  expect(isPage).toBe(true);
});

test('the front page is the club home page @wordpress', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.ch-nav')).toHaveCount(1);
});
