const { test, expect } = require('@playwright/test');

// @wordpress only: this is about what a WordPress role can actually reach, and
// the DB-free preview has no roles and no wp-admin.
//
// An owner is the person this plugin is for, and editing the club's words is
// the thing they do. A club page is a real WordPress page now, the fourteen
// page editors have no menu item of their own, and WordPress's Pages list is
// the only way into one — so an owner without the page capabilities cannot edit
// their own club's site at all. That is exactly what happened when the Club
// Pages screen was deleted: the capabilities had been stripped because that
// screen existed.
//
// The owner user is seeded by tests/global-setup.js.

const OWNER = { login: 'clubowner', pass: 'owner-test-pw' };

async function signInAsOwner(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', OWNER.login);
  await page.fill('#user_pass', OWNER.pass);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test.describe('@wordpress an owner edits a club page', () => {
  test('the Pages list opens a club page in its own editor, and offers no way to destroy it', async ({ page }) => {
    await signInAsOwner(page);

    await page.goto('/wp-admin/edit.php?post_type=page&post_status=all', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h1').first(), 'an owner was refused the Pages list').toHaveText(/Pages/);

    const row = page
      .locator('#the-list tr', { has: page.locator('a.row-title', { hasText: /^About$/ }) })
      .first();

    // Edit goes to that page's own editor, not the block editor.
    const href = await row.locator('a.row-title').getAttribute('href');
    expect(href).toContain('page=clubhouse-page-about');

    // Reading a club page in this list is all it is for. Quick Edit renames and
    // retitles one inline and Trash removes it — both break a site that routes
    // through these pages, from a screen that looks harmless.
    await expect(row.locator('.row-actions .trash')).toHaveCount(0);
    await expect(row.locator('.row-actions .inline')).toHaveCount(0);

    // And the editor actually opens for them.
    await page.goto(href, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
  });

  /** Global content has no page behind it, so the Pages list cannot be its way in. */
  test('global content is reachable from the Clubhouse menu', async ({ page }) => {
    await signInAsOwner(page);
    await page.goto('/wp-admin/admin.php?page=clubhouse-global-content', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
  });
});
