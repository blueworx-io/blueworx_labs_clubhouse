const { test, expect } = require('@playwright/test');

// The footer wordmark is the club's name set at poster scale. It is deliberately
// wider than most viewports, so the thing that can go wrong is it dragging the
// whole page sideways — which would affect every page on the site.

const LOOKS = ['court-side', 'floodlight', 'members-house'];

for (const look of LOOKS) {
  test(`@preview the footer wordmark never scrolls the page sideways — ${look}`, async ({ page }) => {
    for (const width of [390, 820, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`?clubhouse_page=home&look=${look}`);
      const measured = await page.evaluate(() => {
        const mark = document.querySelector('.ch-footer__wordmark');
        return {
          present: !!mark,
          hidden: mark ? mark.getAttribute('aria-hidden') : null,
          pageScrollsX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        };
      });
      expect(measured.present, `${look} @${width}`).toBe(true);
      expect(measured.hidden, 'decorative, so not read out twice').toBe('true');
      expect(measured.pageScrollsX, `${look} @${width}`).toBe(false);
    }
  });
}

test('@preview the footer carries a copyright line', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('.ch-footer__copyright')).toContainText(
    new RegExp(`©\\s*${new Date().getUTCFullYear()}`)
  );
});
