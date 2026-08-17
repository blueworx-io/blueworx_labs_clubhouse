const { test, expect } = require('@playwright/test');

// The header used to keep its full desktop layout down to 900px, but the menu,
// the brand and the two buttons stop fitting at about 1230px. Everything in
// between pushed the page wider than the window, so every page gained a
// horizontal scrollbar on an ordinary laptop.
//
// The widths below bracket the old dead zone and both switch-over points,
// rather than sampling a few sizes that happened to be fine. 1261/1260 sit
// either side of the point the menu now collapses to a burger.
const WIDTHS = [1600, 1440, 1366, 1300, 1280, 1261, 1260, 1180, 1100, 1024, 960, 901, 900, 820, 768, 600, 414, 390, 360];

// Every look ships its own header rules, so a fix in one proves nothing about
// the others.
const LOOKS = ['court-side', 'floodlight', 'members-house'];

for (const look of LOOKS) {
  test(`@preview ${look} never scrolls sideways at any width`, async ({ page }) => {
    // Two pages at nineteen widths is thirty-eight navigations in one test, and
    // on a loaded machine that does not fit the default limit — it timed out
    // mid-run while passing on its own. The coverage is the point of the test,
    // so the limit gives way rather than the width list.
    test.slow();

    for (const slug of ['home', 'news']) {
      for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto(`?clubhouse_page=${slug}&look=${look}`);

        const over = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );
        expect(over, `${look} / ${slug} at ${width}px scrolls sideways by ${over}px`).toBeLessThanOrEqual(1);
      }
    }
  });
}

// Raising the switch-over point is only right if the menu is still reachable
// once it hides — otherwise the pages would simply have lost their navigation.
test('@preview the menu is reachable at every width', async ({ page }) => {
  for (const width of [1600, 1261, 1260, 1100, 900, 390]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto('?clubhouse_page=home');

    const nav = await page.evaluate(() => ({
      links: getComputedStyle(document.querySelector('.ch-nav__links')).display,
      burger: getComputedStyle(document.querySelector('.ch-nav__disc')).display,
    }));

    const reachable = nav.links !== 'none' || nav.burger !== 'none';
    expect(reachable, `at ${width}px there is neither a menu row nor a burger`).toBe(true);
  }
});
