const { test, expect } = require('@playwright/test');

// The ticker is a two-copy marquee: the viewport holds the real track and an
// aria-hidden clone of it, and each track slides left by its own full width.
// When the cycle ends, the clone has arrived exactly where the real track
// started, so the wrap is invisible and the news reads as one endless line.
//
// It used to translate by -50%, which is half of one copy rather than all of
// it, so the line ran halfway along and then snapped back to the start.

test('the ticker carries a real track and a hidden clone of it', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  const tracks = page.locator('.ch-ticker__viewport .ch-ticker__track');
  await expect(tracks).toHaveCount(2);
  await expect(tracks.nth(0)).not.toHaveAttribute('aria-hidden', 'true');
  await expect(tracks.nth(1)).toHaveAttribute('aria-hidden', 'true');
  expect(await tracks.nth(1).locator('.ch-ticker__item').count()).toBe(
    await tracks.nth(0).locator('.ch-ticker__item').count()
  );
});

// Every look draws its own ticker, so every look can get this wrong on its own.
// @preview — ?look= is a preview affordance.
for (const look of ['court-side', 'members-house', 'floodlight']) {
  test(`the ticker wraps without snapping back — ${look} @preview`, async ({ page }) => {
    await page.goto(`?clubhouse_page=home&look=${look}`);
    await expect(page.locator('.ch-ticker')).toBeVisible();

    const measured = await page.evaluate(() => {
      const tracks = Array.from(document.querySelectorAll('.ch-ticker__viewport .ch-ticker__track'));
      const anims = tracks.map((t) => t.getAnimations()[0]);
      if (anims.some((a) => !a)) return null;

      const duration = anims[0].effect.getTiming().duration;
      const seek = (ms) =>
        anims.forEach((a) => {
          a.pause();
          a.currentTime = ms;
        });
      const firstItemX = (track) =>
        track.querySelector('.ch-ticker__item').getBoundingClientRect().x;

      seek(0);
      const startX = firstItemX(tracks[0]);
      // Just short of the wrap: at exactly `duration` an infinite animation has
      // already ticked over to the next iteration and reset to translateX(0).
      seek(duration * 0.9999);
      const endX = firstItemX(tracks[1]);

      return {
        startX,
        endX,
        // A track squashed by flex-shrink would slide by less than the width it
        // actually occupies, which reopens the same gap by another route.
        widths: tracks.map((t) => ({
          laid: t.getBoundingClientRect().width,
          content: t.scrollWidth,
        })),
      };
    });

    expect(measured, 'the ticker track must be animated').not.toBeNull();

    for (const { laid, content } of measured.widths) {
      expect(Math.abs(laid - content), 'a track must not be squashed by its viewport').toBeLessThan(2);
    }

    // As the cycle closes, the clone must sit exactly where the real track began.
    expect(
      Math.abs(measured.endX - measured.startX),
      `clone lands ${Math.round(measured.endX - measured.startX)}px from where the track started`
    ).toBeLessThan(2);
  });
}

// Reduced motion still gets a readable, scrollable line with no duplicate.
test('reduced motion drops the animation and the clone @preview', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('.ch-ticker__viewport .ch-ticker__track').first()).toBeVisible();
  const animated = await page.evaluate(() =>
    Array.from(document.querySelectorAll('.ch-ticker__track')).some(
      (t) => t.getAnimations().length > 0
    )
  );
  expect(animated, 'reduced motion must not animate the ticker').toBe(false);
});
