const { test, expect } = require('@playwright/test');

// A team can carry a link to its page elsewhere — a league table, a
// governing-body squad page. It is optional: teams without one must show no
// button at all, on the Teams page or on the team's own page.
//
// The demo club has two of each, so both states are on screen together.

const LINKED = 'https://league.example.com/rugby/1st-xv';

test('@preview only the teams that have a page elsewhere get a button', async ({ page }) => {
  await page.goto('?clubhouse_page=teams');

  const cards = page.locator('.ch-scard');
  const buttons = page.locator('.ch-scard .ch-scard__cta');
  expect(await cards.count(), 'a card per team').toBeGreaterThan(await buttons.count());
  expect(await buttons.count(), 'the demo club has teams with a page elsewhere').toBeGreaterThan(0);

  // Every button that is there goes somewhere, in its own tab.
  for (const button of await buttons.all()) {
    await expect(button).toBeVisible();
    await expect(button).toHaveAttribute('href', /^https?:\/\//);
    await expect(button).toHaveAttribute('target', '_blank');
    await expect(button).toHaveAttribute('rel', /noopener/);
  }

  await expect(buttons.first()).toHaveAttribute('href', LINKED);
});

// The card title's link covers the whole card so the card reads as clickable.
// A button underneath that overlay would look like a button and do nothing.
test('@preview the team-page button is clickable, not buried under the card link', async ({ page }) => {
  await page.goto('?clubhouse_page=teams');
  const button = page.locator('.ch-scard__cta').first();
  await button.scrollIntoViewIfNeeded();

  const onTop = await button.evaluate((el) => {
    const box = el.getBoundingClientRect();
    const hit = document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2);
    return el === hit || el.contains(hit);
  });
  expect(onTop, 'the button must be the thing under the cursor').toBe(true);
});

test('@preview a linked team offers the link again on its own page', async ({ page }) => {
  await page.goto('?clubhouse_page=teams&clubhouse_item=1st-xv');
  const band = page.locator('.ch-band').filter({ hasText: 'About the section' });
  await expect(band).toHaveCount(1);
  const button = band.locator('.ch-btn');
  await expect(button).toHaveAttribute('href', LINKED);
  await expect(button).toHaveAttribute('target', '_blank');
  await expect(button).toBeVisible();
});

test('@preview a team with no page elsewhere gets no button on its own page', async ({ page }) => {
  await page.goto('?clubhouse_page=teams&clubhouse_item=ladies-1s');
  const band = page.locator('.ch-band').filter({ hasText: 'About the section' });
  await expect(band).toHaveCount(1);
  await expect(band.locator('.ch-btn')).toHaveCount(0);
});
