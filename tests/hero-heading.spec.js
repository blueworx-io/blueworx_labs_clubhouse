const { test, expect } = require('@playwright/test');

// The hero title is one <h1> holding a plain lead and a highlighted <span>.
//
// Two separate faults lived here, and only one is reachable from a browser.
//
//   The missing gap. Where the highlight is inline the two ran together —
//   "Represent" + "Crewe Vagrants" printed "RepresentCrewe Vagrants". It only
//   bites content that has been *saved*, because a trailing space in the field
//   is stripped on save; the built-in demo defaults are authored with their
//   trailing space intact, so no preview page can reproduce it. That fix is
//   covered where it is actually observable — SectionsTest::
//   test_hero_lead_gains_a_gap_before_the_highlight.
//
//   The mid-word break, below, is reproducible here.

test('the hero highlight never breaks mid-word', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');
  const hl = page.locator('.ch-hero__hl').first();
  await expect(hl).toBeVisible();

  // The highlight box is capped at the container width; when the display face
  // outgrew it the browser split the word itself, printing "membershi / p".
  const box = await hl.evaluate((el) => ({
    lines: el.getClientRects().length,
    own: el.getBoundingClientRect().width,
    parent: el.closest('h1').getBoundingClientRect().width,
  }));
  expect(box.lines).toBe(1);
  expect(box.own).toBeLessThanOrEqual(box.parent + 1);
});
