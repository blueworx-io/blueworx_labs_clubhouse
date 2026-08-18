const { test, expect } = require('@playwright/test');

// The seeded tier prices are a preview-only address: on real WordPress the
// tiers are whatever the club has typed, and a fresh install has no annual
// price to switch to. What must hold anywhere is the last test — a grid with
// nothing to switch to offers no switch.

test('@preview the tier grid starts on monthly and switches to annual', async ({ page }) => {
  await page.goto('?clubhouse_page=membership&clubhouse_tiers=cadence');

  const monthly = page.locator('.ch-tier__price--monthly').first();
  const annual = page.locator('.ch-tier__price--annual').first();
  await expect(monthly).toBeVisible();
  await expect(annual).toBeHidden();

  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(annual).toBeVisible();
  await expect(monthly).toBeHidden();
});

test('@preview switching does not reload the page', async ({ page }) => {
  await page.goto('?clubhouse_page=membership&clubhouse_tiers=cadence');
  await page.evaluate(() => { window.__stayed = true; });

  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(page.locator('.ch-tier__price--annual').first()).toBeVisible();
  expect(await page.evaluate(() => window.__stayed)).toBe(true);
});

test('@preview a tier with no annual price says so instead of disappearing', async ({ page }) => {
  await page.goto('?clubhouse_page=membership&clubhouse_tiers=cadence');

  const cards = await page.locator('.ch-tier').count();
  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(page.locator('.ch-tier')).toHaveCount(cards);
  await expect(page.getByText('Monthly only').first()).toBeVisible();
});

test('@preview Home carries the same switch', async ({ page }) => {
  await page.goto('?clubhouse_page=home&clubhouse_tiers=cadence');

  await expect(page.getByRole('button', { name: 'Annual' })).toBeVisible();
  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(page.locator('.ch-tier__price--annual').first()).toBeVisible();
  // Home sells nothing: both cadences lead to the Membership page.
  const hrefs = await page.locator('.ch-tier__cta').evaluateAll((as) => as.map((a) => a.getAttribute('href')));
  expect(hrefs.every((h) => h && h.includes('membership'))).toBe(true);
});

test('a grid with nothing to switch to offers no switch', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');
  await expect(page.locator('.ch-cadence')).toHaveCount(0);
});
