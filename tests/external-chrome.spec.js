const { test, expect } = require('@playwright/test');

// @wordpress only: this is about a page WordPress serves that the plugin does
// NOT own — SureCart's customer dashboard is the real one. The preview harness
// can only render clubhouse pages, so it has nothing to say here.
//
// The tag has to be on each TEST TITLE, not only in this comment: the config
// selects specs with Playwright's grep, which matches titles. Stating it here
// alone is what left these five running against the preview — where the fixture
// does not exist — and failing on every local run.
//
// The last test in this file is deliberately untagged. It checks that a
// clubhouse page is NOT wrapped, which the preview can answer perfectly well.
//
// The fixture is seeded by tests/global-setup.js: an ordinary page carrying the
// page template slug the detection keys off ('…surecart…'). See the comment
// there for why the fixture is a template slug rather than SureCart itself.
//
// Navigated by its pretty permalink, not ?pagename=: the harness sets
// /%postname%/, so the query form is a redirect, and a redirect on the first
// request of the run raced the single-threaded PHP server into an aborted
// navigation.
const FIXTURE = '/external-chrome-fixture/';

test('a page another plugin owns gets the club header and footer @wordpress', async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('header.ch-nav')).toBeVisible();
  await expect(page.locator('.ch-footer')).toBeVisible();
});

test("the other plugin's content is kept, inside the content well @wordpress", async ({ page }) => {
  await page.goto(FIXTURE);

  const foreign = page.locator('#foreign-content');
  await expect(foreign).toHaveText('FOREIGN CONTENT');
  // Between the two halves of the chrome, not swallowed by either.
  await expect(page.locator('.ch-external .ch-external__in #foreign-content')).toHaveCount(1);
});

test('the look stylesheet loads so the page is in the club typeface @wordpress', async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('link#clubhouse-base-css')).toHaveCount(1);
  await expect(page.locator('link#clubhouse-look-css')).toHaveCount(1);

  // The derived :root variables are what make the chrome the club's colours.
  const accent = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()
  );
  expect(accent).not.toBe('');
});

// Our scroll reveal adds a hidden-until-observed class to the children of
// .ch-main. Applied to a foreign page it would hide that plugin's UI — a
// checkout or a dashboard — behind an animation it never asked for.
test('the scroll reveal is not loaded, and nothing is hidden by us @wordpress', async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('script#clubhouse-reveal-js')).toHaveCount(0);
  await expect(page.locator('.ch-reveal')).toHaveCount(0);
  await expect(page.locator('#foreign-content')).toBeVisible();
});

test('the chrome is emitted once, not once per nested body match @wordpress', async ({ page }) => {
  await page.goto(FIXTURE);

  await expect(page.locator('header.ch-nav')).toHaveCount(1);
  await expect(page.locator('.ch-external')).toHaveCount(1);
});

// A clubhouse page renders its own complete document. If the wrapper ever
// stopped excluding them, every clubhouse page would grow a second header.
test('a clubhouse page is not double-wrapped', async ({ page }) => {
  await page.goto('?clubhouse_page=about');

  await expect(page.locator('header.ch-nav')).toHaveCount(1);
  await expect(page.locator('.ch-external')).toHaveCount(0);
});
