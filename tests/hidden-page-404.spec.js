const { test, expect } = require('@playwright/test');

// @wordpress only: the bug is about the HTTP status WordPress serves, which the
// DB-free preview has no part in.
//
// Issue #211. A page switched off still matched its rewrite rule, so WordPress
// had a valid query and answered 200 with whatever the theme falls back to —
// its blog index, on the smoke-test install. Declining to render is not the
// same as saying the page is not there, and a search engine reads the status.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function setPageVisible(page, slug, visible) {
  await page.goto(`/wp-admin/admin.php?page=clubhouse-pages&club_page=${slug}`, {
    waitUntil: 'domcontentloaded',
  });
  const toggle = page.locator('input[name="clubhouse_page_enabled"]');
  await expect(toggle).toBeVisible();
  if ((await toggle.isChecked()) !== visible) {
    await toggle.click({ force: true });
  }
  // force, for the reason menu-editor.spec.js documents.
  await page
    .locator('form:has(input[name="clubhouse_pages_switch"]) button[name="clubhouse_pages_submit"]')
    .click({ force: true });
  await expect(page.locator('.notice, .clubhouse-notice').first()).toBeVisible();
}

test('a page switched off answers 404, and a page that is on does not @wordpress', async ({
  page,
}) => {
  await loginAsAdmin(page);

  const on = await page.goto('/contact/');
  expect(on.status(), 'a visible page').toBe(200);

  try {
    await setPageVisible(page, 'contact', false);

    const off = await page.goto('/contact/');
    expect(off.status(), 'a switched-off page still answered').toBe(404);
  } finally {
    // Always put it back. Visibility is stored site-wide, so a failure partway
    // through would leave every later spec looking at a site missing a page.
    await setPageVisible(page, 'contact', true);
  }

  const back = await page.goto('/contact/');
  expect(back.status(), 'switching it back on did not restore it').toBe(200);
});

// The rewrite rule for Bookings is registered whether or not LatePoint is
// installed, because rules are cached until flushed. CI has no LatePoint, so
// this is the integration-missing half of the same path.
test('a page whose integration is missing answers 404 too @wordpress', async ({ page }) => {
  const res = await page.goto('/booking/');
  expect(res.status()).toBe(404);
});
