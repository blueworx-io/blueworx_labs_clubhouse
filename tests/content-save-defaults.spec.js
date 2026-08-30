const { test, expect } = require('@playwright/test');

// @wordpress only: this is a wp-admin screen and what one save does to the site.
//
// The bug this pins: the cookie notice and the announcement bar are on until a
// club switches them off, but the editing screen did not know that and drew
// both switches as off on a site that had never touched them. Saving writes
// every field at the value shown, so a single visit to the editor — changing
// nothing relevant — turned both off on the live site.
//
// It has to be driven through the real screen. The bug was never in the
// rendering or the storage on their own; it was the round trip between them.
// The screen has moved from Club Pages to Clubhouse → Global content, which is
// exactly the move that could have reintroduced it.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function openGlobalContent(page) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-global-content', {
    waitUntil: 'domcontentloaded',
  });
  // The editor is a JS app; nothing below exists until it has mounted.
  // 30 seconds, not the default five: this is a JS app mounting on a server
  // that answers one request at a time (see docs/testing.md).
  await expect(page.locator('.bw-savebar')).toBeVisible({ timeout: 30_000 });
}

const SWITCHES = ['Show the cookie notice', 'Show announcement bar'];

test('the sitewide switches show the state the site is actually in @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await openGlobalContent(page);

  for (const label of SWITCHES) {
    await expect(page.getByLabel(label), `"${label}" drew as off`).toBeChecked();
  }
});

test('saving Global content leaves the cookie notice and banner alone @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  // What the site does before anyone opens the editor.
  await page.goto('/?clubhouse_page=privacy');
  await expect(page.locator('#ch-cookie')).toBeVisible();
  const bannerBefore = await page.locator('.ch-banner').count();

  await openGlobalContent(page);
  // Change something unrelated, so there is a real save to make — the switches
  // themselves are left exactly as the screen drew them, which is the point.
  const heading = page.getByLabel('Heading').first();
  await heading.fill('Welcome to the club');
  await page.getByRole('button', { name: /save/i }).click();
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });

  await page.goto('/?clubhouse_page=privacy');
  await expect(page.locator('#ch-cookie'), 'a save switched the cookie notice off').toBeVisible();
  expect(await page.locator('.ch-banner').count(), 'a save switched the announcement bar off').toBe(
    bannerBefore,
  );
});
