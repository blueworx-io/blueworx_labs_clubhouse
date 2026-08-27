const { test, expect } = require('@playwright/test');

// The login page's design. What a member can actually DO on it is the shop's
// form, so signing in end to end lives in member-sign-in.spec.js, which needs a
// real shop installed.
//
// @preview: the preview has no shop and so no shop pages, but it still draws
// this page so the design can be looked at. On WordPress without a shop the
// page is not served at all — that is the point of issue #261, and
// member-sign-in.spec.js is what proves it.

test('the login page is the club\'s card around the shop\'s form @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=login');

  const card = page.locator('.ch-auth');
  await expect(card).toBeVisible();
  // Ours: the eyebrow, the heading, the lede, and the way through to joining.
  await expect(card.locator('.ch-auth__title')).toBeVisible();
  await expect(card.locator('.ch-auth__lede')).toBeVisible();
  await expect(card.locator('.ch-auth__alt-link')).toBeVisible();
  // Theirs: the form.
  await expect(card.locator('sc-login-form')).toHaveCount(1);
});

test('the shop\'s form is titled by the club @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=login');

  // Left alone it reads "Sign in to your account" — a different product's
  // wording halfway down the club's own page.
  const heading = await page.locator('.ch-auth__title').innerText();
  await expect(page.locator('sc-login-form [slot="title"]')).toHaveText(heading);
});

test('the login page has exactly one h1 and it is the card heading @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=login');

  const h1s = page.locator('#ch-main h1');
  await expect(h1s).toHaveCount(1);
  await expect(h1s.first()).toHaveClass(/ch-auth__title/);
});

// The card is narrow by intent — it is the whole page and reads as a form, not
// a content section. 460px squeezed the shop's form; 560 gives it room without
// letting the card sprawl.
test('the sign-in card stays narrow @preview', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('?clubhouse_page=login');

  const box = await page.locator('.ch-auth-wrap').boundingBox();
  expect(box?.width).toBe(560);
});
