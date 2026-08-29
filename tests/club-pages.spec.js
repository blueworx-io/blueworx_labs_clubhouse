const { test, expect } = require('@playwright/test');

// @wordpress only: this is about rows in the database, which the DB-free
// preview does not have.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('every club page has a real page behind it @wordpress', async ({ page }) => {
  await signIn(page);

  // Read every page of the list, not just the first. WordPress paginates at 20
  // and the harness is past that, so a single load silently dropped the pages
  // sorted last — which looked exactly like a page that had never been created.
  const found = new Set();
  for (let paged = 1; paged <= 3; paged++) {
    await page.goto(`/wp-admin/edit.php?post_type=page&post_status=all&paged=${paged}`);
    const titles = await page.locator('#the-list a.row-title').allInnerTexts();
    if (titles.length === 0) break;
    titles.forEach((t) => found.add(t.trim()));
  }

  for (const title of ['About', 'Membership', 'Contact', 'News', 'Sports', 'Teams', 'Events', 'Calendar', 'Privacy', 'Terms', 'Club rules']) {
    expect(found, `no page behind "${title}"`).toContain(title);
  }
});

test('a club page renders from its own page, not the rewrite rule @wordpress', async ({ page }) => {
  await page.goto('/about/');
  await expect(page.locator('.ch-nav')).toHaveCount(1);
  const isPage = await page.evaluate(() => document.body.className.includes('page-id-'));
  expect(isPage).toBe(true);
});

test('the front page is the club home page @wordpress', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.ch-nav')).toHaveCount(1);
});

test('editing a club page lands in that page\'s own editor, not the block editor @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');
  const row = page.locator('#the-list tr', { has: page.locator('a.row-title', { hasText: /^About$/ }) }).first();
  const rowId = await row.getAttribute('id');
  const id = (rowId || '').replace('post-', '');
  expect(id).toMatch(/^\d+$/);

  // The Edit link itself points at the About page's editor, not the block editor...
  const href = await row.locator('a.row-title').getAttribute('href');
  expect(href).toContain('page=clubhouse-page-about');
  expect(href).toContain(`id=${id}`);

  // ...and so does typing the editor's address directly.
  await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);
  expect(page.url()).toContain('page=clubhouse-page-about');
  await expect(page.locator('#editor')).toHaveCount(0);
});

// The Pages list is somewhere to see club pages, not somewhere to edit them.
// Quick Edit renames and retitles a page inline, and Trash removes it — both
// break a site that routes through these pages, from a screen that looks
// harmless. An ordinary page the club made itself keeps both.
test('a club page is read-only in the Pages list @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');

  const club = page
    .locator('#the-list tr', { has: page.locator('a.row-title', { hasText: /^About$/ }) })
    .first();
  await expect(club.locator('.row-actions .inline')).toHaveCount(0);
  await expect(club.locator('.row-actions .trash')).toHaveCount(0);
  await expect(club.locator('.row-actions .edit')).toHaveCount(1);
  // And the column says which rows are ours.
  await expect(club.locator('.column-clubhouse_club_page')).toHaveText('Club page');

  // Seeded by global-setup.js — a page this plugin does not own.
  const theirs = page
    .locator('#the-list tr', { has: page.locator('a.row-title', { hasText: /^External chrome fixture$/ }) })
    .first();
  await expect(theirs.locator('.row-actions .inline')).toHaveCount(1);
  await expect(theirs.locator('.row-actions .trash')).toHaveCount(1);
  await expect(theirs.locator('.column-clubhouse_club_page')).toHaveText('');
});
