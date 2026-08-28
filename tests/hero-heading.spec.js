const { test, expect } = require('@playwright/test');
const { targetingWordPress } = require('./harness');

// The hero title is one <h1> holding a lead span and a highlighted <span>.
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

// The highlight must START on line two, at every viewport — left to natural
// wrapping it broke wherever the text ran out, splitting the underline into two
// disconnected segments ("A club for every age and / ability").
//
// Measured rather than asserted on markup: the lead span being display:block is
// the mechanism, but what the issue asks for is the rendered result, and only a
// browser can tell us the highlight actually sits below the lead.
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 834, height: 1112 },
  { name: 'mobile', width: 390, height: 844 },
];

// One page per hero variant: hero() (ch-hero), home_hero() (ch-home-hero) and
// hero_filter() (ch-hero-filter). All three share hero_head(), so this is the
// component rule under test, not three page-specific fixes.
const HEROES = [
  { page: '', block: 'ch-home-hero' },
  { page: 'membership', block: 'ch-hero' },
  { page: 'sports', block: 'ch-hero-filter' },
];

for (const vp of VIEWPORTS) {
  for (const hero of HEROES) {
    test(`${hero.block} highlight starts on line two at ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      // Under WordPress a club page is a real page at its own address. The
      // '?clubhouse_page=' form is the preview's, and its empty-value case no
      // longer reaches the club home at all — it falls through to WordPress's
      // own front page, which has no hero on it.
      const address = targetingWordPress()
        ? `/${hero.page}${'' === hero.page ? '' : '/'}`
        : hero.page === '' ? '?clubhouse_page=' : `?clubhouse_page=${hero.page}`;
      await page.goto(address);

      const h1 = page.locator(`.${hero.block}__title`).first();
      await expect(h1).toBeVisible();

      const geom = await h1.evaluate((el, block) => {
        const lead = el.querySelector(`.${block}__lead`);
        const hl = el.querySelector(`.${block}__hl`);
        if (!lead || !hl) { return null; }
        const leadRects = [].slice.call(lead.getClientRects());
        const hlRects = [].slice.call(hl.getClientRects());
        const leadLast = leadRects[leadRects.length - 1];
        return {
          leadLastTop: leadLast.top,
          leadLastHeight: leadLast.height,
          hlTop: hlRects[0].top,
          hlLines: hlRects.length,
          hlFirstLineWidth: hlRects[0].width,
          hlLastLineWidth: hlRects[hlRects.length - 1].width,
        };
      }, hero.block);

      expect(geom).not.toBeNull();

      // Line two: the highlight's first line box opens below the lead's last one.
      //
      // Compared top-to-top, not top-to-bottom. At these display sizes a span's
      // client rect is its font box, which is taller than the line-height the
      // headings use (~1.03), so two rects on ADJACENT lines legitimately overlap
      // by a few pixels — bottom-of-lead is not a floor the next line clears.
      // Half a line down is unambiguous: same line means zero offset.
      expect(geom.hlTop - geom.leadLastTop).toBeGreaterThan(geom.leadLastHeight * 0.5);

      // One continuous run where it fits on a line; where it must wrap, no
      // orphaned last line (text-wrap:balance keeps the lines comparable).
      if (geom.hlLines > 1) {
        expect(geom.hlLastLineWidth).toBeGreaterThan(geom.hlFirstLineWidth * 0.25);
      }
    });
  }
}
