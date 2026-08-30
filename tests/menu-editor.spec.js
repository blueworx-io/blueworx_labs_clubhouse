const { test, expect } = require('@playwright/test');

// @wordpress only: the menu builder lives in wp-admin, on the Clubhouse screen,
// which the DB-free preview does not have.
//
// The menu is a repeater on the Setup screen now, so it saves with everything
// else: one save bar per screen, whatever tab is showing (issue #285). It used
// to carry its own "Save menu" button, because it was a second form sitting
// inside somebody else's screen.
//
// These specs mutate a stored option, so they run in series — the wordpress
// project is already non-parallel.

const OWNER = { login: 'clubowner', pass: 'owner-test-pw' };

async function signInAsOwner(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', OWNER.login);
  await page.fill('#user_pass', OWNER.pass);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function openSetup(page) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  // The editor mounts itself, so nothing on the screen is real until the save
  // bar is.
  await expect(page.locator('.bw-savebar')).toBeVisible({ timeout: 30_000 });
}

async function openTab(page, label) {
  await page.locator('.bw-tab', { hasText: label }).first().click();
}

async function save(page) {
  await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

test.describe('@wordpress the menu editor', () => {
  test('an owner can rename an item and nest it under the one above', async ({ page }) => {
    await signInAsOwner(page);
    await openSetup(page);
    await openTab(page, 'Menu');

    const rows = page.locator('.bw-repeater__row');
    await expect(rows.first()).toBeVisible();

    // Row 2 of the defaults is About. Rename it and hang it under Home.
    const about = rows.nth(1);
    await about.locator('#label-1').fill('Our club');
    await about.locator('#nested-1').check();

    await save(page);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('.bw-savebar')).toBeVisible({ timeout: 30_000 });
    await openTab(page, 'Menu');

    await expect(page.locator('.bw-repeater__row').nth(1).locator('#label-1')).toHaveValue('Our club');
    await expect(page.locator('.bw-repeater__row').nth(1).locator('#nested-1')).toBeChecked();

    // And it reaches the site: the renamed item is in the nav.
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('header')).toContainText('Our club');
  });

  test('there is one save bar on the screen, whatever tab is showing', async ({ page }) => {
    await signInAsOwner(page);
    await openSetup(page);

    for (const tab of ['Base Look & Branding', 'Visibility', 'Menu', 'Members', 'Settings']) {
      await openTab(page, tab);
      await expect(page.locator('.bw-savebar')).toHaveCount(1);
    }
  });

  test('a menu item can be added and removed', async ({ page }) => {
    await signInAsOwner(page);
    await openSetup(page);
    await openTab(page, 'Menu');

    const before = await page.locator('.bw-repeater__row').count();
    await page.locator('button', { hasText: 'Add a row' }).click();
    await expect(page.locator('.bw-repeater__row')).toHaveCount(before + 1);

    await page.locator('.bw-repeater__row').last().locator('[aria-label="Remove this row"]').click();
    await expect(page.locator('.bw-repeater__row')).toHaveCount(before);
  });
});
