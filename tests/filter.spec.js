const { test, expect } = require('@playwright/test');

// Slice 2d — the filter pills on Sports/Teams/Events/Calendar filter server-side
// via the ?clubhouse_filter= query param. Portable across both harnesses: the
// query var is the same in the preview and in real WordPress.

test('teams filter narrows the squads and marks the active pill', async ({ page }) => {
  await page.goto('?clubhouse_page=teams&clubhouse_filter=rugby');
  await expect(page.getByText('1st XV').first()).toBeVisible();          // Rugby team
  await expect(page.getByText('1st XI')).toHaveCount(0);                 // Cricket team gone
  await expect(page.locator('.ch-filter--on')).toHaveText('Rugby');
});

test('calendar filter narrows fixtures by the sport prefix', async ({ page }) => {
  await page.goto('?clubhouse_page=calendar&clubhouse_filter=cricket');
  await expect(page.getByText('Cricket · 1st XI').first()).toBeVisible();
  await expect(page.getByText('Riverside RFC')).toHaveCount(0);         // a Rugby fixture
  await expect(page.locator('.ch-filter--on')).toHaveText('Cricket');
});

test('an unknown filter falls back to All', async ({ page }) => {
  await page.goto('?clubhouse_page=teams&clubhouse_filter=kabaddi');
  await expect(page.getByText('1st XV').first()).toBeVisible();
  await expect(page.getByText('1st XI').first()).toBeVisible();
  await expect(page.locator('.ch-filter--on')).toHaveText('All');
});

test('no filter shows everything with All active', async ({ page }) => {
  await page.goto('?clubhouse_page=teams');
  await expect(page.locator('.ch-filter--on')).toHaveText('All');
  await expect(page.locator('.ch-scard')).toHaveCount(4);
});
