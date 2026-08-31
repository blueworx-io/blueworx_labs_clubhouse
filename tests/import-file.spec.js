const { test, expect } = require('@playwright/test');

// @wordpress only: uploading a file to a wp-admin screen, and then reading the
// value back out of the editor that owns it.
//
// The point of this spec is the round trip, not the upload. The importer used
// to keep its own list of what a file may write to, separate from the list the
// editors are built from; two lists mean drift, and drift means a file writing
// somewhere no owner can ever see or change (issue #294). One list now — so
// what an import writes must show up in an editor, and this is what proves it.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function pageId(page, title) {
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');
  const row = page
    .locator('#the-list tr', { has: page.locator('a.row-title', { hasText: new RegExp(`^${title}$`) }) })
    .first();
  const id = ((await row.getAttribute('id')) || '').replace('post-', '');
  expect(id, `no page called "${title}"`).toMatch(/^\d+$/);
  return id;
}

/** The editor is a JS app, and it does not exist until its bundle has run. */
async function mounted(page) {
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

async function upload(page, file) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-import', { waitUntil: 'domcontentloaded' });
  await page.setInputFiles('input[name="clubhouse_import_file"]', {
    name: 'clubhouse-import.json',
    mimeType: 'application/json',
    buffer: Buffer.from(JSON.stringify(file)),
  });
  await page.getByRole('button', { name: 'Review this file' }).click();
}

test.describe('@wordpress importing a file', () => {
  /**
   * Club rules is chosen deliberately: it is the one page whose eyebrow no
   * other spec reads, so applying a real import here cannot make another spec
   * pass or fail for a reason of its own. The "switch off the sections this
   * file has no content for" box is unticked for the same reason — the site
   * this suite shares must come out of it looking as it went in, bar one line
   * of text.
   */
  test('what a file writes turns up in the editor that owns it', async ({ page }) => {
    await signIn(page);

    // New every run: reading back what the last run left would prove nothing.
    const words = `Imported at ${Date.now()}`;

    await upload(page, {
      clubhouse_import: 1,
      content: {
        rules: { hero: { eyebrow: words, not_a_field: 'ignore me' } },
      },
    });

    // Named for a human, from the same declaration the editor is built from.
    await expect(page.locator('.bw-table')).toContainText('Club rules · Hero');
    await expect(page.locator('.bw-table')).toContainText('1 field');

    // A key no editor shows is refused, and the owner is told which one.
    await expect(page.locator('.bw-card', { hasText: 'Ignored' })).toContainText('rules/hero/not_a_field');

    // force, because this screen never settles: it is taller than the window,
    // and wp-admin's own scripts keep adjusting the layout as Playwright
    // scrolls down to a control, so nothing below the fold ever reports the
    // same box twice running. Both controls are visible and enabled — asserted
    // either side — so only the wait is being skipped.
    const tidyUp = page.locator('input[name="clubhouse_import_sections"]');
    await expect(tidyUp).toBeChecked();
    await tidyUp.uncheck({ force: true });
    await expect(tidyUp).not.toBeChecked();
    await page.getByRole('button', { name: 'Apply this import' }).click({ force: true });
    await expect(page.getByRole('heading', { name: 'Import complete' })).toBeVisible();

    await page.goto(`/wp-admin/admin.php?page=clubhouse-page-rules&id=${await pageId(page, 'Club rules')}`, {
      waitUntil: 'domcontentloaded',
    });
    await mounted(page);
    await expect(page.locator('#hero_eyebrow')).toHaveValue(words);
  });

  /** Nothing is saved until the owner says so. */
  test('a file that names nothing the site has is refused, not half-applied', async ({ page }) => {
    await signIn(page);

    await upload(page, {
      clubhouse_import: 1,
      content: { nowhere: { nothing: { at_all: 'x' } } },
    });

    await expect(page.locator('.bw-card', { hasText: 'Review' })).toContainText('nothing to import');
    await expect(page.locator('.bw-card', { hasText: 'Ignored' })).toContainText('nowhere/nothing');
    await expect(page.getByRole('button', { name: 'Apply this import' })).toHaveCount(0);
  });
});
