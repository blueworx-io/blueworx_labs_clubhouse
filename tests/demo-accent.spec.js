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

test('the same swatch re-derives for a different look @preview', async ({ page }) => {
  await page.goto('?demo=1');
  await page.locator('[data-clubhouse-accent="berry"]').click();
  const light = await rootToken(page, '--color-accent-block');
  await page.goto('?demo=1&look=floodlight');
  const dark = await rootToken(page, '--color-accent-block');
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a'); // same swatch
  expect(dark).not.toBe(light); // re-derived for the dark shell
});

test('the chosen swatch reports itself as selected', async ({ page }) => {
  await page.goto('?demo=1');
  const berry = page.locator('[data-clubhouse-accent="berry"]');
  const lime = page.locator('[data-clubhouse-accent="volt-lime"]');
  // Nothing is chosen until a click: the server ships every swatch unpressed.
  await expect(berry).toHaveAttribute('aria-pressed', 'false');

  await berry.click();
  await expect(berry).toHaveAttribute('aria-pressed', 'true');
  await expect(berry).toHaveClass(/is-current/);
  // Exactly one, or a screen reader hears two accents both claiming to be on.
  await expect(page.locator('[data-clubhouse-accent][aria-pressed="true"]')).toHaveCount(1);
  await expect(lime).toHaveAttribute('aria-pressed', 'false');

  await lime.click();
  await expect(lime).toHaveAttribute('aria-pressed', 'true');
  await expect(berry).toHaveAttribute('aria-pressed', 'false');
  await expect(page.locator('[data-clubhouse-accent][aria-pressed="true"]')).toHaveCount(1);
});

test('the selected swatch survives navigation, like the accent itself', async ({ page }) => {
  await page.goto('?demo=1');
  await page.locator('[data-clubhouse-accent="berry"]').click();
  await page.goto('?demo=1&page=about');
  // The head script republishes the applied slug; demo.js re-flags the swatch once
  // the footer markup exists. Without this the page is berry but no swatch says so.
  await expect(page.locator('[data-clubhouse-accent="berry"]')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('[data-clubhouse-accent][aria-pressed="true"]')).toHaveCount(1);
});

test('a mangled accent cookie is survivable, not fatal', async ({ page }) => {
  await page.goto('?demo=1');
  // A stray '%' makes decodeURIComponent throw; an uncaught URIError would kill the
  // pre-paint apply for every page load until the cookie is cleared.
  await page.context().addCookies([
    { name: 'clubhouse_demo_accent', value: '%E0%A4%A', url: 'http://127.0.0.1:8124' },
  ]);
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto('?demo=1');
  expect(errors).toEqual([]);
  await expect(page.locator('.clubhouse-demo__swatch')).toHaveCount(5);
  await expect(page.locator('[data-clubhouse-accent][aria-pressed="true"]')).toHaveCount(0);
});

test('no demo bar without ?demo=1 @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('.clubhouse-demo')).toHaveCount(0);
  await expect(page.locator('.clubhouse-demo__swatch')).toHaveCount(0);
});
