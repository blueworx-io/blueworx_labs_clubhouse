const { test, expect } = require('@playwright/test');

// The full-bleed Home hero (home_hero) replaces the shared hero() on Home and
// folds the quick-links into its foot, so the ticker follows the hero directly.
// Structural assertions only — look-agnostic (markup is identical across looks).

test('home renders the full-bleed hero, not the shared hero', async ({ page }) => {
  const response = await page.goto('?clubhouse_page=home');
  expect(response?.status(), 'HTTP status for home').toBe(200);
  await expect(page).toHaveTitle(/.+/);
  await expect(page.locator('#ch-main')).toBeVisible();
  await expect(page.locator('.ch-home-hero')).toHaveCount(1);
  await expect(page.locator('.ch-hero')).toHaveCount(0);
});

test('home hero contains the four icon quick-links with their labels', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  const tiles = page.locator('.ch-home-hero .ch-home-hero__tile');
  await expect(tiles).toHaveCount(4);
  await expect(tiles.filter({ hasText: 'Join the club' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'Take a tour' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'See fixtures' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'Get in touch' })).toHaveCount(1);
});

test('home no longer emits a separate quick_tiles section', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('.ch-tiles-sec')).toHaveCount(0);
});

test('the ticker immediately follows the home hero', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  const nextTag = await page.evaluate(() => {
    const hero = document.querySelector('.ch-home-hero');
    return hero?.nextElementSibling?.className || '';
  });
  expect(nextTag).toContain('ch-ticker');
});

// Hovering a quick-link used to flip the whole tile to --color-ink, dropping a
// near-black slab into a row of pale tiles. The hover now moves the border and
// leaves the fill alone — Floodlight already worked this way. Asserted for every
// look so a look cannot reintroduce a fill-swap hover.
// @preview — ?look= is a preview affordance.
for (const look of ['court-side', 'members-house', 'floodlight']) {
  test(`hero quick-link hover changes the border, not the fill — ${look} @preview`, async ({ page }) => {
    await page.goto(`?clubhouse_page=home&look=${look}`);
    const tile = page.locator('.ch-home-hero__tile').first();
    await expect(tile).toBeVisible();

    const read = () =>
      tile.evaluate((el) => {
        const s = getComputedStyle(el);
        return { background: s.backgroundColor, border: s.borderTopColor, color: s.color };
      });

    const before = await read();
    await tile.hover();
    // Border transition is .18s; give it room to land.
    await page.waitForTimeout(400);
    const after = await read();

    expect(after.background, 'hover must not repaint the tile fill').toBe(before.background);
    expect(after.color, 'hover must not repaint the label').toBe(before.color);
    expect(after.border, 'hover must change the border colour').not.toBe(before.border);
  });
}
