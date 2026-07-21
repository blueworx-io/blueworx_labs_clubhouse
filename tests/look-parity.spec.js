const { test, expect } = require('@playwright/test');

// Static coverage (LookCoverageTest) proves a rule exists for each class. It
// cannot prove the rule matches what the renderer emits. These assertions load
// each look in a browser and read computed styles off the six components that
// were once Court Side only.
//
// Computed values, not screenshots — there are no baselines to churn when the
// design legitimately changes.

const LOOKS = ['court-side', 'floodlight', 'members-house'];

// One marker per previously-broken component, with a property that is unset
// until the component is styled.
const CHECKS = [
  { page: 'sports',   selector: '.ch-filters',    prop: 'display',       expected: 'flex' },
  { page: 'teams',    selector: '.ch-scard',      prop: 'display',       expected: 'flex' },
  { page: 'events',   selector: '.ch-event',      prop: 'display',       expected: 'flex' },
  { page: 'calendar', selector: '.ch-cal',        prop: 'display',       expected: 'flex' },
];

// Switches look, then PROVES it switched.
//
// Without the proof this spec is dangerous: against the DB-free preview the look
// comes from ?look=, not this cookie, so every "look" would resolve to Court Side
// and all assertions below would pass while testing one look three times. A spec
// that silently tests nothing is worse than no spec.
async function useLook(page, look, slug) {
  await page.goto(`?clubhouse_page=${slug}`);
  await page.context().addCookies([
    { name: 'clubhouse_demo_look', value: look, url: new URL(page.url()).origin },
  ]);
  await page.goto(`?clubhouse_page=${slug}`);

  const family = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--font-display').trim()
  );
  expect(family, `look "${look}" did not take effect — is demo mode on?`).not.toBe('');
  return family;
}

for (const look of LOOKS) {
  for (const { page: slug, selector, prop, expected } of CHECKS) {
    test(`${look}: ${selector} on ${slug} is styled @wordpress`, async ({ page }) => {
      await useLook(page, look, slug);

      const el = page.locator(selector).first();
      await expect(el, `${selector} must exist on ${slug}`).toBeAttached();
      const actual = await el.evaluate((node, p) => getComputedStyle(node)[p], prop);
      expect(actual, `${selector} under ${look}`).toBe(expected);
    });
  }
}

for (const look of LOOKS) {
  test(`${look}: the social band is styled @wordpress`, async ({ page }) => {
    await useLook(page, look, 'contact');
    const link = page.locator('.ch-social__link').first();
    await expect(link).toBeAttached();
    // Unstyled anchors are inline (default UA style, block parent). The pill
    // treatment makes .ch-social__links a flex container and .ch-social__link
    // itself specifies inline-flex — but as a flex item its outer display is
    // blockified per the CSS Display spec, so Chromium's getComputedStyle
    // reports 'flex', not 'inline-flex'. That blockification is deterministic
    // and look-independent (no look overrides .ch-social__links), so 'flex'
    // is still a reliable styled-vs-unstyled discriminator.
    const display = await link.evaluate((n) => getComputedStyle(n).display);
    expect(display, `.ch-social__link under ${look}`).toBe('flex');
  });
}

// The looks must actually differ. If this passes while the others fail, the
// cookie is being ignored and the suite above is testing Court Side three times.
test('the three looks resolve to three different display fonts @wordpress', async ({ page }) => {
  const seen = new Set();
  for (const look of LOOKS) {
    seen.add(await useLook(page, look, 'home'));
  }
  expect(seen.size, `expected 3 distinct display fonts, saw ${[...seen].join(' | ')}`).toBe(3);
});
