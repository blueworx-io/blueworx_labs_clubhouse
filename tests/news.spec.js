const { test, expect } = require('@playwright/test');

// Two harnesses, two jobs.
//
// The demo post source only exists in the DB-free preview, so anything that
// counts stories, pages through them or opens an article is tagged @preview —
// against real WordPress those numbers are whatever the club has published,
// which is nothing on a fresh install. `?clubhouse_page=post` is a preview-only
// address too: an article lives at a real permalink under WordPress, and there
// is no 'post' slug in the page map for it to route to.
//
// What the @wordpress spec checks is the part that must hold on any site: the
// News page is served, it is dressed in the clubhouse chrome, and a site with
// no stories yet says so rather than rendering an empty frame.

test('@preview the news index leads with a story and lists the rest', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  await expect(page.locator('.ch-newshead')).toBeVisible();
  await expect(page.locator('.ch-featured__card')).toBeVisible();
  await expect(page.locator('.ch-postcard')).toHaveCount(5);
  await expect(page.locator('.ch-newsgrid__count')).toContainText('stories');
});

test('@preview category pills narrow the list', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  await page.getByRole('link', { name: 'Hockey', exact: true }).click();
  await expect(page.locator('.ch-filter--on')).toHaveText('Hockey');
  const cats = await page.locator('.ch-postcard__cat').allTextContents();
  expect(cats.every((c) => c.trim().toLowerCase() === 'hockey')).toBe(true);
});

test('@preview the pager reaches the second page', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  await page.locator('.ch-pager__step--next').click();
  await expect(page.locator('.ch-pager__no--on')).toHaveText('2');
  // The lead story is a first-page-only treatment.
  await expect(page.locator('.ch-featured__card')).toHaveCount(0);
});

test('@preview an article renders headline, body and the way back', async ({ page }) => {
  await page.goto('?clubhouse_page=post');

  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('.ch-posthead__title')).toBeVisible();
  await expect(page.locator('.ch-prose p').first()).toBeVisible();
  await expect(page.locator('.ch-posthead__back')).toBeVisible();
});

test('@preview an article keeps the site header and footer', async ({ page }) => {
  await page.goto('?clubhouse_page=post');
  await expect(page.locator('header.ch-nav')).toBeVisible();
  await expect(page.locator('.ch-footer')).toBeVisible();
});

test('@preview an article offers more to read', async ({ page }) => {
  await page.goto('?clubhouse_page=post');
  await expect(page.locator('.ch-related .ch-postcard')).toHaveCount(3);
});

// The band used to reach for --space-14, a step the scale never defined. An
// undefined custom property makes the whole padding-block invalid, so the
// header lost its padding at both ends at once: the eyebrow sat on the nav and
// the last line of the headline touched the bottom edge. Asserting the gaps
// rather than the token keeps the test about what a reader sees.
test('@preview the news header has room above and below it', async ({ page }) => {
  await page.goto('?clubhouse_page=news');

  const gaps = await page.evaluate(() => {
    const band = document.querySelector('.ch-newshead');
    const nav = document.querySelector('header.ch-nav');
    const title = document.querySelector('.ch-newshead__title');
    return {
      above: band.getBoundingClientRect().top - nav.getBoundingClientRect().bottom,
      inside: band.querySelector('.ch-eyebrow').getBoundingClientRect().top - band.getBoundingClientRect().top,
      below: band.getBoundingClientRect().bottom - title.getBoundingClientRect().bottom,
    };
  });

  expect(gaps.inside, 'the eyebrow sits flush against the top of the band').toBeGreaterThanOrEqual(24);
  expect(gaps.below, 'the headline touches the bottom of the band').toBeGreaterThanOrEqual(24);
  expect(gaps.above, 'the band has drifted away from the nav').toBeLessThanOrEqual(1);
});

test('@preview the news pages hold their layout on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });

  for (const slug of ['news', 'post']) {
    await page.goto(`?clubhouse_page=${slug}`);
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    expect(overflow, `${slug} scrolls sideways`).toBeLessThanOrEqual(1);
  }
});

test('the news page is served in the clubhouse chrome', async ({ page }) => {
  const response = await page.goto('?clubhouse_page=news');
  expect(response?.status()).toBe(200);

  await expect(page.locator('.ch-newshead')).toBeVisible();
  await expect(page.locator('header.ch-nav')).toBeVisible();
  await expect(page.locator('.ch-footer')).toBeVisible();
  // Whatever the club has published, the page accounts for it: either stories,
  // or a sentence saying there are none. Never an empty frame.
  const stories = await page.locator('.ch-postcard, .ch-featured__card').count();
  if (stories === 0) {
    await expect(page.locator('.ch-empty')).toBeVisible();
  }
});
