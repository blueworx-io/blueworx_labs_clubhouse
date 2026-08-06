const { test, expect } = require('@playwright/test');

// The head tags only exist under WordPress — the DB-free preview renders the
// page body, not the document head the plugin decorates — so these are tagged
// @wordpress and dropped when the run targets the preview.

const content = (page, selector) => page.locator(selector).getAttribute('content');

test('@wordpress home page describes itself to search engines', async ({ page }) => {
  await page.goto('/');

  await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
  const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
  expect(canonical).toMatch(/^https?:\/\//);

  await expect(page.locator('meta[name="description"]')).toHaveCount(1);
  expect((await content(page, 'meta[name="description"]')).length).toBeGreaterThan(0);
});

test('@wordpress a shared page carries a complete Open Graph card', async ({ page }) => {
  await page.goto('/membership/');

  for (const prop of ['og:title', 'og:description', 'og:type', 'og:url', 'og:site_name']) {
    await expect(page.locator(`meta[property="${prop}"]`), prop).toHaveCount(1);
  }
  expect(await content(page, 'meta[property="og:type"]')).toBe('website');
  // Twitter mirrors Open Graph rather than contradicting it.
  expect(await content(page, 'meta[name="twitter:title"]')).toBe(
    await content(page, 'meta[property="og:title"]'),
  );
});

test('@wordpress no tag is emitted twice', async ({ page }) => {
  await page.goto('/about/');
  for (const prop of ['og:title', 'og:url', 'og:image']) {
    expect(await page.locator(`meta[property="${prop}"]`).count(), prop).toBeLessThanOrEqual(1);
  }
});

// Every filter produced a crawlable address (/sports/?clubhouse_filter=hockey)
// carrying the same title and canonical as the unfiltered page. A filtered view
// is the same page with some of it hidden, so it is not indexed in its own
// right — but its links are still followed.
// WordPress itself may emit a robots tag (the "discourage search engines"
// setting does, and the test harness has it on), so these assert on OUR exact
// value rather than on "a robots tag exists".
const OURS = 'meta[name="robots"][content="noindex, follow"]';

test('a filtered view is not indexed in its own right @wordpress', async ({ page }) => {
  await page.goto('/sports/?clubhouse_filter=hockey');

  await expect(page.locator(OURS)).toHaveCount(1);
  // Still points at the page it is a view of, so its value consolidates there.
  await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href', /\/sports\/$/);
});

test('an unfiltered listing is not marked noindex by us @wordpress', async ({ page }) => {
  await page.goto('/sports/');

  await expect(page.locator(OURS)).toHaveCount(0);
});

// A sport page canonicalising to /sports/ would tell search engines it is a
// duplicate of the list and to drop it — the opposite of what it exists for.
test('a sport page claims its own address and its own title @wordpress', async ({ page }) => {
  await page.goto('/sports/rugby/');

  await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href', /\/sports\/rugby\/$/);
  await expect(page).toHaveTitle(/^Rugby/);
});

test('a team page claims its own address and its own title @wordpress', async ({ page }) => {
  await page.goto('/teams/1st-xv/');

  await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href', /\/teams\/1st-xv\/$/);
  await expect(page).toHaveTitle(/^1st XV/);
});
