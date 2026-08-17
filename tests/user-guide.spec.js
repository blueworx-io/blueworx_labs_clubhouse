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

// A page's on/off switch lives on Content → Pages, beside the blocks that page
// is built from — one page at a time, chosen from the list down the side.
async function setPageVisible(page, slug, visible) {
  await page.goto(`/wp-admin/admin.php?page=clubhouse-pages&club_page=${slug}`, {
    waitUntil: 'domcontentloaded',
  });

  const toggle = page.locator('input[name="clubhouse_page_enabled"]');
  await expect(toggle).toBeVisible();
  if ((await toggle.isChecked()) !== visible) {
    await toggle.click({ force: true });
  }
  expect(await toggle.isChecked()).toBe(visible);

  // force, for the reason menu-editor.spec.js documents: wp-admin's own chrome
  // keeps reflowing after this screen loads, so Playwright's 'stable' wait never
  // converges even though the control is provably where it says it is.
  await page
    .locator('form:has(input[name="clubhouse_pages_switch"]) button[name="clubhouse_pages_submit"]')
    .click({ force: true });
  await expect(page.locator('.notice, .clubhouse-notice').first()).toBeVisible();
}

test('switching a page off changes what the guide says about it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  await setPageVisible(page, 'contact', false);

  await page.goto('/wp-admin/admin.php?page=clubhouse-guide', { waitUntil: 'domcontentloaded' });
  const entry = page.locator('#guide-pages details', { hasText: 'Contact' }).first();
  await expect(entry).toContainText('Switched off');

  // Put it back so the rest of the suite sees the site it expects.
  await setPageVisible(page, 'contact', true);
});

test('the guide opens every chapter so find-in-page can reach it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-guide', { waitUntil: 'domcontentloaded' });

  const closed = await page.locator('details.clubhouse-guide-entry:not([open])').count();
  expect(closed).toBe(0);
});
