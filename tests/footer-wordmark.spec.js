const { test, expect } = require('@playwright/test');

// The footer wordmark is the club's name set at poster scale. Two things can go
// wrong with it: it drags the whole page sideways, or it runs out of room and
// loses the end of the name — "Crewe Squash Clu". Both would be on every page.

const LOOKS = ['court-side', 'floodlight', 'members-house'];
const WIDTHS = [320, 390, 820, 1440];

// Long, short, wide-lettered and narrow-lettered: the size is worked out from
// the name, so the names are what has to vary. The preview takes one as ?club=.
const NAMES = [
  'Crewe Squash Club',
  'Wombwell & Mexborough Amateur Swimming Club',
  'Wem Town Women',
  'Little Lillington FC',
  'MMA',
];

for (const look of LOOKS) {
  test(`@preview the footer wordmark never scrolls the page sideways — ${look}`, async ({ page }) => {
    for (const width of WIDTHS) {
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

  test(`@preview the footer wordmark shows the whole name on one line — ${look}`, async ({ page }) => {
    for (const name of NAMES) {
      await page.goto(`?clubhouse_page=home&look=${look}&club=${encodeURIComponent(name)}`);
      await page.evaluate(() => document.fonts.ready);
      for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 });
        const measured = await page.evaluate(() => {
          const mark = document.querySelector('.ch-footer__wordmark');
          const text = mark.querySelector('.ch-footer__wordmark-text');
          const range = document.createRange();
          range.selectNodeContents(text);
          const ink = range.getBoundingClientRect();
          const box = mark.getBoundingClientRect();
          return {
            name: text.textContent,
            overhang: Math.max(box.left - ink.left, ink.right - box.right),
            lines: Math.round(ink.height / parseFloat(getComputedStyle(text).lineHeight)),
            clips: getComputedStyle(mark).overflow !== 'visible' || getComputedStyle(text).overflow !== 'visible',
          };
        });
        const where = `"${name}" — ${look} @${width}`;
        expect(measured.name).toBe(name);
        expect(measured.overhang, where).toBeLessThanOrEqual(1);
        expect(measured.lines, where).toBe(1);
        expect(measured.clips, 'nothing is cut off to make it fit').toBe(false);
      }
    }
  });
}

test('@preview the footer carries a copyright line', async ({ page }) => {
  await page.goto('?clubhouse_page=home');
  await expect(page.locator('.ch-footer__copyright')).toContainText(
    new RegExp(`©\\s*${new Date().getUTCFullYear()}`)
  );
});
