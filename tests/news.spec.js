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

test('@preview an article can be shared, without loading anyone else’s script', async ({ page }) => {
  const foreign = [];
  page.on('request', (r) => {
    const host = new URL(r.url()).host;
    if (host && !host.startsWith('127.0.0.1') && !host.startsWith('localhost')) foreign.push(r.url());
  });

  await page.goto('?clubhouse_page=post');

  const share = page.locator('.ch-share');
  await expect(share).toHaveCount(1);
  for (const name of ['Facebook', 'WhatsApp', 'Email']) {
    await expect(share.getByRole('link', { name: new RegExp(name) })).toHaveAttribute('href', /.+/);
  }

  // The row is plain anchors, so opening the article reaches nobody but us.
  expect(foreign, `article loaded third-party requests: ${foreign.join(', ')}`).toEqual([]);
});

test('@preview copy link puts the story address on the clipboard', async ({ page, context }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await page.goto('?clubhouse_page=post');

  const copy = page.locator('.ch-share [data-clubhouse-copy]');
  // Ships hidden and is only revealed once copying is actually available.
  await expect(copy).toBeVisible();

  const expected = await copy.getAttribute('data-clubhouse-copy');
  await copy.click();

  await expect(copy).toHaveText('Link copied');
  const onClipboard = await page.evaluate(() => navigator.clipboard.readText());
  expect(onClipboard).toBe(expected);
});

// The preview always opens the newest story, which is exactly the edge case
// worth pinning in the browser: the half that has nowhere to go must be absent
// rather than drawn as a dead link.
test('@preview an article offers the story either side, and no dead half', async ({ page }) => {
  await page.goto('?clubhouse_page=post');

  const steps = page.locator('.ch-poststeps');
  await expect(steps).toHaveCount(1);

  const prev = steps.locator('.ch-poststep--prev');
  await expect(prev).toBeVisible();
  // Named, not a bare arrow — a reader should know what they are clicking.
  await expect(prev.locator('.ch-poststep__title')).not.toBeEmpty();
  await expect(prev).toHaveAttribute('href', /.+/);

  // Newest story: there is nothing newer, so that half is not drawn.
  await expect(steps.locator('.ch-poststep--next')).toHaveCount(0);
});

// The featured story was a white card and the five below it sat bare on the
// page background, so the grid read as loose text. Their excerpts also ran to
// whatever length the post had, which stretched every card in a row to match
// the longest one.
test('@preview the post cards are cards, and a row of them is level', async ({ page }) => {
  await page.setViewportSize({ width: 1400, height: 1000 });
  await page.goto('?clubhouse_page=news');
  // The grid reveals on scroll; measuring it while it is still faded in gives
  // heights that are real but a screenshot nobody would recognise.
  await page.locator('.ch-newsgrid').scrollIntoViewIfNeeded();

  const cards = page.locator('.ch-postcard__link');
  await expect(cards.first()).toBeVisible();

  const seen = await page.evaluate(() => {
    const links = [...document.querySelectorAll('.ch-postcard__link')];
    const cs = getComputedStyle(links[0]);
    const rows = {};
    for (const l of links) {
      const box = l.getBoundingClientRect();
      const top = Math.round(box.top);
      (rows[top] ||= []).push(Math.round(box.height));
    }
    return {
      background: cs.backgroundColor,
      sectionBackground: getComputedStyle(document.querySelector('.ch-newsgrid')).backgroundColor,
      borderWidth: parseFloat(cs.borderTopWidth),
      radius: parseFloat(cs.borderTopLeftRadius),
      clamp: getComputedStyle(document.querySelector('.ch-postcard__excerpt')).webkitLineClamp,
      rows: Object.values(rows),
    };
  });

  // Dressed like the featured card above it, not like bare text.
  expect(seen.borderWidth, 'post cards have no border').toBeGreaterThan(0);
  expect(seen.radius, 'post cards have square corners').toBeGreaterThan(0);
  expect(seen.background).not.toBe(seen.sectionBackground);

  expect(seen.clamp, 'excerpts are not cut to a fixed number of lines').toBe('3');

  for (const heights of seen.rows) {
    expect(new Set(heights).size, `cards in a row differ in height: ${heights}`).toBe(1);
  }
});

// Every post on a real site drew one blank chip below the body, because an
// untagged post reported one nameless tag rather than none.
test('@preview an article never shows a tag chip with nothing in it', async ({ page }) => {
  await page.goto('?clubhouse_page=post');

  const tags = await page.locator('.ch-posttag').allTextContents();
  expect(tags.length).toBeGreaterThan(0);
  expect(tags.every((t) => t.trim() !== ''), `blank chips: ${JSON.stringify(tags)}`).toBe(true);
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

// Issue #218. The filter row sat a full section gap below the featured story,
// far enough that it read as a broken layout rather than a deliberate break —
// the pills floating in empty space with the story above and the rule line
// below. The gap belongs to the section flow, so it is checked against the
// looks that set it rather than a single number: every look must draw the
// filters closer under the card than it separates two unrelated sections.
for (const look of ['court-side', 'floodlight', 'members-house']) {
  test(`@preview the filters sit under the featured story in ${look}`, async ({ page }) => {
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`?clubhouse_page=news&look=${look}`);

      const measured = await page.evaluate(() => {
        // The reveal animation holds a section 24px low until it scrolls into
        // view, and it slides rather than jumps — measuring mid-slide reads the
        // animation as part of the gap. Dropping the class is what reveal.js
        // itself does when it gives up, and it settles the section instantly.
        document.querySelectorAll('.ch-reveal').forEach((el) => el.classList.remove('ch-reveal'));
        const px = (v) => parseFloat(getComputedStyle(document.documentElement).getPropertyValue(v));
        const card = document.querySelector('.ch-featured__card').getBoundingClientRect();
        const bar = document.querySelector('.ch-newsgrid__bar').getBoundingClientRect();
        return { gap: bar.top - card.bottom, flow: px('--flow-lg') };
      });

      expect(
        measured.gap,
        `${look} at ${width}px leaves the filters adrift under the featured story`,
      ).toBeLessThan(measured.flow);
      expect(measured.gap, `${look} at ${width}px has the filters crowding the card`).toBeGreaterThanOrEqual(24);
    }
  });
}

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

// The demo source can only prove the shape of the control. Whether real
// WordPress finds the neighbours is a different question, and the fixture posts
// global-setup seeds — one either side of a known story — are what answer it.
test('@wordpress a real post links to the stories either side of it', async ({ page }) => {
  await page.goto('/clubhouse-post-fixture/');

  const steps = page.locator('.ch-poststeps');
  await expect(steps).toHaveCount(1);

  // Seeded dates put "older" before the fixture and "newer" after it.
  await expect(steps.locator('.ch-poststep--prev .ch-poststep__title')).toHaveText('Clubhouse post older');
  await expect(steps.locator('.ch-poststep--next .ch-poststep__title')).toHaveText('Clubhouse post newer');

  await steps.locator('.ch-poststep--prev').click();
  await expect(page.locator('.ch-posthead__title')).toHaveText('Clubhouse post older');
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
