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

/** The titles WordPress lists under one post status on its own Pages screen. */
async function titlesWithStatus(page, status) {
  await page.goto(`/wp-admin/edit.php?post_type=page&post_status=${status}`, {
    waitUntil: 'domcontentloaded',
  });
  return page.locator('#the-list .column-title strong').allInnerTexts();
}

/** What status WordPress itself has a page in — 'publish', 'draft', or 'missing'. */
async function pageStatus(page, title) {
  for (const status of ['draft', 'publish']) {
    const titles = await titlesWithStatus(page, status);
    if (titles.some((text) => text.trim().startsWith(title))) {
      return status;
    }
  }
  return 'missing';
}

/** Change status on the named pages through Bulk Edit, as an admin would. */
async function bulkSetStatus(page, titles, status) {
  await page.goto('/wp-admin/edit.php?post_type=page', { waitUntil: 'domcontentloaded' });
  for (const title of titles) {
    const row = page.locator('#the-list tr').filter({ hasText: title }).first();
    await row.locator('input[type="checkbox"]').first().check({ force: true });
  }
  await page.selectOption('#bulk-action-selector-top', 'edit');
  // force, for the reason menu-editor.spec.js documents: this screen never settles.
  await page.locator('#doaction').click({ force: true });
  await expect(page.locator('#bulk-edit')).toBeVisible();
  await page.selectOption('#bulk-edit select[name="_status"]', status);
  await page.locator('#bulk_edit').click({ force: true });
  await page.waitForLoadState('domcontentloaded');
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
    // Regression: the real page behind /contact/ still exists and still
    // matched, so the template filter that serves it kept doing so even after
    // set_404() ran — right status, but an empty body instead of the theme's
    // own 404 page.
    const bodyText = await page.locator('body').innerText();
    expect(bodyText.trim().length, 'a 404 must not render an empty body').toBeGreaterThan(0);

    // Switched off is a draft, which is how WordPress itself keeps a page out
    // of the sitemap and out of search. The flag alone only stopped this
    // plugin rendering; everything else still treated the page as live.
    expect(await pageStatus(page, 'Contact'), 'a switched-off page').toBe('draft');
  } finally {
    // Always put it back. Visibility is stored site-wide, so a failure partway
    // through would leave every later spec looking at a site missing a page.
    await setPageVisible(page, 'contact', true);
  }

  // And switching it back on publishes it again, so the sitemap and search get
  // it back without anyone touching WordPress.
  expect(await pageStatus(page, 'Contact'), 'a page switched back on').toBe('publish');
});

test('a bulk status change in the Pages list cannot switch a club page off @wordpress', async ({
  page,
}) => {
  // Bulk Edit reaches wp_update_post() directly — nowhere near the row actions
  // taken away from a club page, and nowhere near the trash and delete hooks.
  // Now that a page's status is what "switched off" means, a bulk change here
  // would switch a page off behind the Setup screen's back, leaving the stored
  // flag saying one thing and the page another.
  await loginAsAdmin(page);

  try {
    await bulkSetStatus(page, ['Contact', 'Sample Page'], 'draft');

    expect(await pageStatus(page, 'Contact'), 'a club page').toBe('publish');
    // The same change on an ordinary page is nobody's business but the
    // admin's, and still takes effect.
    expect(await pageStatus(page, 'Sample Page'), 'an ordinary page').toBe('draft');
  } finally {
    await bulkSetStatus(page, ['Sample Page'], 'publish');
  }
});
