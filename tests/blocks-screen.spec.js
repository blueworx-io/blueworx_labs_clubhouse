const { test, expect } = require('@playwright/test');

// @wordpress only: Content → Blocks lives in wp-admin and edits stored options.
// Each spec puts back what it changed, and none depends on another having run.
// Clicks are forced for the same reason as the Pages screen's — the club's
// webfonts keep Playwright's "stable" wait from converging.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function openBlock(page, id) {
  await page.goto(`/wp-admin/admin.php?page=clubhouse-blocks&block=${id}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.clubhouse-pages')).toBeVisible();
}

async function openPage(page, slug) {
  await page.goto(`/wp-admin/admin.php?page=clubhouse-pages&club_page=${slug}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.clubhouse-pages')).toBeVisible();
}

const save = () => '.clubhouse-bar button:has-text("Save")';

test('editing a block once changes every page showing it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);

  // Share the About history block onto Membership, from the Pages screen — the
  // way an owner would.
  await openPage(page, 'membership');
  await page.selectOption('#clubhouse-pages-add', { label: 'About · History' });
  await page.click('button:has-text("Add to this page")', { force: true });

  await openBlock(page, 'about-history');
  await expect(page.locator('.clubhouse-help--strong')).toContainText('About, Membership');

  await page.fill('input[name="field[heading]"]', 'Ninety years by the weir');
  await page.click(save(), { force: true });
  await expect(page.locator('.notice-success')).toContainText('all of them have changed');

  // Visited rather than fetched: PHP's built-in server is single-threaded, and
  // a side request issued while the admin page is still settling can wait on a
  // connection that is not coming.
  for (const url of ['/about/', '/membership/']) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText('Ninety years by the weir');
  }

  // Put it back: off Membership, and the heading restored.
  await openPage(page, 'membership');
  await page.click('tr:has(.clubhouse-table__name:text-is("About · History")) button:has-text("Remove")', { force: true });
});

test('a block in use cannot be deleted without being told which pages go with it @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await openBlock(page, 'about-committee');

  await page.click('.clubhouse-bar button:has-text("Delete")', { force: true });
  await expect(page.locator('.clubhouse-step--warn')).toContainText('It is on About');

  // Nothing has happened yet — the block and its page are as they were.
  expect(await (await page.request.get('/about/')).text()).toContain('about-committee');

  await page.click('a:has-text("Keep it")', { force: true });
  await expect(page.locator('.clubhouse-step--warn')).toHaveCount(0);
});

test('duplicating a block breaks the link so one page can differ @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await openBlock(page, 'about-history');

  await page.click('.clubhouse-bar button:has-text("Duplicate")', { force: true });
  await expect(page.locator('.notice-success')).toContainText('is a copy of');
  await expect(page.locator('input[name="clubhouse_blocks_name"]')).toHaveValue(/copy/);
  await expect(page.locator('.clubhouse-blocks__used')).toContainText('Not on any page yet');

  // Tidy up: the copy is on no page, so deleting it asks nothing.
  await page.click('.clubhouse-bar button:has-text("Delete")', { force: true });
  await expect(page.locator('.notice-success')).toContainText('is deleted');
});
