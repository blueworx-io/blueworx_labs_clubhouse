const { test, expect } = require('@playwright/test');

// The head tags only exist under WordPress — the DB-free preview renders the
// page body, not the document head the plugin decorates — so these are tagged
// @wordpress and dropped when the run targets the preview.

const content = (page, selector) => page.locator(selector).getAttribute('content');

test('@wordpress home page describes itself to search engines', async ({ page }) => {
  await page.goto('/');

  await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
  const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
  expect(canonical).toMatch(/^https?:\/\//);

  await expect(page.locator('meta[name="description"]')).toHaveCount(1);
  expect((await content(page, 'meta[name="description"]')).length).toBeGreaterThan(0);
});

test('@wordpress a shared page carries a complete Open Graph card', async ({ page }) => {
  await page.goto('/membership/');

  for (const prop of ['og:title', 'og:description', 'og:type', 'og:url', 'og:site_name']) {
    await expect(page.locator(`meta[property="${prop}"]`), prop).toHaveCount(1);
  }
  expect(await content(page, 'meta[property="og:type"]')).toBe('website');
  // Twitter mirrors Open Graph rather than contradicting it.
  expect(await content(page, 'meta[name="twitter:title"]')).toBe(
    await content(page, 'meta[property="og:title"]'),
  );
});

test('@wordpress no tag is emitted twice', async ({ page }) => {
  await page.goto('/about/');
  for (const prop of ['og:title', 'og:url', 'og:image']) {
    expect(await page.locator(`meta[property="${prop}"]`).count(), prop).toBeLessThanOrEqual(1);
  }
});
