const { test, expect } = require('@playwright/test');

// A full-bleed band has no gutter for a rounded corner to sit in, so the curve
// reads as a rendering fault — a sliver of page background cutting into the
// corner of an otherwise edge-to-edge section. Court Side already got this
// right; Members House and Floodlight carried a radius on .ch-band-img,
// .ch-ticker and .ch-info. This asserts the rule rather than the three
// instances, so a new full-bleed section cannot reintroduce it.
const FULL_BLEED_WITH_RADIUS = () => {
  const vw = document.documentElement.clientWidth;
  const offenders = [];
  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect();
    // Allow a 2px slack: sub-pixel layout means an edge-to-edge band can measure
    // a hair under the viewport without being inset.
    if (r.width < vw - 2 || r.height === 0) continue;

    const cs = getComputedStyle(el);
    // Only elements that actually paint a surface can show a cut corner.
    const paints =
      cs.backgroundColor !== 'rgba(0, 0, 0, 0)' ||
      cs.backgroundImage !== 'none' ||
      parseFloat(cs.borderTopWidth) > 0;
    if (!paints) continue;

    const corners = {
      tl: parseFloat(cs.borderTopLeftRadius),
      tr: parseFloat(cs.borderTopRightRadius),
      bl: parseFloat(cs.borderBottomLeftRadius),
      br: parseFloat(cs.borderBottomRightRadius),
    };
    const rounded = Object.entries(corners).filter(([, v]) => v > 0);
    if (!rounded.length) continue;

    offenders.push({
      selector: String(el.className || el.tagName).slice(0, 60),
      width: Math.round(r.width),
      viewport: vw,
      rounded: Object.fromEntries(rounded),
    });
  }
  return offenders;
};

test('no full-bleed section is rounded', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  const offenders = await page.evaluate(FULL_BLEED_WITH_RADIUS);
  expect(offenders, `rounded full-bleed sections: ${JSON.stringify(offenders, null, 2)}`).toEqual([]);
});

// The rule has to hold for every look on every page — the offending sections
// only appear on some pages (.ch-band-img on home/about/news, .ch-info and
// .ch-ticker on home/news), and each look styles them independently.
// @preview because ?look= is a preview affordance; in WordPress the look is a
// persisted setting.
const LOOKS = ['court-side', 'members-house', 'floodlight'];
const PAGES = ['home', 'about', 'news'];

for (const look of LOOKS) {
  test(`no full-bleed section is rounded — ${look} @preview`, async ({ page }) => {
    for (const slug of PAGES) {
      await page.goto(`?clubhouse_page=${slug}&look=${look}`);
      await expect(page.locator('#ch-main')).toBeVisible();
      const offenders = await page.evaluate(FULL_BLEED_WITH_RADIUS);
      expect(
        offenders,
        `${look}/${slug} — rounded full-bleed sections: ${JSON.stringify(offenders, null, 2)}`
      ).toEqual([]);
    }
  });
}
