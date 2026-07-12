const { test, expect } = require('@playwright/test');

// Smoke suite for the built Court Side pages, run against the DB-free PHP preview
// booted by the webServer in playwright.config.js. Each page asserts that the
// document renders (non-empty title + the <main> landmark) and that ?page=
// routing resolved to THIS page rather than the Home fallback — proven by a
// marker unique to the page and absent on Home.
const PAGES = [
  { slug: 'home', marker: '.ch-cards' },
  { slug: 'about', marker: '.ch-benefits' },
  { slug: 'membership', marker: '.ch-faq' },
  { slug: 'contact', marker: '.ch-contact' },
  { slug: 'login', marker: 'input[type="password"]' },
];

for (const { slug, marker } of PAGES) {
  test(`${slug} page renders and routes`, async ({ page }) => {
    const response = await page.goto(`?page=${slug}`);
    expect(response?.status(), `HTTP status for ${slug}`).toBe(200);

    await expect(page).toHaveTitle(/.+/);
    await expect(page.locator('#ch-main')).toBeVisible();
    await expect(page.locator(marker).first()).toBeVisible();
  });
}
