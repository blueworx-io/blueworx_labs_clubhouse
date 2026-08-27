const { test, expect } = require('@playwright/test');

// Smoke suite for the built Court Side pages, run against the DB-free PHP preview
// booted by the webServer in playwright.config.js. Each page asserts that the
// document renders (non-empty title + the <main> landmark) and that ?clubhouse_page=
// routing resolved to THIS page rather than the Home fallback — proven by a
// marker unique to the page and absent on Home.
const PAGES = [
  { slug: 'home', marker: '.ch-cards' },
  { slug: 'about', marker: '.ch-benefits' },
  { slug: 'membership', marker: '.ch-faq' },
  { slug: 'contact', marker: '.ch-contact' },
];

// Login is not in the list above because it is not a page every site has: it
// belongs to the shop, and a club without one is not served it at all (#261).
// The preview has no shop, but it still draws the page so the design can be
// looked at — so this is the one place the card is smoke-tested.
test('login page renders and routes @preview', async ({ page }) => {
  const response = await page.goto('?clubhouse_page=login');
  expect(response?.status()).toBe(200);
  await expect(page.locator('#ch-main')).toBeVisible();
  // The club's own card. The form inside it is the shop's.
  await expect(page.locator('.ch-auth').first()).toBeVisible();
});

for (const { slug, marker } of PAGES) {
  test(`${slug} page renders and routes`, async ({ page }) => {
    const response = await page.goto(`?clubhouse_page=${slug}`);
    expect(response?.status(), `HTTP status for ${slug}`).toBe(200);

    await expect(page).toHaveTitle(/.+/);
    await expect(page.locator('#ch-main')).toBeVisible();
    await expect(page.locator(marker).first()).toBeVisible();
  });
}

test('sports page lists collection sports', async ({ page }) => {
  await page.goto('?clubhouse_page=sports');
  await expect(page.getByText('Rugby').first()).toBeVisible();
  await expect(page.getByText('Netball').first()).toBeVisible();
});

test('calendar shows month-grouped fixtures from the collection', async ({ page }) => {
  await page.goto('?clubhouse_page=calendar');
  await expect(page.getByText('July').first()).toBeVisible();
  await expect(page.getByText('Won by 34 runs').first()).toBeVisible();
});
