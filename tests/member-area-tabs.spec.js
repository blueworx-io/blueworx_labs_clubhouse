const { test, expect } = require('@playwright/test');

// The member area belongs to the shop (issue #261), so these need one installed.
// CI has none, which is a real coverage gap — see tests/helpers/shop.js.
const { hasShop } = require('./helpers/shop');
test.beforeEach(async ({ page }) => {
	test.skip(!(await hasShop(page)), 'no shop installed — run npm run wp:shop');
});

// @wordpress only: the member area is a real route, and what is being proved
// here is that a click does NOT reload the page — which needs a real browser.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

test('the member area draws the sidebar and the top bar @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  await expect(page.locator('.clubhouse-member__side')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__main .bw-pagehead')).toHaveCount(1);
});

test('every panel is on the page, with one shown @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  const panels = page.locator('.clubhouse-member__panel');
  // LatePoint is not installed, so Bookings is not among them.
  await expect(panels).not.toHaveCount(0);
  await expect(page.locator('.clubhouse-member__panel:not([hidden])')).toHaveCount(1);
});

test('a nav item is a real link to a real address @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  const href = await page.locator('[data-view-link]').first().getAttribute('href');
  expect(href).toBeTruthy();
  expect(href).not.toBe('#');
});

// A second view is injected rather than clicked for real: the point is the
// swap itself, and an injected pair keeps this independent of which plugins
// happen to be installed. member-area.js reads its
// panels and links off the DOM once, at load, so the injected second panel
// and link have to be present in the HTML *before* the script runs — a
// page.evaluate() after goto() is too late, the script has already captured
// a NodeList without them. So this intercepts the response and inserts the
// markup server-side-shaped, before the browser ever parses it, then
// exercises the real click handling: asserting both that the panels swapped
// AND that the page never navigated.
test('clicking a nav item swaps panels without navigating @wordpress', async ({ page }) => {
  await page.route('**/member-dashboard/', async (route) => {
    const response = await route.fetch();
    let body = await response.text();
    body = body.replace(
      '</nav>',
      '<a id="test-second-view-link" class="bw-secnav__item" data-view-link="test-second-view"'
        + ' data-view-title="Second view" data-view-lede="A second view for testing."'
        + ' href="/member-dashboard/?view=test-second-view">Second view</a></nav>'
    );
    body = body.replace(
      '</main>',
      '<div class="clubhouse-member__panel" data-view="test-second-view" hidden>Injected second panel</div></main>'
    );
    await route.fulfill({ response, body });
  });

  await page.goto('/member-dashboard/');

  const firstPanel = page.locator('.clubhouse-member__panel').first();
  await expect(firstPanel).toBeVisible();
  await expect(firstPanel).not.toHaveAttribute('hidden', '');

  // Set before the click; if a real navigation happens the page unloads and
  // this flag is lost along with it.
  await page.evaluate(() => {
    window.__stayed = true;
  });

  await page.click('#test-second-view-link');

  const stayed = await page.evaluate(() => window.__stayed === true);
  expect(stayed).toBe(true);

  await expect(firstPanel).toHaveAttribute('hidden', '');
  await expect(page.locator('[data-view="test-second-view"]')).toBeVisible();
  await expect(page).toHaveURL(/view=test-second-view/);
  // Two now — the page head's and the brand block's phone-only pair — and
  // the script updates every one of them (see member-area.js), not just the
  // first it finds.
  await expect(page.locator('[data-member-title]')).toHaveText(['Second view', 'Second view']);

  // Focus should land on the shown panel itself — it carries role="tabpanel"
  // and aria-labelledby, so a screen reader gets the panel's own name rather
  // than the generic "main" landmark around it.
  await expect(page.locator('[data-view="test-second-view"]')).toBeFocused();
});

