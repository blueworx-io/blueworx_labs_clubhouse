const { test, expect } = require('@playwright/test');

// The scroll reveal hides nine of the ten blocks on the home page and hands
// them back as you scroll. Nothing else on the site hides content, so every way
// the reveal can fail is a way the page can end up blank. Two guarantees:
//
//   - it hides nothing unless it is running (so a script that never runs, or a
//     visitor with JavaScript off, sees everything); and
//   - once running, it always hands the content back, even if the observer it
//     depends on never reports.

const HIDDEN_COUNT = () =>
  [...document.querySelectorAll('.ch-main > *')]
    .filter((el) => parseFloat(getComputedStyle(el).opacity) < 0.1).length;

test('@preview content is visible when the reveal never runs', async ({ page }) => {
  // Matches JavaScript being off, blocked, or failing before it starts.
  await page.addInitScript(() => {
    delete window.IntersectionObserver;
  });
  await page.goto('?clubhouse_page=home');
  expect(await page.evaluate(HIDDEN_COUNT)).toBe(0);
});

test('@preview a broken observer still gives the content back', async ({ page }) => {
  // An observer that constructs and accepts observe() but never calls back —
  // the failure the hidden-then-throw path cannot recover from on its own.
  await page.addInitScript(() => {
    window.IntersectionObserver = function () {
      return { observe() {}, unobserve() {}, disconnect() {} };
    };
  });
  await page.goto('?clubhouse_page=home');
  expect(await page.evaluate(HIDDEN_COUNT), 'hidden while the watchdog waits').toBeGreaterThan(0);

  await page.waitForFunction(
    () => [...document.querySelectorAll('.ch-main > *')]
      .every((el) => parseFloat(getComputedStyle(el).opacity) >= 0.1),
    null,
    { timeout: 6000 }
  );
});
