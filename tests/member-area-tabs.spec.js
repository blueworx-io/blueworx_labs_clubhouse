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
  await expect(page.locator('[data-member-title]')).toHaveText('Second view');

  // Focus should land on the shown panel itself — it carries role="tabpanel"
  // and aria-labelledby, so a screen reader gets the panel's own name rather
  // than the generic "main" landmark around it.
  await expect(page.locator('[data-view="test-second-view"]')).toBeFocused();
});

// The harness has neither SureCart nor LatePoint, so the real page draws only
// one view and the tab bar never renders at all (Dashboard_Shell::tabbar()
// returns '' below two views). Proving the phone layout honestly means giving
// it enough views to fill the bar and overflow it — six — the same
// response-splicing approach the click test above uses, extended to the
// sidebar nav, the tab bar and the overflow rows.
test('a phone gets the bottom bar and the overflow rows, not the sidebar nav @wordpress', async ({ page }) => {
  await page.route('**/member-dashboard/', async (route) => {
    const response = await route.fetch();
    let body = await response.text();

    const extraNavItems = ['bookings', 'orders', 'invoices', 'plans', 'account']
      .map((key) => '<a class="bw-secnav__item" data-view-link="' + key + '"'
        + ' data-view-title="' + key + '" data-view-lede=""'
        + ' href="/member-dashboard/?view=' + key + '">'
        + '<span class="clubhouse-member__navlabel">' + key + '</span></a>')
      .join('');
    body = body.replace('Dashboard</span></a></nav>', 'Dashboard</span></a>' + extraNavItems + '</nav>');

    // The server already draws the bar, carrying this club's one view, so
    // four more are spliced into it rather than a second bar being injected.
    // Five fit; the sixth ("account") is what overflow_links() offers instead
    // — see includes/dashboard/class-member-dashboard.php.
    const tabbarItems = ['bookings', 'orders', 'invoices', 'plans']
      .map((key) => '<a class="clubhouse-member__tab" data-view-link="' + key + '"'
        + ' data-view-title="' + key + '" data-view-lede=""'
        + ' href="/member-dashboard/?view=' + key + '">'
        + '<span class="clubhouse-member__tablabel">' + key + '</span></a>')
      .join('');
    const barOpen = '<nav class="clubhouse-member__tabbar" aria-label="Your account">';
    body = body.replace(barOpen, barOpen + tabbarItems);

    const more = '<nav class="clubhouse-member__more" aria-label="More of your account">'
      + '<a class="clubhouse-member__morelink" data-view-link="account" data-view-title="account" data-view-lede=""'
      + ' href="/member-dashboard/?view=account"><span>account</span></a></nav>';

    // The real markup places the overflow rows inside the panel (main).
    body = body.replace('</main>', more + '</main>');

    await route.fulfill({ response, body });
  });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__tabbar')).toBeVisible();
  await expect(page.locator('.clubhouse-member__tab')).toHaveCount(5);
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeHidden();
  await expect(page.locator('.clubhouse-member__more')).toBeVisible();
  await expect(page.locator('.clubhouse-member__more [data-view-link="account"]')).toBeVisible();
  // The bottom bar carries no way out of the member area — the sidebar's own
  // "Back to the club site" link has to stay reachable on a phone.
  await expect(page.locator('.clubhouse-member__back')).toBeVisible();

  // The reverse above the phone breakpoint: the sidebar carries every view,
  // so the bar and the overflow rows are noise.
  await page.setViewportSize({ width: 1280, height: 900 });
  await expect(page.locator('.clubhouse-member__tabbar')).toBeHidden();
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeVisible();
  await expect(page.locator('.clubhouse-member__more')).toBeHidden();
});

// No splicing here: this is the club the harness actually is — no shop, no
// bookings, so one view. The bar is drawn for it all the same, so the member
// area looks the same on every club rather than growing a bar the day a plugin
// is installed.
test('a club with a single view still gets the bottom bar @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');

  await expect(page.locator('.clubhouse-member__tabbar')).toBeVisible();
  await expect(page.locator('.clubhouse-member__tab')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeHidden();
  await expect(page.locator('.clubhouse-member__back')).toBeVisible();
});
