const { test, expect } = require('@playwright/test');

// @wordpress only: this is about the addresses a real WordPress install answers,
// which the DB-free preview has no part in.
//
// Issue #243. The plugin's own rewrite rules are gone — club pages are found the
// way WordPress finds any page. The addresses those rules used to answer are in
// bookmarks, in newsletters and in whatever links to the club from elsewhere, so
// every one of them has to land on the page rather than a 404.

test('@wordpress the old query address lands on the page itself', async ({ page }) => {
  await page.goto('/?clubhouse_page=news');

  await expect(page).toHaveURL(/\/news\/$/);
  await expect(page.locator('header.ch-nav')).toBeVisible();
  await expect(page).toHaveTitle(/^News/);
});

// Home's slug is the empty string; 'home' was always the literal the query
// address used for it.
test('@wordpress the old query address for home lands on the front page', async ({ page }) => {
  await page.goto('/?clubhouse_page=home');

  await expect(page).toHaveURL(/127\.0\.0\.1:\d+\/$/);
  await expect(page.locator('.ch-home-hero')).toBeVisible();
});

// What the old address showed has to survive the move, or a link to a filtered
// view arrives somewhere else.
test('@wordpress a filter on an old address is carried across', async ({ page }) => {
  await page.goto('/?clubhouse_page=teams&clubhouse_filter=rugby');

  await expect(page).toHaveURL(/\/teams\/\?clubhouse_filter=rugby$/);
  await expect(page.locator('.ch-scard')).not.toHaveCount(0);
});

// The one address that never had a page of its own behind it.
test('@wordpress a sport hung off its listing still resolves', async ({ page }) => {
  await page.goto('/sports/rugby/');

  await expect(page).toHaveURL(/\/sports\/\?clubhouse_item=rugby$/);
  await expect(page.locator('h1')).toContainText(/rugby/i);
});

// Only addresses this plugin actually served. A path that merely starts with a
// club page's name was never one of ours, and forwarding it to the listing
// would turn a wrong link into a wrong page instead of an honest 404.
test('@wordpress an address that was never ours still answers 404', async ({ page }) => {
  const response = await page.goto('/news/never-a-page/');

  expect(response.status()).toBe(404);
});
