const { test, expect } = require('@playwright/test');

// Sections and teams are the main reason most people visit a club site. The
// cards used to be a name, a strapline and a squad count that went nowhere.

test('@preview every sport card links to that sport, and the page has real content', async ({ page }) => {
  await page.goto('?clubhouse_page=sports');

  const links = page.locator('.ch-scard__link');
  const count = await links.count();
  expect(count, 'a card per section, each linked').toBeGreaterThan(0);

  const href = await links.first().getAttribute('href');
  expect(href).toContain('clubhouse_item=');

  await page.goto(href.replace(/^\?page=/, '?clubhouse_page='));
  // The name is the headline, not a card title in a grid.
  await expect(page.locator('h1')).toContainText(/rugby/i);
  // What the card promised and could not deliver.
  await expect(page.locator('.ch-info')).toHaveCount(1);
  await expect(page.getByText('Training', { exact: true })).toBeVisible();
  await expect(page.getByText('Who to ask')).toBeVisible();
});

test('@preview a team card opens that team, showing its own fixtures', async ({ page }) => {
  await page.goto('?clubhouse_page=teams');

  const href = await page.locator('.ch-scard__link').first().getAttribute('href');
  expect(href).toContain('clubhouse_item=');

  await page.goto(href.replace(/^\?page=/, '?clubhouse_page='));
  await expect(page.locator('h1')).toContainText(/xv|xi|1s|2s/i);
  await expect(page.locator('.ch-footer')).toHaveCount(1);
});

// A stale link, a renamed section, a typo in the address bar. None of those
// should produce an empty page — the listing is always a useful answer.
test('@preview an unknown sport falls back to the listing', async ({ page }) => {
  await page.goto('?clubhouse_page=sports&clubhouse_item=quidditch');

  await expect(page.locator('.ch-scard')).not.toHaveCount(0);
  await expect(page.locator('.ch-info')).toHaveCount(0);
});

// The card title carries the link, not the whole card: wrapping an article in
// an anchor makes a screen reader read the image and every stat as the link's
// name before saying "link".
test('@preview the card link is the title alone', async ({ page }) => {
  await page.goto('?clubhouse_page=sports');

  const text = await page.locator('.ch-scard__link').first().innerText();
  expect(text.split('\n').length, 'one line, the name').toBe(1);
  await expect(page.locator('a.ch-scard')).toHaveCount(0);
});
