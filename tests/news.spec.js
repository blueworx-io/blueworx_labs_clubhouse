const { test, expect } = require('@playwright/test');

// Club news, end to end. Portable: the DB-free preview serves both screens from
// the demo post source, and WordPress serves them from real posts.

test('the news index leads with a story and lists the rest', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  await expect(page.locator('.ch-newshead')).toBeVisible();
  await expect(page.locator('.ch-featured__card')).toBeVisible();
  await expect(page.locator('.ch-postcard')).toHaveCount(5);
  await expect(page.locator('.ch-newsgrid__count')).toContainText('stories');
});

test('the news index keeps the site header and footer', async ({ page }) => {
  await page.goto('?clubhouse_page=news');
  await expect(page.locator('header.ch-nav')).toBeVisible();
  await expect(page.locator('.ch-footer')).toBeVisible();
});

test('category pills narrow the list', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  await page.getByRole('link', { name: 'Hockey', exact: true }).click();
  await expect(page.locator('.ch-filter--on')).toHaveText('Hockey');
  // Every card left is a hockey one.
  const cats = await page.locator('.ch-postcard__cat').allTextContents();
  expect(cats.every((c) => c.trim().toLowerCase() === 'hockey')).toBe(true);
});

test('the pager reaches the second page', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  await page.locator('.ch-pager__step--next').click();
  await expect(page.locator('.ch-pager__no--on')).toHaveText('2');
  // The lead story is a first-page-only treatment.
  await expect(page.locator('.ch-featured__card')).toHaveCount(0);
});

test('an article renders headline, body and the way back', async ({ page }) => {
  await page.goto('?clubhouse_page=post');

  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('.ch-posthead__title')).toBeVisible();
  await expect(page.locator('.ch-prose p').first()).toBeVisible();
  await expect(page.locator('.ch-posthead__back')).toBeVisible();
});

test('an article keeps the site header and footer', async ({ page }) => {
  await page.goto('?clubhouse_page=post');
  await expect(page.locator('header.ch-nav')).toBeVisible();
  await expect(page.locator('.ch-footer')).toBeVisible();
});

test('an article offers more to read', async ({ page }) => {
  await page.goto('?clubhouse_page=post');
  await expect(page.locator('.ch-related .ch-postcard')).toHaveCount(3);
});

test('the news pages hold their layout on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });

  for (const slug of ['news', 'post']) {
    await page.goto(`?clubhouse_page=${slug}`);
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    expect(overflow, `${slug} scrolls sideways`).toBeLessThanOrEqual(1);
  }
});