// LatePoint is not installed, so faking a full club means splicing Bookings
// into the bar, and Orders/Invoices/Plans into the sidebar only — the bar
// never carries those.
// Same response-splicing approach the click test above uses. Billing carries
// their panels itself on a phone, so there are no link rows under it.
test('a phone gets the bottom bar, not the sidebar nav @wordpress', async ({ page }) => {
  await page.route('**/member-dashboard/', async (route) => {
    const response = await route.fetch();
    let body = await response.text();

    const extraNavItems = ['bookings', 'orders', 'invoices', 'plans']
      .map((key) => '<a class="bw-secnav__item" data-view-link="' + key + '"'
        + ' data-view-title="' + key + '" data-view-lede=""'
        + ' href="/member-dashboard/?view=' + key + '">'
        + '<span class="clubhouse-member__navlabel">' + key + '</span></a>')
      .join('');
    body = body.replace('Dashboard</span></a></nav>', 'Dashboard</span></a>' + extraNavItems + '</nav>');

    // The server draws Dashboard, Billing and Account itself now that a shop
    // is installed, so faking a full club only means splicing Bookings in —
    // LatePoint's, which the harness does not have. Orders, Invoices and Plans
    // are sidebar-only and never appear here.
    const barOpen = '<nav class="clubhouse-member__tabbar" aria-label="Your account">';
    const extraTabs = ['bookings']
      .map((key) => '<a class="clubhouse-member__tab" data-view-link="' + key + '"'
        + ' data-view-title="' + key + '" data-view-lede=""'
        + ' href="/member-dashboard/?view=' + key + '">'
        + '<span class="clubhouse-member__tablabel">' + key + '</span></a>')
      .join('');
    body = body.replace(barOpen, barOpen + extraTabs);

    await route.fulfill({ response, body });
  });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__tabbar')).toBeVisible();
  // Dashboard, Billing, Account, the spliced-in Bookings, and the way back.
  await expect(page.locator('.clubhouse-member__tab')).toHaveCount(5);
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeHidden();

  // Billing carries the Plans, Orders and Invoices panels itself, so no link
  // rows are drawn under it — or under any other screen.
  await expect(page.locator('.clubhouse-member__more')).toHaveCount(0);
  await page.locator('.clubhouse-member__tab[data-view-link="billing"]').click();
  await expect(page.locator('.clubhouse-member__more')).toHaveCount(0);
  // The way out is now the bar's own last item, not a separate control beside
  // the club badge — see the media query in assets/bw/bw.css.
  await expect(page.locator('.clubhouse-member__side .clubhouse-member__back')).toBeHidden();

  // The reverse above the phone breakpoint: the sidebar carries every view, so
  // the bar is noise, and the way back returns to the sidebar's own row
  // beside the brand.
  await page.setViewportSize({ width: 1280, height: 900 });
  await expect(page.locator('.clubhouse-member__tabbar')).toBeHidden();
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeVisible();
  await expect(page.locator('.clubhouse-member__side .clubhouse-member__back')).toBeVisible();
});

// No splicing here: this is the club the harness actually is — no shop, no
// bookings. Billing and Account are built from the shop's blocks, so neither
// is offered — see Dashboard_Views::all() — leaving Dashboard and the way
// out. The bar is still drawn for one view, rather than appearing the day a
// plugin is installed.
test('the bar shows exactly the curated list, not every view @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__tabbar')).toBeVisible();
  const tabs = page.locator('.clubhouse-member__tab');
  // Dashboard, Billing, Account, and the way out. Orders, Invoices and Plans
  // are sidebar-only: the bar is a curated list, not whichever views fit.
  await expect(tabs).toHaveCount(4);
  await expect(tabs.nth(0)).toContainText('Dashboard');
  await expect(tabs.nth(3)).toContainText('Back home');
  for (const key of ['orders', 'invoices', 'plans']) {
    await expect(page.locator(`.clubhouse-member__tab[data-view-link="${key}"]`)).toHaveCount(0);
  }

  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeHidden();
  // The way out lives in the bar now, not beside the club badge.
  await expect(page.locator('.clubhouse-member__side .clubhouse-member__back')).toBeHidden();
});

// The page head (.clubhouse-member__head) is dropped on a phone; its title,
// lede and sign-out move into the top row instead — see the phone-only pair
// in Dashboard_Shell::sidebar() and the media query in assets/bw/bw.css.
test('a phone drops the page head in favour of the top row, the desktop keeps it @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__head')).toBeHidden();
  await expect(page.locator('.clubhouse-member__viewtext')).toBeVisible();
  await expect(page.locator('.clubhouse-member__viewtitle')).toHaveText('Your account');
  await expect(page.locator('.clubhouse-member__brandsignout')).toBeVisible();
  await expect(page.locator('.clubhouse-member__tab').last()).toContainText('Back home');

  // The reverse above the phone breakpoint: the page head is back, and the
  // phone-only pair is gone rather than sitting hidden but present twice.
  await page.setViewportSize({ width: 1280, height: 900 });
  await expect(page.locator('.clubhouse-member__head')).toBeVisible();
  await expect(page.locator('.clubhouse-member__viewtext')).toBeHidden();
});

// Who is signed in is desktop-only. A phone's top row already carries the
// section's name, its description and the way out; the avatar and name on top
// of that crowded the row and told a member nothing they did not know.
test('a phone does not show who is signed in @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');
  await expect(page.locator('.clubhouse-member__person')).toBeHidden();

  await page.setViewportSize({ width: 1280, height: 900 });
  await expect(page.locator('.clubhouse-member__person')).toBeVisible();
});
