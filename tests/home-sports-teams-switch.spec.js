const { test, expect } = require('@playwright/test');

// Home's card grid now carries a Sports/Teams switch: one section, two
// collections, chosen by the reader rather than fixed by the page.
test('home card grid switches between Sports and Teams', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const group = page.locator('.ch-tabs').filter({ has: page.getByRole('tab', { name: 'Teams' }) });
  const sports = group.locator('[data-ch-tab="sports"]');
  const teams = group.locator('[data-ch-tab="teams"]');

  await expect(sports).toBeVisible();
  await expect(teams).toBeHidden();
  await expect(group.getByRole('tab', { name: 'Sports' })).toHaveClass(/ch-tabs__btn--on/);

  await page.evaluate(() => { window.__chNavMarker = 'survived'; });
  await group.getByRole('tab', { name: 'Teams' }).click();

  await expect(teams).toBeVisible();
  await expect(sports).toBeHidden();
  await expect(group.getByRole('tab', { name: 'Teams' })).toHaveClass(/ch-tabs__btn--on/);
  // The ARIA state must move with the class, or a screen reader is told the old
  // tab is still the selected one.
  await expect(group.getByRole('tab', { name: 'Teams' })).toHaveAttribute('aria-selected', 'true');
  await expect(group.getByRole('tab', { name: 'Sports' })).toHaveAttribute('aria-selected', 'false');
  // Client-side: no navigation, so nothing above the section moves.
  expect(await page.evaluate(() => window.__chNavMarker)).toBe('survived');
});

// Arrow keys are what a tablist is expected to answer to; without them the roving
// tabindex would strand a keyboard user on the first tab.
test('arrow keys move between tabs', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const group = page.locator('.ch-tabs').filter({ has: page.getByRole('tab', { name: 'Teams' }) });
  const sports = group.getByRole('tab', { name: 'Sports' });
  const teams = group.getByRole('tab', { name: 'Teams' });

  await sports.focus();
  await page.keyboard.press('ArrowRight');

  await expect(teams).toBeFocused();
  await expect(teams).toHaveAttribute('aria-selected', 'true');
  await expect(group.locator('[data-ch-tab="teams"]')).toBeVisible();

  // And back again, so the wrap-around works in both directions.
  await page.keyboard.press('ArrowLeft');
  await expect(sports).toBeFocused();
  await expect(sports).toHaveAttribute('aria-selected', 'true');
});

// Each panel carries its own "see them all" link — the Sports panel points at
// Sports, the Teams panel at Teams. Under the old single-grid section there was
// one link in the section head, which would have been wrong for half the switch.
test('each panel links to its own collection page', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const group = page.locator('.ch-tabs').filter({ has: page.getByRole('tab', { name: 'Teams' }) });
  await expect(group.locator('[data-ch-tab="sports"] .ch-cards__all')).toHaveAttribute('href', /sports/);
  await expect(group.locator('[data-ch-tab="teams"] .ch-cards__all')).toHaveAttribute('href', /teams/);
});

// Regression guard for the bug this change would otherwise have shipped: the tab
// script used to bind `document.querySelector("[data-ch-tabs]")` — the FIRST group
// only. Home now has two, so the second would have been dead on arrival.
test('both tab groups on home are live, not just the first', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const groups = page.locator('[data-ch-tabs]');
  await expect(groups).toHaveCount(2);

  for (let i = 0; i < 2; i++) {
    const group = groups.nth(i);
    const buttons = group.locator('[data-ch-tabbtn]');
    const second = buttons.nth(1);
    const key = await second.getAttribute('data-ch-tabbtn');

    await second.click();
    await expect(second).toHaveClass(/ch-tabs__btn--on/);
    await expect(group.locator(`[data-ch-tab="${key}"]`)).toBeVisible();
  }
});

// Panels hide via a class, not the `hidden` attribute, so a page that failed to
// load its stylesheet shows every panel's content rather than none of it.
test('panels are hidden by class so a styleless page still shows content', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  const off = page.locator('.ch-tabs__panel--off').first();
  await expect(off).toHaveCount(1);
  await expect(off).not.toHaveAttribute('hidden', /.*/);
});
