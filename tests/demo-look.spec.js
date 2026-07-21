const { test, expect } = require('@playwright/test');

// The demo bar's look controls, mounted in the DB-free preview via ?demo=1.
//
// SCOPE — read before extending this file. These tests prove the CLIENT contract
// only: the button writes the look cookie and asks for a reload. They deliberately
// do NOT prove the server then renders that look, because the harness cannot show
// it: preview/index.php resolves the look from the ?look= query param, not from
// clubhouse_demo_look, so a reload here re-renders the same look regardless of the
// cookie. Teaching the preview to read the cookie would deepen the very
// preview/production divergence that already hid one bug on this feature
// (the preview calls set_active(); production never does).
//
// The server half is covered where it actually lives, without a browser:
// DemoControllerTest::test_look_slug_uses_cookie_only_when_on and
// ::test_head_script_derives_for_the_viewers_demo_look_not_the_saved_look.

// location.reload cannot be redefined in Chromium, so rather than stub it we mark
// the live document and let a real reload wipe the mark. That proves the reload
// actually happened rather than that a stub was called.
const mark = (page) => page.evaluate(() => { window.__mark = true; });
const survived = (page) => page.evaluate(() => window.__mark === true);

const cookie = async (page, name) => {
  const all = await page.context().cookies();
  const hit = all.find((c) => c.name === name);
  return hit ? hit.value : null;
};

test('the demo bar renders one look control per registered look', async ({ page }) => {
  await page.goto('?demo=1');
  await expect(page.locator('[data-clubhouse-look]')).toHaveCount(3);
  await expect(page.locator('[data-clubhouse-look="floodlight"]')).toBeVisible();
});

test('the current look is the only one flagged @preview', async ({ page }) => {
  await page.goto('?demo=1&look=floodlight');
  await expect(page.locator('[data-clubhouse-look="floodlight"]')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('[data-clubhouse-look][aria-pressed="true"]')).toHaveCount(1);
  await expect(page.locator('[data-clubhouse-look="court-side"]')).toHaveAttribute('aria-pressed', 'false');
});

test('clicking a look writes the cookie and reloads', async ({ page }) => {
  await page.goto('?demo=1');
  await mark(page);

  const navigated = page.waitForEvent('framenavigated');
  await page.locator('[data-clubhouse-look="floodlight"]').click();
  await navigated;

  expect(await cookie(page, 'clubhouse_demo_look')).toBe('floodlight');
  // The look branch reloads so the SERVER re-renders the shell — unlike the accent
  // branch, which applies live and must never reload. That difference is the point.
  expect(await survived(page), 'the look switch must reload the page').toBe(false);
});

test('the look and accent branches do not cross-talk', async ({ page }) => {
  await page.goto('?demo=1');
  await mark(page);
  await page.locator('[data-clubhouse-accent="berry"]').click();

  expect(await survived(page), 'an accent click must not reload').toBe(true);
  expect(await cookie(page, 'clubhouse_demo_look'), 'an accent click must not touch the look cookie').toBeNull();
  expect(await cookie(page, 'clubhouse_demo_accent')).toBe('berry');
});
