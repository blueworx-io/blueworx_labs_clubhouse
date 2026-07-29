const { test, expect } = require('@playwright/test');

// Small-text WCAG AA. The eyebrow is 12px, so it needs 4.5:1 — the large-text
// 3:1 allowance does not apply, bold or not.
const AA_SMALL = 4.5;

// Measures what the browser actually painted.
//
// Colours are resolved through a canvas rather than by parsing the computed
// string. Chrome serialises color-mix() as `oklab(L a b)`, whose three numbers
// are NOT r/g/b — a naive parse reads oklab(0.42 0.005 0.02) as near-black and
// silently reports a passing ratio for text that is actually failing. Canvas
// resolves every notation the stylesheets use (hex, rgb, oklab, color-mix) to
// real sRGB, and composites alpha over the backdrop instead of guessing.
const CONTRAST_PROBE = () => {
  const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
  const resolve = (value, over) => {
    ctx.clearRect(0, 0, 1, 1);
    if (over) {
      ctx.fillStyle = `rgb(${over.join(',')})`;
      ctx.fillRect(0, 0, 1, 1);
    }
    // An unparseable value leaves fillStyle at its previous setting, so seed a
    // sentinel and treat "unchanged" as unresolvable rather than as black.
    ctx.fillStyle = '#010203';
    ctx.fillStyle = value;
    if (ctx.fillStyle === '#010203' && value !== '#010203') return null;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2]];
  };

  const channel = (v) => {
    v /= 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  };
  const luminance = ([r, g, b]) => 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
  const contrast = (a, b) => {
    const [l1, l2] = [luminance(a), luminance(b)];
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };

  // A section can paint its backdrop with a positioned layer element rather than
  // its own background — .ch-home-hero__bg and .ch-card__scrim both sit behind
  // their text at a negative z-index. Those layers are SIBLINGS of the text, so
  // walking up the ancestor chain sails straight past them and reports whatever
  // colour is further up (usually the page shell). That reads as cream-on-cream
  // for text that is really cream-on-dark. Detect the layer and skip.
  const paintsWithLayer = (node) =>
    [...node.children].some((child) => {
      const cs = getComputedStyle(child);
      return (
        (cs.position === 'absolute' || cs.position === 'fixed') &&
        Number(cs.zIndex) < 0 &&
        (cs.backgroundColor !== 'rgba(0, 0, 0, 0)' || cs.backgroundImage !== 'none')
      );
    });

  const results = [];
  for (const el of document.querySelectorAll('.ch-eyebrow')) {
    // Walk up for the first painted backdrop. A background-image means the
    // backdrop is a photo or gradient, not a flat colour — undecidable here, so
    // skip rather than report the colour sitting behind the image.
    let node = el.parentElement;
    let backdrop = null;
    let undecidable = false;
    while (node) {
      const cs = getComputedStyle(node);
      if (paintsWithLayer(node) || (cs.backgroundImage && cs.backgroundImage !== 'none')) {
        undecidable = true;
        break;
      }
      if (cs.backgroundColor && cs.backgroundColor !== 'rgba(0, 0, 0, 0)') {
        backdrop = resolve(cs.backgroundColor);
        break;
      }
      node = node.parentElement;
    }
    if (undecidable || !backdrop) continue;

    const fg = resolve(getComputedStyle(el).color, backdrop);
    if (!fg) continue;
    results.push({
      text: el.textContent.trim().slice(0, 24),
      section: (el.closest('[class*="ch-band"], [class*="ch-sec"]')?.className || 'page').slice(0, 36),
      ratio: Number(contrast(fg, backdrop).toFixed(2)),
    });
  }
  return results;
};

const assertEyebrowsClearAA = async (page, label) => {
  const measured = await page.evaluate(CONTRAST_PROBE);
  expect(measured.length, 'expected at least one eyebrow on a solid backdrop').toBeGreaterThan(0);
  const failures = measured.filter((m) => m.ratio < AA_SMALL);
  expect(
    failures,
    `${label}: eyebrows below ${AA_SMALL}:1 — ${JSON.stringify(failures, null, 2)}`
  ).toHaveLength(0);
};

test('every eyebrow on a solid backdrop clears WCAG AA', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  await assertEyebrowsClearAA(page, 'default look');
});

// Members House is the only look that puts an --color-ink-soft eyebrow on a
// --color-accent-wash band (Court Side paints accent-ink on the accent;
// Floodlight fades its band to --color-bg). The wash is
// mix(accent, shell-bg, 0.12), so it darkens as the club's accent darkens, and
// ink-soft alone fell under AA there — 4.17:1 on the live site's navy, 4.37 on
// Berry, 4.40 on Cobalt, while passing on lighter accents like Volt Lime (5.08).
//
// So a single-accent check is not enough: it passes or fails depending on which
// club colour happens to be set. This sweeps every accent the switcher offers.
// @preview — ?look= and the switcher are preview affordances; in WordPress both
// the look and the accent are persisted settings.
test('Members House eyebrows clear AA for every club accent @preview', async ({ page }) => {
  await page.goto('?clubhouse_page=home&look=members-house');
  await expect(page.locator('#ch-main')).toBeVisible();
  await expect(page.locator('.ch-band--accent')).toHaveCount(1);

  await assertEyebrowsClearAA(page, 'members-house / look default accent');

  const swatches = page.locator('.ch-switcher button');
  const count = await swatches.count();
  expect(count, 'expected the preview accent switcher to offer swatches').toBeGreaterThan(0);

  for (let i = 0; i < count; i++) {
    const name = (await swatches.nth(i).getAttribute('title')) || `swatch ${i}`;
    await swatches.nth(i).click();
    await assertEyebrowsClearAA(page, `members-house / ${name}`);
  }
});
