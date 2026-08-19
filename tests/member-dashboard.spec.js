const { test, expect } = require('@playwright/test');

// @wordpress only: the member area renders on the customer dashboard, which is
// a real WordPress page the DB-free preview does not have.
//
// The fixture is member-area-fixture — an ordinary page carrying SureCart's
// dashboard template slug, with the dashboard page option pointed at it by
// tests/global-setup.js. That option is what the code keys off and what
// SureCart itself writes. It is a separate fixture from external-chrome's:
// that one proves a foreign page is left untouched, while this one is the
// page the member area replaces the content of.
//
// The harness has neither SureCart nor LatePoint installed, so what these
// assertions cover is the empty path: the frame renders, no dead nav items are
// offered, and nothing fatals. Asserting SureCart's own panels would be testing
// SureCart, the same reasoning external-chrome.spec.js records.
//
// Every test signs in first: the member area is for members, and a signed-out
// visitor is deliberately left with the page's own content (which on a real
// club is SureCart's sign-in form) rather than a frame telling them the club
// has set nothing up.
const DASHBOARD = '/member-area-fixture/';

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

test('a member gets the club frame around their account page @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
  await expect(page.locator('.bw-pagehead__h1')).toContainText('Your account');
});

test('the member area stylesheet loads, and before the page is drawn @wordpress', async ({ page }) => {
  // A 404 on the stylesheet leaves a page that renders but looks like nothing.
  // It also has to be asked for in the head: queued any later, the member
  // watches the page render bare and then snap into shape.
  const responses = [];
  page.on('response', (r) => responses.push(r));
  await page.goto(DASHBOARD);

  const sheet = responses.find((r) => r.url().includes('/assets/bw/bw.css'));
  expect(sheet, 'the vendored stylesheet was never requested').toBeTruthy();
  expect(sheet.status()).toBe(200);
  await expect(page.locator('head link[href*="/assets/bw/bw.css"]')).toHaveCount(1);
});

test('a club with no shop and no bookings is offered no dead nav items @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-secnav__item')).toHaveCount(1);
  // Built on the page's own address, not a bare query that would replace it.
  await expect(page.locator('.bw-secnav__item')).toHaveAttribute(
    'href',
    /member-area-fixture\/\?view=dashboard$/,
  );
});

test('an address for a view this club does not have lands on the dashboard @wordpress', async ({ page }) => {
  // A bookmark kept from before a plugin was removed, or a typed address.
  await page.goto(`${DASHBOARD}?view=orders`);
  await expect(page.locator('.bw-pagehead__h1')).toContainText('Your account');
  await expect(page.locator('.bw-empty')).toHaveCount(0);
});

test('junk in the address does not break the page @wordpress', async ({ page }) => {
  const response = await page.goto(`${DASHBOARD}?view=%3Cscript%3Ealert(1)%3C/script%3E`);
  expect(response.status()).toBe(200);
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
  await expect(page.locator('.bw-pagehead__h1')).toContainText('Your account');
});

test('the welcome pack greets a member at the top of the overview @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  const pack = page.locator('.clubhouse-welcome');
  await expect(pack).toHaveCount(1);
  await expect(pack.getByRole('heading', { name: 'Welcome to the club' })).toBeVisible();
});

test('there is a way back to the club site @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page.getByRole('link', { name: 'Back to the club site' }).first()).toBeVisible();
});

test('a signed-in member is offered the way out @wordpress', async ({ page }) => {
  // The club's own header and footer are kept off this page, so without this
  // there is no sign-out control anywhere in the member area.
  await page.goto(DASHBOARD);
  await expect(page.getByRole('link', { name: 'Sign out' }).first()).toBeVisible();
});

test('a signed-out visitor keeps the page own content @wordpress', async ({ page, context }) => {
  // On a real club that content is SureCart's sign-in form. Our frame would
  // tell them the club had set nothing up, which is not true and not their
  // problem.
  await context.clearCookies();
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(0);
});
