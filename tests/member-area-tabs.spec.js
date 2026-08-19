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
