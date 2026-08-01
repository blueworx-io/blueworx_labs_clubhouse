const { test, expect } = require('@playwright/test');

// The secondary colour has to reach the browser, not just the token map. These
// check the two things PHP cannot: that the custom properties actually resolve
// on a rendered page, and that a real element is painted with them.

test('the secondary tokens resolve on the front end', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');

  const tokens = await page.evaluate(() => {
    const s = getComputedStyle(document.documentElement);
    const names = ['', '-ink', '-deep', '-wash', '-block', '-hover', '-active', '-disabled'];
    const out = {};
    names.forEach((n) => { out[n] = s.getPropertyValue('--color-secondary' + n).trim(); });
    return out;
  });

  for (const [name, value] of Object.entries(tokens)) {
    expect(value, `--color-secondary${name} is emitted`).toMatch(/^#[0-9a-f]{6}$/i);
  }

  // A derived default is a genuinely different colour from the primary, not a
  // silent copy of it.
  const accent = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()
  );
  expect(tokens[''].toLowerCase()).not.toBe(accent.toLowerCase());
});

test('the secondary action button is painted in the secondary colour', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');

  const ghost = page.locator('.ch-btn--ghost').first();
  await expect(ghost).toBeVisible();

  const paint = await ghost.evaluate((el) => {
    const s = getComputedStyle(el);
    const root = getComputedStyle(document.documentElement);
    // Resolve the token to the same rgb() form the computed style reports, so the
    // comparison is like-for-like rather than hex-vs-rgb.
    const probe = document.createElement('span');
    probe.style.color = root.getPropertyValue('--color-secondary-deep').trim();
    document.body.appendChild(probe);
    const expected = getComputedStyle(probe).color;
    probe.remove();
    return { color: s.color, border: s.borderTopColor, expected };
  });

  expect(paint.color).toBe(paint.expected);
  expect(paint.border).toBe(paint.expected);
});
