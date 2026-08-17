const { test, expect } = require('@playwright/test');

// @wordpress only: this is a wp-admin form and what one save does to the site.
//
// The bug this pins: the cookie notice and the announcement bar are on until a
// club switches them off, but the editing screen did not know that and drew
// both switches as off on a site that had never touched them. Saving writes
// every field at the value shown, so a single visit to the editor — changing
// nothing relevant — turned both off on the live site.
//
// It has to be driven through the real form. The bug was never in the rendering
// or the storage on their own; it was the round trip between them.
//
// The two switches now live on the blocks that draw them — the announcement bar
// on the header, the cookie notice on the footer — so this is two blocks rather
// than one tab. That is the same round trip and the same trap.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

const SWITCHES = [
  { block: 'footer', selector: 'input[name="field[cookie_show]"]' },
  { block: 'header', selector: 'input[name="field[banner_show]"]' },
];

const blockUrl = (block) => `/wp-admin/admin.php?page=clubhouse-blocks&block=${block}`;

test('the sitewide switches show the state the site is actually in @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  for (const { block, selector } of SWITCHES) {
    await page.goto(blockUrl(block), { waitUntil: 'domcontentloaded' });
    await expect(page.locator(selector), `${selector} drew as off`).toBeChecked();
  }
});

test('saving a block leaves the cookie notice and banner alone @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  // What the site does before anyone opens the editor.
  await page.goto('/?clubhouse_page=privacy');
  await expect(page.locator('#ch-cookie')).toBeVisible();
  const bannerBefore = await page.locator('.ch-banner').count();

  // Save both, changing nothing — the whole point is that this is a no-op.
  for (const { block } of SWITCHES) {
    await page.goto(blockUrl(block), { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Save', exact: true }).click({ force: true });
    await expect(page.locator('.notice, .clubhouse-notice').first()).toBeVisible();
  }

  await page.goto('/?clubhouse_page=privacy');
  await expect(page.locator('#ch-cookie'), 'a save switched the cookie notice off').toBeVisible();
  expect(await page.locator('.ch-banner').count(), 'a save switched the announcement bar off').toBe(
    bannerBefore,
  );
});
