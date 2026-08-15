const { test, expect } = require('@playwright/test');

// @preview: both halves of booking need LatePoint to be present, and the
// preview reports every integration installed so design-time pages render.
// Real WordPress here has no LatePoint, so the Bookings page is not served at
// all and the Calendar's booking block is dropped — there would be nothing to
// assert against.
//
// Issue #136: booking was offered in two places that looked nothing alike,
// neither linking to the other, with nothing to say which to use.

test('the Bookings page hands over to the times @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=booking');
  const cta = page.locator('.ch-main .ch-hero__cta a').first();
  await expect(cta).toHaveText('See what is free');
  await expect(cta).toHaveAttribute('href', /calendar/);
});

test('the calendar points back at the sessions @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=calendar');
  const link = page.locator('#ch-calendar-booking .ch-sec__head a');
  await expect(link).toHaveText('Sessions, courts and coaches');
  await expect(link).toHaveAttribute('href', /booking/);
});

test('the Bookings page no longer promises a time it cannot show @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=booking');
  await expect(page.locator('.ch-main')).not.toContainText('and a time');
});
