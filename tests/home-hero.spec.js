const { test, expect } = require('@playwright/test');

// The full-bleed Home hero (home_hero) replaces the shared hero() on Home and
// folds the quick-links into its foot, so the ticker follows the hero directly.
// Structural assertions only — look-agnostic (markup is identical across looks).

test('home renders the full-bleed hero, not the shared hero', async ({ page }) => {
  const response = await page.goto('?page=home');
  expect(response?.status(), 'HTTP status for home').toBe(200);
  await expect(page).toHaveTitle(/.+/);
  await expect(page.locator('#ch-main')).toBeVisible();
  await expect(page.locator('.ch-home-hero')).toHaveCount(1);
  await expect(page.locator('.ch-hero')).toHaveCount(0);
});

test('home hero contains the four icon quick-links with their labels', async ({ page }) => {
  await page.goto('?page=home');
  const tiles = page.locator('.ch-home-hero .ch-home-hero__tile');
  await expect(tiles).toHaveCount(4);
  await expect(tiles.filter({ hasText: 'Join the club' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'Take a tour' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'See fixtures' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'Get in touch' })).toHaveCount(1);
});

test('home no longer emits a separate quick_tiles section', async ({ page }) => {
  await page.goto('?page=home');
  await expect(page.locator('.ch-tiles-sec')).toHaveCount(0);
});

test('the ticker immediately follows the home hero', async ({ page }) => {
  await page.goto('?page=home');
  const nextTag = await page.evaluate(() => {
    const hero = document.querySelector('.ch-home-hero');
    return hero?.nextElementSibling?.className || '';
  });
  expect(nextTag).toContain('ch-ticker');
});
