const { test, expect } = require('@playwright/test');

// @wordpress only: the frame goes on whichever post SureCart records as its
// checkout, which the DB-free preview has none of.
//
// The harness has no SureCart, so checkout-fixture (seeded by
// tests/global-setup.js, with the checkout page option pointed at it) stands in
// for it. What these assert is our own frame and the shop's content surviving
// it — never SureCart's fields, which would be testing SureCart.
const CHECKOUT = '/checkout-fixture/';

test('the checkout wears its own frame @wordpress', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-admin.clubhouse-checkout')).toHaveCount(1);
  await expect(page.locator('.clubhouse-checkout__head')).toBeVisible();
  await expect(page.locator('.clubhouse-checkout__foot')).toBeVisible();
});

test("the shop's own content is passed through untouched @wordpress", async ({ page }) => {
  // The frame is chrome. The moment it starts rewriting what SureCart rendered,
  // a SureCart update breaks the form silently.
  await page.goto(CHECKOUT);
  await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
});

test('a buyer is offered no nav to wander off into @wordpress', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-secnav')).toHaveCount(0);
  await expect(page.locator('.clubhouse-member__tabbar')).toHaveCount(0);
});

test('the page has exactly one heading at the top level @wordpress', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('h1')).toHaveCount(1);
});

test('the field theme is asked for in the head, not the footer @wordpress', async ({ page }) => {
  // Queued any later and the buyer watches a payment form render bare and then
  // snap into shape — the worst page on the site for that.
  await page.goto(CHECKOUT);
  await expect(
    page.locator('head link[rel="stylesheet"][href*="surecart.css"]')
  ).toHaveCount(1);
});

test('the footer stacks into full-width targets on a phone @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(CHECKOUT);
  const back = page.locator('.clubhouse-checkout__back');
  await expect(back).toBeVisible();
  const box = await back.boundingBox();
  expect(box.height).toBeGreaterThanOrEqual(44);
});
