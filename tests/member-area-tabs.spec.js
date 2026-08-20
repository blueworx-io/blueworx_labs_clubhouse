const { test, expect } = require('@playwright/test');

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
  // The harness has neither SureCart nor LatePoint, so there is one view.
  await expect(panels).not.toHaveCount(0);
  await expect(page.locator('.clubhouse-member__panel:not([hidden])')).toHaveCount(1);
});

test('a nav item is a real link to a real address @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  const href = await page.locator('[data-view-link]').first().getAttribute('href');
  expect(href).toBeTruthy();
  expect(href).not.toBe('#');
});

// The harness has neither SureCart nor LatePoint, so the member area here
// offers only one view (see the test above) — there is no second real nav
// item to click to prove the switch honestly. member-area.js reads its
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

// The harness has neither SureCart nor LatePoint, so the real page already
// carries Dashboard, Billing and Account — see Dashboard_Views::all(): Billing
// and Account no longer require a shop. The bar is a curated list now (Task 1
// of the bar-report brief), so faking a full club means adding Bookings to the
// bar, Orders/Invoices/Plans to the sidebar and the overflow rows only — not
// to the bar, which never carries them — the same response-splicing approach
// the click test above uses.
test('a phone gets the bottom bar and the overflow rows, not the sidebar nav @wordpress', async ({ page }) => {
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

    // The server already draws the bar, carrying Dashboard, Billing and
    // Account for this club. Bookings is the one extra view the bar can carry
    // — Orders, Invoices and Plans are sidebar-only and never appear here, so
    // they are what overflow_links() offers instead.
    const barOpen = '<nav class="clubhouse-member__tabbar" aria-label="Your account">';
    const bookingsTab = '<a class="clubhouse-member__tab" data-view-link="bookings"'
      + ' data-view-title="bookings" data-view-lede="" href="/member-dashboard/?view=bookings">'
      + '<span class="clubhouse-member__tablabel">bookings</span></a>';
    body = body.replace(barOpen, barOpen + bookingsTab);

    const more = '<nav class="clubhouse-member__more" aria-label="More of your account">'
      + ['orders', 'invoices', 'plans']
        .map((key) => '<a class="clubhouse-member__morelink" data-view-link="' + key + '"'
          + ' data-view-title="' + key + '" data-view-lede=""'
          + ' href="/member-dashboard/?view=' + key + '"><span>' + key + '</span></a>')
        .join('')
      + '</nav>';

    // The real markup places the overflow rows at the foot of the Billing
    // panel and nowhere else, so splice them exactly there.
    body = body.replace(
      /(<div class="clubhouse-member__panel" data-view="billing"[\s\S]*?)(<\/div>)(?=<div class="clubhouse-member__panel"|<\/main>)/,
      '$1' + more + '$2'
    );

    await route.fulfill({ response, body });
  });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__tabbar')).toBeVisible();
  // Dashboard, Bookings, Billing, Account, and the way back: five tabs.
  await expect(page.locator('.clubhouse-member__tab')).toHaveCount(5);
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeHidden();

  // Orders, Invoices and Plans live at the foot of Billing alone — one copy on
  // the page, inside that panel, so they are not a stray menu under every
  // screen. Hidden while Dashboard is up; there when Billing is.
  await expect(page.locator('.clubhouse-member__more')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__panel[data-view="billing"] .clubhouse-member__more')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__more')).toBeHidden();
  await page.locator('.clubhouse-member__tab[data-view-link="billing"]').click();
  await expect(page.locator('.clubhouse-member__more')).toBeVisible();
  await expect(page.locator('.clubhouse-member__more [data-view-link="orders"]')).toBeVisible();
  await expect(page.locator('.clubhouse-member__more [data-view-link="invoices"]')).toBeVisible();
  await expect(page.locator('.clubhouse-member__more [data-view-link="plans"]')).toBeVisible();
  // The way out is now the bar's own last item, not a separate control beside
  // the club badge — see the media query in assets/bw/bw.css.
  await expect(page.locator('.clubhouse-member__side .clubhouse-member__back')).toBeHidden();

  // The reverse above the phone breakpoint: the sidebar carries every view,
  // so the bar and the overflow rows are noise, and the way back returns to
  // the sidebar's own row beside the brand.
  await page.setViewportSize({ width: 1280, height: 900 });
  await expect(page.locator('.clubhouse-member__tabbar')).toBeHidden();
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeVisible();
  await expect(page.locator('.clubhouse-member__more')).toBeHidden();
  await expect(page.locator('.clubhouse-member__side .clubhouse-member__back')).toBeVisible();
});

// No splicing here: this is the club the harness actually is — no shop, no
// bookings. Dashboard, Billing and Account are still offered — see
// Dashboard_Views::all() — so the bar is a curated list of exactly those
// three plus the way out, never "the first five views" or a single one.
test('the bar shows exactly the curated list on a club with no shop and no bookings @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__tabbar')).toBeVisible();
  const tabs = page.locator('.clubhouse-member__tab');
  await expect(tabs).toHaveCount(4);
  await expect(tabs.nth(0)).toContainText('Dashboard');
  await expect(tabs.nth(1)).toContainText('Billing');
  await expect(tabs.nth(2)).toContainText('Account');
  await expect(tabs.nth(3)).toContainText('Back home');

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
