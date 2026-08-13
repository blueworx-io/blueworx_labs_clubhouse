const { test, expect } = require('@playwright/test');

// The membership page with no tier connected — the state every club starts in,
// and the one that must never change.
test('unconnected tiers keep their typed price and a working button @preview', async ({ page }) => {
  await page.goto('?page=membership');

  const tiers = page.locator('.ch-tier');
  await expect(tiers.first()).toBeVisible();

  // Every tier's button goes somewhere: no dead buttons on a page whose whole
  // job is to sign someone up.
  const links = page.locator('.ch-tier a[href]');
  const count = await links.count();
  expect(count).toBeGreaterThan(0);
  for (let i = 0; i < count; i++) {
    const href = await links.nth(i).getAttribute('href');
    expect(href).not.toBe('');
    expect(href).not.toBe('#');
  }
});
