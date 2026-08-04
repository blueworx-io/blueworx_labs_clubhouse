const { test, expect } = require('@playwright/test');

// The demo switcher is admin tooling shown to every viewer, pinned above the page
// at z-index 99999. On a 390px screen it landed over the bottom of the open mobile
// menu, and "Log in" could not be tapped at all — the click went to the switcher.
//
// Hit-testing, not geometry: elementFromPoint answers the question a finger asks
// ("what would this tap hit?"), where comparing bounding boxes only answers
// whether two rectangles overlap.
const topmostAt = (page, text) =>
  page.evaluate((label) => {
    const drawer = document.querySelector('.ch-nav__drawer');
    const link = [...drawer.querySelectorAll('a')].find((a) => a.textContent.trim() === label);
    if (!link) return { found: false };
    const r = link.getBoundingClientRect();
    const hit = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
    return { found: true, reachable: hit === link || link.contains(hit) };
  }, text);

test.describe('@preview demo switcher clearance', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('every item in the open mobile menu can be tapped', async ({ page }) => {
    await page.goto('?demo=1');
    await page.evaluate(() => { document.querySelector('.ch-nav__disc').open = true; });

    // The switcher is what this guards against, so assert it is actually present:
    // a passing test on a page with no demo bar would prove nothing.
    await expect(page.locator('.clubhouse-demo')).toHaveCount(1);

    for (const label of ['Home', 'Contact', 'Log in']) {
      const res = await topmostAt(page, label);
      expect(res.found, `${label} should be in the drawer`).toBe(true);
      expect(res.reachable, `${label} must not be covered by the demo switcher`).toBe(true);
    }
  });
});
