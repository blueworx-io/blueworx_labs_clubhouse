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

// The visibility toggles live in a tab panel that is not the one the Setup
// screen opens on, so the tab has to be opened before anything in it can be
// clicked — its inputs are genuinely not on screen until then.
async function setPageVisible(page, slug, visible) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await page.getByRole('tab', { name: 'Visibility' }).click();

  const toggle = page.locator(`input[name="clubhouse_page[${slug}]"]`);
  await expect(toggle).toBeVisible();
  if (visible) {
    await toggle.check();
  } else {
    await toggle.uncheck();
  }
  await page.getByRole('button', { name: /save/i }).first().click({ force: true });
  await expect(page.locator('.notice-success')).toBeVisible();
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
