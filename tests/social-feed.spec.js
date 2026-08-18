const { test, expect } = require('@playwright/test');

// The seeded states are a preview-only address: on real WordPress the feed is
// whatever the club has switched on and pasted in, which on a fresh install is
// nothing. What must hold on any site — the section is off until a club opts in
// — is the @wordpress spec below.

test('the social feed is off until a club switches it on', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('.ch-feed')).toHaveCount(0);
});

test('@preview a switched-on feed shows the pasted posts', async ({ page }) => {
  await page.goto('?clubhouse_page=home&clubhouse_social=demo');

  const cards = page.locator('.ch-feed__card');
  await expect(cards).toHaveCount(3);
  await expect(cards.first()).toHaveAttribute('href', /^https?:\/\//);
  await expect(page.locator('.ch-feed__caption').first()).not.toBeEmpty();
});

test('@preview a switched-on feed with nothing pasted renders nothing at all', async ({ page }) => {
  await page.goto('?clubhouse_page=home&clubhouse_social=empty');

  await expect(page.locator('.ch-feed')).toHaveCount(0);
  await expect(page.getByText('Latest from the club')).toHaveCount(0);
});
