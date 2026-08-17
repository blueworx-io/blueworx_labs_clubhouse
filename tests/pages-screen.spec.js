const { test, expect } = require('@playwright/test');

// @wordpress only: Content → Pages lives in wp-admin and edits stored options,
// neither of which the DB-free preview has. These specs change what a page is
// made of, so each one puts back what it moved and none of them depends on
// another having run — the wordpress project is already non-parallel, but a
// spec that only passes second is a spec that cannot be run on its own.
//
// Clicks are forced, as on the Clubhouse setup screen: this screen loads the
// club's own webfonts, so Playwright's "stable" wait never converges even
// though the control has been sitting still since first paint.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function openPages(page) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-pages&club_page=about', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.clubhouse-pages')).toBeVisible();
}

/** The block names listed for the page being edited, in render order. */
async function blockNames(page) {
  return page.locator('.clubhouse-table tbody tr th .clubhouse-table__name').allTextContents();
}

/** The row for one block. Used for assertions, which then retry past the reload. */
function blockRow(page, name) {
  return page.locator(`.clubhouse-table tbody tr:has(.clubhouse-table__name:text-is("${name}"))`);
}

async function removeBlock(page, name) {
  await page.click(`tr:has(.clubhouse-table__name:text-is("${name}")) button:has-text("Remove")`, { force: true });
}

test('taking a block off a page removes it from the site but keeps it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await openPages(page);

  // A named block rather than "whichever is first": the point is that this one
  // comes off and goes back, and a shifting target hides a failure.
  const target = 'About · Committee';
  await expect(blockRow(page, target)).toBeVisible();

  await removeBlock(page, target);
  await expect(page.locator('.notice-success')).toContainText('still in your blocks');
  await expect(blockRow(page, target)).toHaveCount(0);
  expect(await (await page.request.get('/about/')).text()).not.toContain('about.committee');

  // Off the page, not gone: the picker still offers it back.
  await page.selectOption('#clubhouse-pages-add', { label: target });
  await page.click('button:has-text("Add to this page")', { force: true });
  await expect(blockRow(page, target)).toBeVisible();
});

test('a page switched off leaves the site, and comes back @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await openPages(page);

  const save = '.clubhouse-step:has-text("Is this page on the site?") button:has-text("Save")';

  await page.uncheck('input[name="clubhouse_page_enabled"]', { force: true });
  await page.click(save, { force: true });
  await expect(page.locator('.clubhouse-pages__page[aria-current="page"]')).toContainText('Off the site');

  // The front end follows: the address no longer serves the club's About page.
  // Asserted on the title rather than the markup — the plugin still enqueues its
  // chrome onto whatever WordPress falls back to, so the body is not empty.
  expect(await (await page.request.get('/about/')).text()).not.toContain('<title>About');

  await page.check('input[name="clubhouse_page_enabled"]', { force: true });
  await page.click(save, { force: true });
  await expect(page.locator('.clubhouse-pages__page[aria-current="page"]')).not.toContainText('Off the site');
  expect(await (await page.request.get('/about/')).text()).toContain('<title>About');
});

test('a new block lands where its kind belongs, not at the bottom @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await openPages(page);

  await page.selectOption('#clubhouse-pages-add', 'new:hero');
  await page.click('button:has-text("Add to this page")', { force: true });
  await expect(page.locator('.notice-success')).toContainText('Content → Blocks');

  // Straight after the page's own hero — not last, which is where a type's rank
  // alone would put it on a page numbered 10, 20, 30 by the migration.
  const names = await blockNames(page);
  expect(names.indexOf('About Hero')).toBe(names.indexOf('About · Hero') + 1);
  expect(names.indexOf('About Hero')).toBeLessThan(names.length - 1);

  await removeBlock(page, 'About Hero');
  await expect(blockRow(page, 'About Hero')).toHaveCount(0);
});
