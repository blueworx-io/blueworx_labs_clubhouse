const { test, expect } = require('@playwright/test');

// @wordpress only: an admin screen, which the DB-free preview does not have.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test("What's new lists real releases and marks the one being run @wordpress", async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-whats-new', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: "What's new", exact: true })).toBeVisible();

  // The version this site is running is named, and the release carrying it is
  // marked — the one thing an owner opens this screen to check.
  const running = await page.locator('.clubhouse-step__lede').first().innerText();
  const version = running.match(/version (\d+\.\d+\.\d+)/)[1];
  await expect(page.locator('.clubhouse-release').filter({ hasText: `Version ${version}` })).toContainText(
    'You are on this version'
  );

  // Read from the shipped changelog, so a release nobody wrote an entry for
  // cannot appear — every release on the page says something.
  const releases = page.locator('.clubhouse-release');
  expect(await releases.count()).toBeGreaterThan(5);

  // The long history is present but folded away, not 165 releases deep.
  await expect(page.locator('details')).toContainText('Everything before that');
});

test("What's new hangs off the Clubhouse menu @wordpress", async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await expect(
    page.locator('#adminmenu a[href*="page=clubhouse-whats-new"]')
  ).toHaveText(/What.s new/); // WordPress curls the apostrophe in menu labels.
});
