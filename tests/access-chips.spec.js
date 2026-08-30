const { test, expect } = require('@playwright/test');

// @wordpress only: these are admin screens, which the DB-free preview does not
// have.
//
// The chips in a ClubHouse screen's top bar tell an administrator which roles
// can reach the page they are looking at. Three screens asked their controller
// for them and two never did, so Search & sharing and the User guide claimed
// nothing about who could open them.

const SCREENS = [
  { slug: 'clubhouse-setup', name: 'Clubhouse Setup' },
  { slug: 'clubhouse-import', name: 'Import' },
  { slug: 'clubhouse-seo', name: 'Search & sharing' },
  { slug: 'clubhouse-guide', name: 'User guide' },
];

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('every clubhouse screen tells an administrator who can reach it @wordpress', async ({ page }) => {
  // Four wp-admin screens in one test, one of them the guide, which builds
  // itself from every live registry. Covering every screen is the point of the
  // test, so the list does not get shortened to save time — the harness carries
  // the budget for a wp-admin screen instead (see playwright.config.js).
  await loginAsAdmin(page);

  for (const screen of SCREENS) {
    await page.goto(`/wp-admin/admin.php?page=${screen.slug}`, { waitUntil: 'domcontentloaded' });

    // Two markups, on purpose. A screen built from the BlueWorx admin design
    // system carries chips in its page header's actions. A page editor library
    // screen — Setup, since v0.100.0 — has a header the library builds from a
    // title, an eyebrow and a line of text, with nowhere to put markup of
    // ours, so it says the same thing in words in that line. What must hold
    // either way is that an administrator is told who can reach it.
    // The page header either way, rather than one markup or the other:
    // Administrator can reach all four, so it is the one label common to every
    // screen — the rest differ by page and are not worth pinning here.
    const head = page.locator('.bw-pagehead').first();
    await expect(
      head,
      `${screen.name} says nothing about who can reach it`,
    ).toContainText('Administrator', { timeout: 30_000 });
  }
});
