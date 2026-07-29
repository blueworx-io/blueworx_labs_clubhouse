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
        return {
          background: s.backgroundColor,
          border: s.borderTopColor,
          color: s.color,
          shadow: s.boxShadow,
          // Box geometry — the hover must not resize the tile or shunt its
          // neighbours along, which is what a plain border-width change would do.
          width: Math.round(el.getBoundingClientRect().width),
          height: Math.round(el.getBoundingClientRect().height),
        };
      });

    const before = await read();
    await tile.hover();
    // Border transition is .18s; give it room to land.
    await page.waitForTimeout(400);
    const after = await read();

    expect(after.background, 'hover must not repaint the tile fill').toBe(before.background);
    expect(after.color, 'hover must not repaint the label').toBe(before.color);
    expect(after.border, 'hover must change the border colour').not.toBe(before.border);
    expect(after.shadow, 'hover must add a ring/glow').not.toBe(before.shadow);
    expect(after.shadow, 'hover must add a ring/glow').not.toBe('none');
    // The ring is drawn with an inset shadow precisely so the box does not grow.
    expect(after.width, 'hover must not resize the tile').toBe(before.width);
    expect(after.height, 'hover must not resize the tile').toBe(before.height);

    // "Changed" is not enough. The first attempt at this used --color-accent,
    // which changed the value but landed at ~1.1:1 against the pale tiles on
    // the two light looks — a hover state you cannot see. Measure it.
    const contrast = await tile.evaluate((el) => {
      const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
      const over = (base, top) => {
        ctx.clearRect(0, 0, 1, 1);
        ctx.fillStyle = base;
        ctx.fillRect(0, 0, 1, 1);
        ctx.fillStyle = top;
        ctx.fillRect(0, 0, 1, 1);
        const d = ctx.getImageData(0, 0, 1, 1).data;
        return [d[0], d[1], d[2]];
      };
      const s = getComputedStyle(el);
      const heroBg = getComputedStyle(
        document.querySelector('.ch-home-hero__bg') || document.body
      ).backgroundColor;
      // Both the tile fill and the border can be translucent, so composite each
      // onto what is actually behind it before comparing.
      const fill = over(heroBg, s.backgroundColor);
      const border = over(`rgb(${fill.join(',')})`, s.borderTopColor);
      const ch = (v) => {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
      };
      const lum = (c) => 0.2126 * ch(c[0]) + 0.7152 * ch(c[1]) + 0.0722 * ch(c[2]);
      const [l1, l2] = [lum(border), lum(fill)];
      return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    });

    expect(
      contrast,
      `hover border must be visible against the tile (got ${contrast.toFixed(2)}:1)`
    ).toBeGreaterThan(3);
  });
}
