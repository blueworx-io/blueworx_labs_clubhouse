const { test, expect } = require('@playwright/test');

// Filter pills used to be plain links: clicking one navigated, so the hero
// re-rendered and the scroll position jumped. They now swap <main> in place.
//
// "No reload" is asserted by planting a value on `window` before the click and
// checking it survives — a navigation wipes it, an in-place swap does not. That
// is the actual property under test; asserting on the URL alone would pass even
// if the page had fully reloaded.
for (const slug of ['sports', 'teams', 'events', 'calendar']) {
  test(`${slug} filters swap the list without reloading`, async ({ page }) => {
    await page.goto(`?clubhouse_page=${slug}`);

    const pills = page.locator('.ch-filter');
    await expect(pills.first()).toBeVisible();
    const target = pills.locator(':scope:not(.ch-filter--on)').first();
    const label = (await target.textContent()).trim();

    await page.evaluate(() => { window.__chNavMarker = 'survived'; });
    await target.click();

    await expect(page.locator('.ch-filter--on')).toHaveText(label);
    expect(page.url()).toContain('clubhouse_filter=');
    expect(await page.evaluate(() => window.__chNavMarker)).toBe('survived');
  });
}

// The active pill is not a destination — clicking it must do nothing at all
// rather than re-fetch the page it is already showing.
test('clicking the active filter is inert', async ({ page }) => {
  await page.goto('?clubhouse_page=sports');

  await page.evaluate(() => { window.__chNavMarker = 'survived'; });
  const before = page.url();
  await page.locator('.ch-filter--on').click();

  expect(page.url()).toBe(before);
  expect(await page.evaluate(() => window.__chNavMarker)).toBe('survived');
});

// The pills stay real links so each filtered view is shareable, bookmarkable and
// crawlable, and so the page still works with JavaScript switched off.
test('filter pills remain real links with the filter in the href', async ({ page }) => {
  await page.goto('?clubhouse_page=sports');

  const inactive = page.locator('.ch-filter:not(.ch-filter--on)').first();
  await expect(inactive).toHaveAttribute('href', /clubhouse_filter=/);
  await expect(page.locator('.ch-filter--on')).toHaveAttribute('aria-current', 'page');
});

// Server-rendered, so the structure below the pills — here the directory grid —
// is the filtered one, not the full list with rows hidden by CSS.
test('the swapped-in list is the filtered one, not a hidden subset', async ({ page }) => {
  await page.goto('?clubhouse_page=sports');

  const allCards = await page.locator('.ch-scard').count();
  await page.locator('.ch-filter:not(.ch-filter--on)').first().click();
  await expect(page.locator('.ch-filter--on')).not.toHaveText('All');

  const filtered = await page.locator('.ch-scard').count();
  expect(filtered).toBeLessThan(allCards);
  expect(await page.locator('.ch-scard').filter({ visible: false }).count()).toBe(0);
});
