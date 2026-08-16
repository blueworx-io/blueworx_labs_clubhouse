const { test, expect } = require('@playwright/test');

// @wordpress only: the welcome pack renders on the customer dashboard, which is
// a real WordPress page the DB-free preview does not have.
//
// The fixture is the same page external-chrome.spec.js uses — an ordinary page
// carrying SureCart's dashboard template slug — with the dashboard page option
// pointed at it by tests/global-setup.js. That option is what the code keys off
// and what SureCart itself writes, so the fixture is the real contract rather
// than a stand-in for it. Installing SureCart to assert our own block would be
// testing SureCart, the same reasoning external-chrome.spec.js records.
const DASHBOARD = '/external-chrome-fixture/';

test('a member sees the club welcome pack on their dashboard @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);

  const pack = page.locator('.clubhouse-welcome');
  await expect(pack).toHaveCount(1);
  await expect(pack.getByRole('heading', { name: 'Welcome to the club' })).toBeVisible();
  await expect(pack).toContainText('The gate code is on your membership card.');
  // A blank line in the admin textarea is a new paragraph.
  await expect(pack.locator('p.clubhouse-welcome__p')).toHaveCount(3);
  await expect(pack.getByRole('link', { name: 'Read the handbook' })).toHaveAttribute(
    'href',
    'https://club.example/handbook',
  );
});

test('the pack sits below what the shop renders, not above it @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);

  const order = await page.evaluate(() => {
    const own = document.querySelector('#foreign-content');
    const pack = document.querySelector('.clubhouse-welcome');
    if (!own || !pack) return null;
    // 4 === DOCUMENT_POSITION_FOLLOWING: the pack comes after the page's own
    // content, which is the whole point of the priority the filter runs at.
    return own.compareDocumentPosition(pack) & 4 ? 'after' : 'before';
  });

  expect(order).toBe('after');
});

// The reason this renders plainly at all: the dashboard is the shop's page and
// deliberately carries none of the club's look. A pack reaching for design
// tokens that are not there would arrive unstyled.
test('the dashboard still stands alone, with no club chrome @wordpress', async ({ page }) => {
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
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  // Two levels of tab, and force, for the reasons user-guide.spec.js documents.
  await page.getByRole('tab', { name: 'Visibility' }).click({ force: true });
  await page.locator('button.clubhouse-vistab[data-vistab="home"]').click({ force: true });

  // The checkbox is opacity:0 and 0×0 with the switch drawn beside it, so the
  // input is driven directly. Clicking the visible switch is the more faithful
  // gesture, but this one sits well down a long grid and wp-admin's chrome
  // keeps reflowing after load, so a positional click lands on stale
  // coordinates and silently misses.
  const toggle = page.locator('input[name="clubhouse_section[home.welcome]"]');
  const label = page.locator('label.clubhouse-toggle', { has: toggle });
  await expect(label).toBeVisible();

  if ((await toggle.isChecked()) !== visible) {
    // A real click event on the real switch, dispatched in the page rather than
    // aimed at screen coordinates. The input is 0×0 with the switch drawn beside
    // it, and wp-admin's chrome keeps reflowing after this screen loads, so a
    // positional click either misses or never satisfies Playwright's stability
    // wait — the problem menu-editor.spec.js documents. This still exercises the
    // control an owner uses; only the aiming is different.
    await label.evaluate((el) => el.click());
  }
  expect(await toggle.isChecked()).toBe(visible);

  await page.getByRole('button', { name: /save/i }).first().click({ force: true });
  await expect(page.locator('.notice-success')).toBeVisible();
}

test('switching the welcome pack off takes it off the dashboard @wordpress', async ({ page }) => {
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
