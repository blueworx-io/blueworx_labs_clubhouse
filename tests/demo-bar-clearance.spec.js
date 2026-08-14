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

// The panel used to be a bar across the bottom of the viewport, covering two of
// the home hero's call-to-action tiles at every width. It is now closed by
// default and opens only when someone asks for it.
test('the demo panel starts closed, as a small launcher @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/');

  const panel = page.locator('#clubhouse-demo');
  await expect(panel).toHaveCount(1);
  await expect(panel).not.toHaveAttribute('open', '');

  // Nothing of the club's is underneath it at desktop width.
  const covered = await page.evaluate(() => {
    const s = document.querySelector('#clubhouse-demo').getBoundingClientRect();
    return [...document.querySelectorAll('a,button')]
      .filter((el) => !el.closest('#clubhouse-demo'))
      .filter((el) => {
        const b = el.getBoundingClientRect();
        if (!b.width || !b.height) return false;
        return !(b.right < s.left || b.left > s.right || b.bottom < s.top || b.top > s.bottom);
      }).length;
  });
  expect(covered).toBe(0);
});

test('the demo panel still opens, and its controls work @wordpress', async ({ page }) => {
  await page.goto('/');

  await page.locator('#clubhouse-demo .clubhouse-demo__toggle').click();
  await expect(page.locator('#clubhouse-demo')).toHaveAttribute('open', '');
  await expect(page.locator('.clubhouse-demo__look').first()).toBeVisible();
});
