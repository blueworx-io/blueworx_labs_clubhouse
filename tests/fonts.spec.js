const { test, expect } = require('@playwright/test');

// Proves Phase 5: the rendered page makes zero third-party font requests and
// loads its fonts from the plugin's own /assets/fonts/ directory.
test('fonts are self-hosted, no Google CDN requests', async ({ page }) => {
  const thirdParty = [];
  const fontHits = [];
  page.on('request', (req) => {
    const url = req.url();
    if (url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com')) {
      thirdParty.push(url);
    }
  });
  page.on('response', (res) => {
    const url = res.url();
    if (url.includes('/assets/fonts/') && url.endsWith('.woff2')) {
      fontHits.push({ url, status: res.status() });
    }
  });

  await page.goto('?clubhouse_page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  // Let font requests settle.
  await page.waitForLoadState('networkidle');

  expect(thirdParty, `unexpected third-party font requests: ${thirdParty.join(', ')}`).toHaveLength(0);
  expect(fontHits.length, 'expected at least one self-hosted woff2 request').toBeGreaterThan(0);
  for (const hit of fontHits) {
    expect(hit.status, `status for ${hit.url}`).toBe(200);
  }
});

// The test above proves the fonts DOWNLOAD. It does not prove anything uses
// them, and that gap is exactly how the live site shipped with every woff2
// returning 200 while body copy rendered in Times New Roman: an active theme's
// reset (`body { font: inherit }`) tied our body rule on specificity and won on
// source order. These assert the fonts are actually APPLIED.
// Each look ships its own pair (Court Side is Inter/Syne, Members House is
// Mulish/Fraunces), so these read the family out of the look's own token rather
// than naming one. The invariant is "the token reached the element", which holds
// for every look and for any future one.
const FIRST_FAMILY = (stack) => stack.split(',')[0].trim().replace(/^['"]|['"]$/g, '');

test('body typography resolves to the look tokens, not a UA fallback', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  await page.waitForLoadState('networkidle');

  const body = await page.evaluate(() => {
    const s = getComputedStyle(document.body);
    return {
      family: s.fontFamily,
      size: parseFloat(s.fontSize),
      lineHeight: parseFloat(s.lineHeight),
      bodyToken: getComputedStyle(document.documentElement).getPropertyValue('--font-body').trim(),
    };
  });

  expect(body.bodyToken, '--font-body token must be defined').not.toBe('');
  // Equality on the FIRST family, not containment anywhere in the stack: under
  // the production bug body computed to "Times New Roman", so the look's face
  // has to be the one actually leading the stack, not merely present in it.
  expect(FIRST_FAMILY(body.family), 'body must resolve to the --font-body face').toBe(
    FIRST_FAMILY(body.bodyToken)
  );
  // Both were collapsed by the reset too: font-size dropped to 16 and
  // line-height to exactly 1. Asserted as ratios so a look may retune either.
  expect(body.size, 'body font-size must survive the theme cascade').toBeGreaterThan(16);
  expect(body.lineHeight / body.size, 'body line-height must not be reset to 1').toBeGreaterThan(1.5);
});

test('display and body faces each reach the elements that ask for them', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  await page.waitForLoadState('networkidle');

  const tokens = await page.evaluate(() => {
    const cs = getComputedStyle(document.documentElement);
    return {
      display: cs.getPropertyValue('--font-display').trim(),
      body: cs.getPropertyValue('--font-body').trim(),
    };
  });

  // One representative per role, plus a form control — buttons do not inherit
  // font-family from body by default, so they need their own rule to get there.
  const roles = {
    '.ch-sec__title': tokens.display,
    '.ch-eyebrow': tokens.body,
    '.ch-btn': tokens.body,
    '.ch-tabs__btn': tokens.body,
  };

  for (const [selector, stack] of Object.entries(roles)) {
    const el = page.locator(selector).first();
    if ((await el.count()) === 0) continue;
    const family = await el.evaluate((node) => getComputedStyle(node).fontFamily);
    expect(family, `${selector} should render in ${FIRST_FAMILY(stack)}`).toContain(
      FIRST_FAMILY(stack)
    );
  }
});
