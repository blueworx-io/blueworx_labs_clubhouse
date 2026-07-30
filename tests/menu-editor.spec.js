const { test, expect } = require('@playwright/test');

// @wordpress only: the Menu tab lives in wp-admin, which the DB-free preview
// does not have. These specs mutate a stored option, so they run in series —
// the wordpress project is already non-parallel.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('an owner can rename, reorder and nest a menu item @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  // 'domcontentloaded', not the default 'load': this screen enqueues
  // wp_enqueue_media(), whose scripts keep the load event pending well past
  // Playwright's default timeout even though the form itself is fully usable.
  await page.goto('/wp-admin/admin.php?page=clubhouse-site-content&tab=menu', { waitUntil: 'domcontentloaded' });

  // Row 1 of the defaults is About. Rename it, then hang it under Home.
  await page.fill('input[name="menu[1][label]"]', 'Our club');
  await page.click('button[name="clubhouse_menu_indent[1]"]');
  await expect(page.locator('input[name="menu[0][children][0][label]"]')).toHaveValue('Our club');

  // Every catalogue tab's form shares the name clubhouse_content_submit on its
  // Save button; only the Menu tab's reads "Save menu", so that is what
  // distinguishes it from the other nine hidden forms' Save buttons.
  //
  // force: true — the Indent click just above submitted its own form and
  // reloaded the page (every move button posts the same as Save, per
  // Menu_Panel's docblock); WP-admin's own chrome (admin bar, notices) keeps
  // reflowing for a beat right after that reload, so Playwright's actionability
  // "stable" wait on this far-down button never converges even though its
  // bounding box is provably static. Confirmed by hand: identical coordinates
  // sampled a second apart, yet the plain click still timed out waiting to be
  // "stable" — only forcing it past that check gets the click to land.
  await page.getByRole('button', { name: 'Save menu' }).click({ force: true });
  await expect(page.locator('.notice-success')).toContainText('menu has been saved');

  // The front end shows it nested under its parent.
  await page.goto('/');
  const parent = page.locator('.ch-nav__item--has-children').first();
  await expect(parent).toBeVisible();
  await expect(parent.locator('.ch-nav__sub')).toContainText('Our club');

  // A submenu opens on keyboard focus alone — no pointer, no JavaScript.
  await parent.locator('.ch-nav__link').first().focus();
  await expect(parent.locator('.ch-nav__sub')).toBeVisible();
});

test('a removed item leaves the nav @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-site-content&tab=menu', { waitUntil: 'domcontentloaded' });

  const doomed = await page.inputValue('input[name="menu[1][label]"]');
  await page.click('button[name="clubhouse_menu_remove[1]"]');
  // force: true — see the comment on the identical click above; the Remove
  // click just above reloaded the page the same way the Indent click does.
  await page.getByRole('button', { name: 'Save menu' }).click({ force: true });

  await page.goto('/');
  await expect(page.locator('.ch-nav__links')).not.toContainText(doomed);
});
