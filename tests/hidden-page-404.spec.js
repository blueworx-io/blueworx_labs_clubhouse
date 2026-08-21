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
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', {
    waitUntil: 'domcontentloaded',
  });
  // The page switches live on the Visibility tab, and each page has its own
  // sub-tab within it. Both have to be open before the checkbox is on screen.
  // force, for the reason menu-editor.spec.js documents: this screen's media
  // scripts keep reflowing the chrome, so the "stable" wait never converges.
  await page.click('.clubhouse-tab[data-tab="visibility"]', { force: true });
  await page.click(`.clubhouse-vistab[data-vistab="${slug}"]`, { force: true });

  const toggle = page.locator(`input[name="clubhouse_page[${slug}]"]`);
  await expect(toggle).toBeVisible();
  if ((await toggle.isChecked()) !== visible) {
    await toggle.click({ force: true });
  }
  // force, for the reason menu-editor.spec.js documents.
  await page.locator('button[name="clubhouse_setup_submit"]').click({ force: true });
  await expect(page.locator('.notice, .clubhouse-notice').first()).toBeVisible();
}

test('a page switched off answers 404, and a page that is on does not @wordpress', async ({
  page,
}) => {
  // Two full trips through the Setup screen — switch off, then switch back —
  // on top of signing in. PHP's built-in server is single-threaded (see
  // playwright.config.js), so that admin screen and its scripts alone can eat
  // the default 30s budget. Slow by nature, not by failure.
  test.slow();
  await loginAsAdmin(page);

  const on = await page.goto('/contact/');
  expect(on.status(), 'a visible page').toBe(200);

  try {
    await setPageVisible(page, 'contact', false);

    const off = await page.goto('/contact/');
    expect(off.status(), 'a switched-off page still answered').toBe(404);
    // Regression: the real page behind /contact/ still exists and still
    // matched, so the template filter that serves it kept doing so even after
    // set_404() ran — right status, but an empty body instead of the theme's
    // own 404 page.
    const bodyText = await page.locator('body').innerText();
    expect(bodyText.trim().length, 'a 404 must not render an empty body').toBeGreaterThan(0);
  } finally {
    // Always put it back. Visibility is stored site-wide, so a failure partway
    // through would leave every later spec looking at a site missing a page.
    await setPageVisible(page, 'contact', true);
  }
});
