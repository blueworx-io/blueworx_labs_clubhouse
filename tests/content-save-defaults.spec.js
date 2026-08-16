const { test, expect } = require('@playwright/test');

// @wordpress only: this is a wp-admin form and what one save does to the site.
//
// The bug this pins: the cookie notice and the announcement bar are on until a
// club switches them off, but the editing screen did not know that and drew
// both switches as off on a site that had never touched them. Saving the tab
// writes every field at the value shown, so a single visit to Club Pages —
// changing nothing relevant — turned both off on the live site.
//
// It has to be driven through the real form. The bug was never in the rendering
// or the storage on their own; it was the round trip between them.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

const SWITCHES = [
  'input[name="field[global][cookies][show]"]',
  'input[name="field[global][header][banner_show]"]',
];

test('the sitewide switches show the state the site is actually in @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-site-content&tab=global', {
    waitUntil: 'domcontentloaded',
  });

  for (const selector of SWITCHES) {
    await expect(page.locator(selector), `${selector} drew as off`).toBeChecked();
  }
});

test('saving Club Pages leaves the cookie notice and banner alone @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  // What the site does before anyone opens the editor.
  await page.goto('/?clubhouse_page=privacy');
  await expect(page.locator('#ch-cookie')).toBeVisible();
  const bannerBefore = await page.locator('.ch-banner').count();

  await page.goto('/wp-admin/admin.php?page=clubhouse-site-content&tab=global', {
    waitUntil: 'domcontentloaded',
  });
  // Save without changing anything — the whole point is that this is a no-op.
  await page
    .locator('form:has(input[name="field[global][cookies][show]"]) button[name="clubhouse_content_submit"]')
    .click({ force: true });
  await expect(page.locator('.notice, .clubhouse-notice').first()).toBeVisible();

  await page.goto('/?clubhouse_page=privacy');
  await expect(page.locator('#ch-cookie'), 'a save switched the cookie notice off').toBeVisible();
  expect(await page.locator('.ch-banner').count(), 'a save switched the announcement bar off').toBe(
    bannerBefore,
  );
});
