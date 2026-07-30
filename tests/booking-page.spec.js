const { test, expect } = require('@playwright/test');

// @preview only: the Booking page exists when LatePoint does, and CI's real
// WordPress has no LatePoint. The preview reports every integration present so
// the page stays designable, which is exactly the harness that can serve it.

test('@preview booking page renders a slot per LatePoint resource list', async ({ page }) => {
  await page.goto('?clubhouse_page=booking');

  const slots = page.locator('.ch-shortcode');
  await expect(slots).toHaveCount(3);

  // No expander in the preview, so each slot shows its shortcode as text. That
  // is the point: it shows what will run without pretending to have run it.
  await expect(slots.nth(0)).toContainText('[latepoint_resources items="services" columns="3"]');
  await expect(slots.nth(1)).toContainText('[latepoint_resources items="locations" columns="3"]');
  await expect(slots.nth(2)).toContainText('[latepoint_resources items="agents" columns="3"]');
});

test('@preview each booking slot is headed so the page reads as a journey', async ({ page }) => {
  await page.goto('?clubhouse_page=booking');

  for (const heading of ['Sessions and services', 'Courts and locations', 'Coaches and staff']) {
    await expect(page.getByRole('heading', { name: heading })).toBeVisible();
  }
});

// The booking calendar sits on the Calendar page, above the fixtures — deciding
// when to play comes before looking up results.
test('@preview the calendar page carries the booking calendar above its fixtures', async ({ page }) => {
  await page.goto('?clubhouse_page=calendar');

  const slot = page.locator('.ch-shortcode');
  await expect(slot).toHaveCount(1);
  await expect(slot).toContainText('[latepoint_calendar view="month"]');

  const slotY = (await slot.boundingBox()).y;
  const fixturesY = (await page.locator('.ch-cal__month').first().boundingBox()).y;
  expect(slotY).toBeLessThan(fixturesY);
});

// A shortcode slot holds another plugin's markup, which ships its own CSS and
// knows nothing about this layout. It must not be able to widen the layout.
//
// Measured on the slot's own .ch-wrap rather than on the document: the preview
// injects a fixed look-switcher panel of its own, so documentElement.scrollWidth
// answers a question about the harness as much as about this rule.
test('@preview a wide shortcode scrolls inside its slot, not the layout', async ({ page }) => {
  await page.goto('?clubhouse_page=booking');

  const measured = await page.locator('.ch-shortcode').first().evaluate((el) => {
    el.innerHTML = '<div style="width:4000px">very wide third-party markup</div>';
    const wrap = el.closest('.ch-wrap');
    return {
      slotScrollWidth: el.scrollWidth,
      slotClientWidth: el.clientWidth,
      wrapScrollWidth: wrap.scrollWidth,
      wrapClientWidth: wrap.clientWidth,
    };
  });

  // The oversized content is real and scrollable inside the slot...
  expect(measured.slotScrollWidth).toBeGreaterThan(measured.slotClientWidth);
  // ...and the container around it has not grown by a pixel.
  expect(measured.wrapScrollWidth).toBeLessThanOrEqual(measured.wrapClientWidth);
});

test('@preview booking is reachable from the header nav and the footer', async ({ page }) => {
  await page.goto('?clubhouse_page=home');

  await expect(page.locator('.ch-nav__links').getByText('Book a court')).toBeVisible();
  await expect(page.locator('.ch-footer').getByText('Book a court')).toBeVisible();
});
