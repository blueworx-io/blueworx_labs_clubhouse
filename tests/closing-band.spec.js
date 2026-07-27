const { test, expect } = require('@playwright/test');

// The page's closing band: socials and the find-us details in ONE light section
// seated on the footer. They used to be two stacked sections — a light social
// band above a dark info strip — which read as two endings.
test('home closes with a single band that seats on the footer', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const band = page.locator('.ch-social');
  await expect(band).toHaveCount(1);
  await expect(band.locator('.ch-social__links')).toBeVisible();
  await expect(band.locator('.ch-social__cols')).toBeVisible();
  await expect(page.locator('.ch-info')).toHaveCount(0);

  // Flush: the footer's top edge meets the end of <main>, no gap. Measured off
  // <main> rather than the band itself, whose box is offset while the .ch-reveal
  // entrance transform is still in flight.
  const mainBox = await page.locator('#ch-main').boundingBox();
  const footBox = await page.locator('.ch-footer').boundingBox();
  expect(Math.abs(footBox.y - (mainBox.y + mainBox.height))).toBeLessThan(2);
});

// The footer socials are icon-only buttons — the network name ships in the
// aria-label, so nothing is lost to assistive tech.
test('footer socials render as labelled icon buttons', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const icons = page.locator('.ch-footer__socials .ch-social__link--icon');
  await expect(icons).toHaveCount(3);
  await expect(icons.first()).toHaveAttribute('aria-label', 'Follow us on Facebook');
  await expect(page.locator('.ch-footer__socials .ch-social__label')).toHaveCount(0);

  const box = await icons.first().boundingBox();
  expect(box.width).toBeCloseTo(box.height, 0);
  expect(box.width).toBeGreaterThanOrEqual(44); // Keeps the 44px touch target.
});

// Narrow screens stretch the labelled pills to fill the row; the fixed-size icon
// buttons must not be caught by that rule.
test('footer icon buttons stay round on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('?clubhouse_page=home');

  const box = await page.locator('.ch-footer__socials .ch-social__link--icon').first().boundingBox();
  expect(box.width).toBeCloseTo(box.height, 0);
});
