const { test, expect } = require('@playwright/test');

// A finger needs about 44px on its smallest side. Several links here are set in
// body copy and came out 20-24px tall; the filter pills came out under 44px
// wide. Each look carries its own copy of the rule, so each look is measured.
const MIN = 44;

// Browsers lay out on 1/64px units, so a box asked for 44px is occasionally
// painted at 43.99997. Half a pixel of slack keeps that from reading as a
// failure while still catching anything genuinely undersized — the smallest
// real offender here was 20px.
const SLACK = 0.5;

const LOOKS = ['court-side', 'floodlight', 'members-house'];
const PAGES = ['home', 'contact', 'sports'];

// Measures painted geometry, not declared CSS — padding, flex and wrapping all
// change the box, and only the browser knows the result.
const PROBE = (min) =>
  [...document.querySelectorAll('a,button,[role=tab]')]
    .filter((el) => {
      // Demo mode's look switcher is our own scaffolding, not club navigation,
      // and does not ship to a real club's visitors.
      if (el.closest('.ch-switcher') || el.classList.contains('ch-look-toggle')) {
        return false;
      }
      // A card's title link stretches its hit area over the whole card with an
      // inset ::after, so the anchor's own box understates what a finger can
      // press. Measure the card, which is what actually responds to the tap.
      const box = el.closest('.ch-scard--linked') || el;
      const r = box.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && Math.min(r.width, r.height) < min;
    })
    .map((el) => {
      // Report the same box the filter judged, or the failure names a size
      // nobody measured.
      const r = (el.closest('.ch-scard--linked') || el).getBoundingClientRect();
      return `${el.tagName.toLowerCase()}.${el.className || '(none)'} ` +
        `${Math.round(r.width)}x${Math.round(r.height)}`;
    });

for (const look of LOOKS) {
  test(`@preview tap targets are at least ${MIN}px on a phone — ${look}`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    for (const slug of PAGES) {
      await page.goto(`?clubhouse_page=${slug}&look=${look}`);
      // These boxes are sized from their text, so they are a fraction of a pixel
      // short until the look's web font has replaced the fallback. Measuring
      // before that reports a failure the visitor never sees.
      await page.evaluate(() => document.fonts.ready);
      const small = await page.evaluate(PROBE, MIN - SLACK);
      expect(small, `${look} / ${slug}`).toEqual([]);
    }
  });
}
