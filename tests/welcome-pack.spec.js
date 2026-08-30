const { test, expect } = require('@playwright/test');

// The member area belongs to the shop (issue #261), so these need one installed.
// CI has none, which is a real coverage gap — see tests/helpers/shop.js.
const { hasShop } = require('./helpers/shop');
test.beforeEach(async ({ page }) => {
	test.skip(!(await hasShop(page)), 'no shop installed — run npm run wp:shop');
});

// @wordpress only: the welcome pack renders on the member area, which is a
// real WordPress route the DB-free preview does not have.
const DASHBOARD = '/member-dashboard/';

test('a member sees the club welcome pack on their dashboard @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto(DASHBOARD);

  const pack = page.locator('.clubhouse-welcome');
  await expect(pack).toHaveCount(1);
  await expect(pack.getByRole('heading', { name: 'Welcome to the club' })).toBeVisible();
  await expect(pack).toContainText('The gate code is on your membership card.');
  // A blank line in the admin textarea is a new paragraph. The link is no
  // longer one of them — it is a button in its own row.
  await expect(pack.locator('p.clubhouse-welcome__p')).toHaveCount(2);
  await expect(pack.getByRole('link', { name: 'Read the handbook' })).toHaveAttribute(
    'href',
    'https://club.example/handbook',
  );
});

test('the pack greets a member above everything else on the page @wordpress', async ({ page }) => {
  // It used to sit below all of it, which on a real club's dashboard meant
  // under the tabs and an empty appointments list — the last thing a new
  // member would see, if they scrolled at all. The member area now draws the
  // pack itself, first thing in the overview.
  await loginAsAdmin(page);
  await page.goto(DASHBOARD);

  const order = await page.evaluate(() => {
    const pack = document.querySelector('.clubhouse-welcome');
    const rest = document.querySelector('.clubhouse-member__quicks, .bw-card');
    if (!pack) return null;
    if (!rest) return 'before'; // Nothing else on the page to come after.
    return pack.compareDocumentPosition(rest) & 4 ? 'before' : 'after';
  });

  expect(order).toBe('before');
});

// The reason this renders plainly at all: the dashboard is the shop's page and
// deliberately carries none of the club's look. A pack reaching for design
// tokens that are not there would arrive unstyled.
test('the dashboard still stands alone, with no club chrome @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto(DASHBOARD);

  await expect(page.locator('.clubhouse-welcome')).toHaveCount(1);
  await expect(page.locator('header.ch-nav')).toHaveCount(0);
  await expect(page.locator('.ch-footer')).toHaveCount(0);
});

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

// Its own section toggle, like every other section, driven through the screen
// an owner actually uses so the toggle being wired to the right address is part
// of what is proved.
//
// Setup → Visibility rather than the matching switch on Club Pages: that one
// rides in the Global tab's content form, and saving it writes every other
// field on that tab at its current value — which for a site that has never
// edited them means writing empty over the defaults, switching the cookie
// notice and announcement bar off as a side effect. Worth its own issue; not
// something a test about the welcome pack should be doing to the site.
async function setPackVisible(page, visible) {
  // The pack's own Shown switch, on the panel it belongs to. Section
  // visibility left Setup in phase 3 — a section is switched off on the page
  // it is part of, which for the welcome pack is Global content.
  await page.goto('/wp-admin/admin.php?page=clubhouse-global-content', {
    waitUntil: 'domcontentloaded',
  });
  // The editor is a JS app; nothing below exists until it has mounted.
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });

  // The panel's own Shown switch. Located as the panel's one checkbox rather
  // than by a label, for the reason club-page-editor.spec.js documents: the
  // library draws the switch as a styled span with the real input behind it,
  // and that input carries no id of its own.
  const panel = page
    .locator('section.bw-card')
    .filter({ has: page.getByRole('heading', { name: 'Welcome pack', exact: true }) });
  const toggle = panel.locator('input[type="checkbox"]');
  await expect(toggle).toBeAttached({ timeout: 30_000 });

  if ((await toggle.isChecked()) !== visible) {
    await toggle.setChecked(visible);
    await page.getByRole('button', { name: 'Save changes' }).click();
  }
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

test('switching the welcome pack off takes it off the dashboard @wordpress', async ({ page }) => {
  // Two saves of the Setup screen and two dashboard loads in one test — slow by
  // nature, not by failure. The harness carries the budget for a wp-admin
  // screen (see playwright.config.js).
  await loginAsAdmin(page);

  try {
    await setPackVisible(page, false);
    await page.goto(DASHBOARD);
    await expect(page.locator('.clubhouse-welcome')).toHaveCount(0);
  } finally {
    // Always put it back. Visibility is stored site-wide, so a failure partway
    // through would otherwise leave every later run looking at a site with the
    // pack switched off, and the failure would look like it was somewhere else.
    await setPackVisible(page, true);
  }

  await page.goto(DASHBOARD);
  await expect(page.locator('.clubhouse-welcome')).toHaveCount(1);
});

test('the welcome pack appears on the dashboard and nowhere else @wordpress', async ({ page }) => {
  for (const url of ['/', '/clubhouse-post-fixture/', '/?clubhouse_page=membership']) {
    await page.goto(url);
    await expect(page.locator('.clubhouse-welcome'), `pack leaked onto ${url}`).toHaveCount(0);
  }
});

test('a signed-out visitor is sent to the club login page @wordpress', async ({ page }) => {
  await page.context().clearCookies();
  await page.goto('/member-dashboard/');
  await expect(page).toHaveURL(/\/login\/?$/);
});
