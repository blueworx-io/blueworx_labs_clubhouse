const { test, expect } = require('@playwright/test');

// Issue #121: the privacy and terms pages 404'd, nothing linked to them, and
// there was no cookie wording anywhere — on a site whose forms collect names,
// email addresses and phone numbers.

test('the privacy policy is a real page and says what is collected', async ({ page }) => {
  await page.goto('?clubhouse_page=privacy');
  await expect(page.locator('h1')).toContainText('your details');
  await expect(page.locator('.ch-prose')).toContainText('What we collect');
  await expect(page.locator('.ch-prose')).toContainText('ico.org.uk');
});

test('the terms page is a real page', async ({ page }) => {
  await page.goto('?clubhouse_page=terms');
  await expect(page.locator('.ch-prose')).toContainText('Membership and payments');
});

test('every page links to both from the footer', async ({ page }) => {
  for (const slug of ['home', 'membership', 'contact']) {
    await page.goto(`?clubhouse_page=${slug}`);
    await expect(page.locator('.ch-footer__legal-link', { hasText: /^Privacy$/ })).toHaveCount(1);
    await expect(page.locator('.ch-footer__legal-link', { hasText: /^Terms$/ })).toHaveCount(1);
  }
});

test('the cookie notice appears once and stays dismissed', async ({ page }) => {
  await page.goto('?clubhouse_page=privacy');
  const notice = page.locator('#ch-cookie');
  await expect(notice).toBeVisible();
  // It must not claim to gate anything — it does not, and cannot without
  // breaking the shop.
  await expect(notice).toContainText('to take payment');

  await page.locator('#ch-cookie-dismiss').click();
  await expect(notice).toBeHidden();

  // Still gone on the next page: the whole point of remembering it.
  await page.goto('?clubhouse_page=contact');
  await expect(page.locator('#ch-cookie')).toBeHidden();
});

test('a hero with no call to action renders no buttons at all', async ({ page }) => {
  // These used to render as two blank pills that reloaded the page.
  await page.goto('?clubhouse_page=privacy');
  const blanks = page.locator('.ch-hero .ch-btn').filter({ hasText: /^\s*$/ });
  await expect(blanks).toHaveCount(0);
});
