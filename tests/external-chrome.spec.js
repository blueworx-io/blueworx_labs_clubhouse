const { test, expect } = require('@playwright/test');

// @wordpress only: this is about pages WordPress serves that the plugin does
// NOT render itself. The preview harness only renders clubhouse pages, so it has
// nothing to say about most of this.
//
// The tag has to be on each TEST TITLE, not only in this comment: the config
// selects specs with Playwright's grep, which matches titles. Stating it here
// alone is what left these running against the preview — where the fixture does
// not exist — and failing on every local run.
//
// Two rules are covered, and they pull in opposite directions:
//   - a bad URL or an archive SHOULD get the club chrome, because otherwise it
//     is bare theme output with no way back to the club; but
//   - a SureCart page should NOT, because SureCart ships a complete UI of its
//     own and the club's tokens fought it.
//
// The SureCart fixture is seeded by tests/global-setup.js: an ordinary page
// carrying the page template slug the detection keys off ('…surecart…'). See the
// comment there for why the fixture is a template slug rather than SureCart itself.
//
// Navigated by its pretty permalink, not ?pagename=: the harness sets
// /%postname%/, so the query form is a redirect, and a redirect on the first
// request of the run raced the single-threaded PHP server into an aborted
// navigation.
const FIXTURE = '/external-chrome-fixture/';

// SureCart owns its own look. Wrapping the customer dashboard and checkout in
// the club header, footer and design tokens fought SureCart's styling instead
// of complementing it, so commerce pages are excluded outright and stand alone.
test('a SureCart page gets no club chrome at all @wordpress', async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('header.ch-nav')).toHaveCount(0);
  await expect(page.locator('.ch-footer')).toHaveCount(0);
  await expect(page.locator('.ch-external')).toHaveCount(0);
});

test('a SureCart page loads none of the club stylesheets @wordpress', async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('link#clubhouse-base-css')).toHaveCount(0);
  await expect(page.locator('link#clubhouse-look-css')).toHaveCount(0);

  // No club design tokens on :root either — those are what were re-colouring
  // SureCart's own components.
  const accent = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()
  );
  expect(accent).toBe('');
});

test("the SureCart page's own content is untouched @wordpress", async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('#foreign-content')).toHaveText('FOREIGN CONTENT');
});

// A clubhouse page renders its own complete document. If the wrapper ever
// stopped excluding them, every clubhouse page would grow a second header.
test('a clubhouse page is not double-wrapped', async ({ page }) => {
  await page.goto('?clubhouse_page=about');

  await expect(page.locator('header.ch-nav')).toHaveCount(1);
  await expect(page.locator('.ch-external')).toHaveCount(0);
});

// The pages nobody dressed. Each rendered as bare theme output — blue
// underlined links, serif body text, no header, no footer and no way back to
// the club — and each is a URL a visitor can arrive at from a search result.
//
// A single post is NOT in this list: posts already render through Clubhouse's
// own article template (.ch-main--article), so they were never bare. These are
// the views that fell through: a bad URL, and a category archive.
const UNDRESSED = [
  { what: 'a bad URL', url: '/no-such-page-anywhere/' },
  { what: 'a category archive', url: '/category/uncategorized/' },
];

for (const { what, url } of UNDRESSED) {
  test(`${what} still shows the club's header and footer @wordpress`, async ({ page }) => {
    await page.goto(url);

    await expect(page.locator('header.ch-nav')).toHaveCount(1);
    await expect(page.locator('.ch-footer')).toHaveCount(1);
    // A way back to the club, which is the point of dressing these at all.
    await expect(page.locator('header.ch-nav a').first()).toBeVisible();
  });
}

// Posts route through the article template, not this wrapper. Asserted so a
// future change to the dressing rule cannot quietly start double-wrapping them.
test('a news story uses the article template, not the external wrapper @wordpress', async ({ page }) => {
  await page.goto('/clubhouse-post-fixture/');

  await expect(page.locator('.ch-main--article')).toHaveCount(1);
  await expect(page.locator('.ch-external')).toHaveCount(0);
  await expect(page.locator('header.ch-nav')).toHaveCount(1);
});
