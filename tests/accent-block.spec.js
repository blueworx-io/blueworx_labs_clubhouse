const { test, expect } = require('@playwright/test');

// The backbone blocks carry the club's tint, derived by the real colour engine.
// Court Side is the preview default; its Volt Lime accent resolves to #4f5c28
// (rgb(79, 92, 40)) — the same anchor the PHP engine test pins.
const TINT = 'rgb(79, 92, 40)';
const INK = 'rgb(28, 27, 24)';

test('the banner, home hero and ticker carry the club tint, not flat ink', async ({ page }) => {
  await page.goto('?page=home');
  for (const sel of ['.ch-banner', '.ch-home-hero__bg', '.ch-ticker']) {
    const bg = await page.locator(sel).evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(bg, `${sel} background`).toBe(TINT);
  }
});

// The scrim is built with color-mix(in oklab, ...), which Chrome serialises as
// `linear-gradient(oklab(0.222095 ...  / 0.26), ...)` — NOT rgb — so do not assert
// on a hex or rgb triple here. Asserting that the accent cannot move it expresses
// the actual intent (photos must not be colour-cast) and survives any serialisation.
test('the hero scrim stays neutral so club photos are not colour-cast', async ({ page }) => {
  await page.goto('?page=home');
  const scrim = page.locator('.ch-home-hero__scrim');
  const before = await scrim.evaluate((el) => getComputedStyle(el).backgroundImage);
  await page.locator('.ch-switcher button').nth(1).click();
  const after = await scrim.evaluate((el) => getComputedStyle(el).backgroundImage);
  expect(after, 'the scrim must not follow the club accent').toBe(before);
});

test('switching the accent re-themes the backbone through the real engine', async ({ page }) => {
  await page.goto('?page=home');
  const before = await page.locator('.ch-ticker').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(before).toBe(TINT);
  // Signal Orange is the 2nd swatch; Court Side + #ff5b23 derives to #602e1b.
  await page.locator('.ch-switcher button').nth(1).click();
  const after = await page.locator('.ch-ticker').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(after).toBe('rgb(96, 46, 27)');
  expect(after).not.toBe(INK);
});
