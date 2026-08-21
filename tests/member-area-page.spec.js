const { test, expect } = require('@playwright/test');

// @wordpress only: these prove the routing, which the DB-free preview has none of.
//
// The harness has neither SureCart nor LatePoint installed, so what is covered
// here is the frame and the journeys around it — not the shop's own panels,
// which would be testing SureCart.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

// Seeded by global-setup.js. Subscriber, not admin: admin also holds
// read_private_pages, which is exactly the capability that hid the original
// bug — the member area's page was created 'private', so it answered for admin
// while 404ing for every ordinary member once WordPress's own page routing took
// over its URL. Its page is published now; this signs in as a real member so a
// return to that mistake fails here rather than in production.
async function signInAsMember(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'member');
  await page.fill('#user_pass', 'wptest-member-pw');
  await page.click('#wp-submit');
  // A subscriber lands on wp-admin's (very limited) dashboard, not a front-end
  // page, so body.logged-in — a front-end body_class() addition — is never
  // there to find. The admin toolbar renders for every signed-in role by
  // default and is the one thing both destinations share.
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

// Same helper as hidden-page-404.spec.js — reused rather than reinvented.
async function setPageVisible(page, slug, visible) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', {
    waitUntil: 'domcontentloaded',
  });
  await page.click('.clubhouse-tab[data-tab="visibility"]', { force: true });
  await page.click(`.clubhouse-vistab[data-vistab="${slug}"]`, { force: true });

  const toggle = page.locator(`input[name="clubhouse_page[${slug}]"]`);
  await expect(toggle).toBeVisible();
  if ((await toggle.isChecked()) !== visible) {
    await toggle.click({ force: true });
  }
  await page.locator('button[name="clubhouse_setup_submit"]').click({ force: true });
  await expect(page.locator('.notice, .clubhouse-notice').first()).toBeVisible();
}

test('the member area serves at its own club address @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/member-dashboard/');
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
});

// Regression found in review: the member area's real page was created as a
// WordPress private post, and WordPress's own page query filters a private page
// out of its results for anyone without read_private_pages — every ordinary
// signed-in member. admin holds that capability and so never saw the 404 a real
// member would get. Signed-out is already covered by member-dashboard.spec.js's
// redirect-to-login test — this is the missing signed-in-but-not-admin case.
test('an ordinary signed-in member reaches the member area, not a 404 @wordpress', async ({ page }) => {
  await signInAsMember(page);
  const res = await page.goto('/member-dashboard/');
  expect(res.status(), 'a subscriber must be able to open the member area').toBe(200);
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
});

test('the old account page carries a member across, panel and all @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/member-area-fixture/?view=orders');
  await expect(page).toHaveURL(/\/member-dashboard\/\?view=orders/);
});

test('the header offers the member area to a signed-in member @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/');
  // Header markup is <header class="ch-nav">, not .ch-header — there is no
  // .ch-header class anywhere in the plugin.
  await expect(page.locator('.ch-nav').getByRole('link', { name: 'Member area' })).toBeVisible();
});

test('the header offers log in to everyone else @wordpress', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.ch-nav').getByRole('link', { name: 'Log in' })).toBeVisible();
});

// Club pages are real WordPress pages, so the Pages screen is a real place to
// look again and is back on the menu. It was taken off while they were only
// rewrite rules; leaving it off now would hide half a club's own site from it.
test('wp-admin offers the Pages screen @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/');
  // Two links, not one: the top-level Pages item and its own All Pages child.
  await expect(page.locator('#adminmenu a[href="edit.php?post_type=page"]').first()).toBeVisible();
});

test('a switched-off member area answers 404 @wordpress', async ({ page }) => {
  // Two full trips through the Setup screen — switch off, then switch back —
  // on top of signing in. PHP's built-in server is single-threaded (see
  // playwright.config.js), so that admin screen and its scripts alone can eat
  // the default 30s budget. Slow by nature, not by failure.
  test.slow();
  await signIn(page);

  try {
    await setPageVisible(page, 'member-dashboard', false);

    const off = await page.goto('/member-dashboard/');
    expect(off.status(), 'a switched-off member area still answered').toBe(404);
  } finally {
    // Always put it back — visibility is stored site-wide, so a failure partway
    // through would leave every later spec looking at a site missing the page.
    await setPageVisible(page, 'member-dashboard', true);
  }
});

test('the SEO report does not score the members-only screen @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-seo', { waitUntil: 'domcontentloaded' });
  // The report lists every page a visitor or a search engine can reach. The
  // member area is neither, so it must not be scored — and a row appearing
  // here would mean the private-page guard had stopped working.
  await expect(page.getByText('Member area')).toHaveCount(0);
  // Proves the report rendered at all, rather than passing on an error page.
  await expect(page.getByText('Membership')).not.toHaveCount(0);
});
