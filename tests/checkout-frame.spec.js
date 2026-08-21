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

test('the checkout owns the whole page, with no theme chrome around it @wordpress', async ({ page }) => {
  // The theme's own footer used to render below the frame's — its 442px under
  // our 48px — and its header above. The frame is a self-contained checkout, so
  // the page is served from the plugin's own template instead.
  await page.goto(CHECKOUT);
  await expect(page.locator('.wp-block-template-part')).toHaveCount(0);
  await expect(page.locator('footer')).toHaveCount(1);
  await expect(page.locator('footer')).toHaveClass(/clubhouse-checkout__foot/);
});

test('nothing renders below the checkout footer @wordpress', async ({ page }) => {
  // The bug this guards is measured, not described: the page used to run on for
  // more than a thousand pixels after the footer ended.
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(CHECKOUT);
  const slack = await page.evaluate(() => {
    const foot = document.querySelector('.clubhouse-checkout__foot').getBoundingClientRect();
    return document.documentElement.scrollHeight - (foot.bottom + window.scrollY);
  });
  expect(slack).toBeLessThanOrEqual(1);
});
