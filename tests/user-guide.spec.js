const { test, expect } = require('@playwright/test');

// @wordpress only: the guide is an admin screen, which the DB-free preview does
// not have.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('the user guide describes this site, not a generic one @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-guide', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: 'How ClubHouse works' })).toBeVisible();

  // Chapters derived from the live registries, not from prose.
  await expect(page.locator('#guide-pages')).toBeVisible();
  await expect(page.locator('#guide-collections')).toBeVisible();
  await expect(page.locator('#guide-look')).toBeVisible();

  // Every page the site actually serves is named.
  for (const label of ['Home', 'About', 'Membership', 'Contact']) {
    await expect(page.locator('#guide-pages'), label).toContainText(label);
  }
});

test('switching a page off changes what the guide says about it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  const toggle = page.locator('input[name="clubhouse_page[contact]"]');
  await toggle.uncheck();
  await page.getByRole('button', { name: /save/i }).first().click({ force: true });

  await page.goto('/wp-admin/admin.php?page=clubhouse-guide', { waitUntil: 'domcontentloaded' });
  const entry = page.locator('#guide-pages details', { hasText: 'Contact' }).first();
  await expect(entry).toContainText('Switched off');

  // Put it back so the rest of the suite sees the site it expects.
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="clubhouse_page[contact]"]').check();
  await page.getByRole('button', { name: /save/i }).first().click({ force: true });
});

test('the guide opens every chapter so find-in-page can reach it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-guide', { waitUntil: 'domcontentloaded' });

  const closed = await page.locator('details.clubhouse-guide-entry:not([open])').count();
  expect(closed).toBe(0);
});
