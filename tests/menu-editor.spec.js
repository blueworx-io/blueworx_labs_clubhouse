const { test, expect } = require('@playwright/test');

// @wordpress only: the menu builder lives in wp-admin, on the Clubhouse screen
// (it moved there in issue #144), which the DB-free preview does not have.
// These specs mutate a stored option, so they run in series — the wordpress
// project is already non-parallel.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

// The Clubhouse screen opens on Base Look & Branding, so the Menu tab has to be
// selected — and re-selected after every move button, since each one posts and
// reloads the screen back to its first tab.
async function openMenuTab(page) {
  // 'domcontentloaded', not the default 'load': this screen enqueues
  // wp_enqueue_media(), whose scripts keep the load event pending well past
  // Playwright's default timeout even though the form itself is fully usable.
  // force: true for the same reason as the Save click below — this screen's
  // media scripts keep reflowing the chrome, so Playwright's "stable" wait
  // never converges even though the tab is sitting still.
  // Clicking once is not enough: only this screen's own JS puts .is-active on a
  // panel, and on a loaded machine that JS can land well after DOMContentLoaded.
  // A click that arrives before the handler is bound does nothing at all and the
  // panel never opens — which is what made this flake in CI while passing
  // locally. So keep asking until the panel is actually open.
  await page.waitForLoadState('domcontentloaded');
  const tab = page.locator('.clubhouse-tab[data-tab="menu"]');
  const panel = page.locator('.clubhouse-panel[data-panel="menu"]');
  await tab.waitFor({ state: 'attached' });
  await expect
    .poll(
      async () => {
        await tab.click({ force: true }).catch(() => {});
        return panel.evaluate((el) => el.classList.contains('is-active')).catch(() => false);
      },
      { timeout: 60_000, intervals: [250, 500, 1000], message: 'the Menu tab never opened' }
    )
    .toBe(true);
  await expect(panel).toBeVisible();
}

test('an owner can rename, reorder and nest a menu item @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await openMenuTab(page);

  // Row 1 of the defaults is About. Rename it, then hang it under Home.
  await page.fill('input[name="menu[1][label]"]', 'Our club');
  await page.click('button[name="clubhouse_menu_indent[1]"]');
  await openMenuTab(page);
  await expect(page.locator('input[name="menu[0][children][0][label]"]')).toHaveValue('Our club');

  // The Menu tab's Save reads "Save menu", which distinguishes it from the
  // setup form's own "Save changes" on the same screen.
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
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await openMenuTab(page);

  const doomed = await page.inputValue('input[name="menu[1][label]"]');
  await page.click('button[name="clubhouse_menu_remove[1]"]');
  await openMenuTab(page);
  // force: true — see the comment on the identical click above; the Remove
  // click just above reloaded the page the same way the Indent click does.
  await page.getByRole('button', { name: 'Save menu' }).click({ force: true });

  await page.goto('/');
  await expect(page.locator('.ch-nav__links')).not.toContainText(doomed);
});
