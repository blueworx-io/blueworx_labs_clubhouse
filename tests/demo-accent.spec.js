const { test, expect } = require('@playwright/test');

// The real Demo mode bar, mounted in the DB-free preview via ?demo=1.
// Court Side is the preview default; its derived tokens are the engine's own:
// Berry -> accent #c2337a, block #4e2235. Signal Orange -> block #602e1b.
const BERRY_BLOCK = 'rgb(78, 34, 53)';

const rootToken = (page, name) =>
  page.evaluate((n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(), name);

test('demo bar renders five swatches, painted from the palettes', async ({ page }) => {
  await page.goto('?demo=1');
  const swatches = page.locator('.clubhouse-demo__swatch');
  await expect(swatches).toHaveCount(5);
  // demo.js paints them; the server markup carries no colour.
  const bg = await swatches.first().evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(bg).toBe('rgb(198, 242, 78)'); // Volt Lime #c6f24e
});

test('clicking a swatch recolours the page live, with no reload', async ({ page }) => {
  await page.goto('?demo=1');
  // The swatch is a <button type="button">, so this is not about default navigation —
  // it proves the accent branch never calls location.reload() the way the look branch does.
  await page.evaluate(() => { window.__stillHere = true; });
  await page.locator('[data-clubhouse-accent="berry"]').click();
  expect(await page.evaluate(() => window.__stillHere), 'page must not reload').toBe(true);
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a');
  const ticker = await page.locator('.ch-ticker').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(ticker).toBe(BERRY_BLOCK);
});

test('the accent survives navigation via the cookie', async ({ page }) => {
  await page.goto('?demo=1');
  await page.locator('[data-clubhouse-accent="berry"]').click();
  await page.goto('?demo=1&page=about');
  // Re-applied by the head script, before paint.
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a');
});

test('the same swatch re-derives for a different look', async ({ page }) => {
  await page.goto('?demo=1');
  await page.locator('[data-clubhouse-accent="berry"]').click();
  const light = await rootToken(page, '--color-accent-block');
  await page.goto('?demo=1&look=floodlight');
  const dark = await rootToken(page, '--color-accent-block');
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a'); // same swatch
  expect(dark).not.toBe(light); // re-derived for the dark shell
});

test('no demo bar without ?demo=1', async ({ page }) => {
  await page.goto('?page=home');
  await expect(page.locator('.clubhouse-demo')).toHaveCount(0);
  await expect(page.locator('.clubhouse-demo__swatch')).toHaveCount(0);
});
