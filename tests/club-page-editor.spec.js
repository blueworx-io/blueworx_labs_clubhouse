const { test, expect } = require('@playwright/test');

// @wordpress only: a club page's words live on the page's own post now, so
// this needs a real page and a real record — neither of which the DB-free
// preview has.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

// The page's id, read from the Pages list rather than from an env var — the
// harness creates the pages itself, so nothing outside it knows the ids.
async function pageId(page, title) {
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');
  const row = page
    .locator('#the-list tr', { has: page.locator('a.row-title', { hasText: new RegExp(`^${title}$`) }) })
    .first();
  const id = ((await row.getAttribute('id')) || '').replace('post-', '');
  expect(id, `no page called "${title}"`).toMatch(/^\d+$/);
  return id;
}

async function openEditor(page, area, id) {
  await page.goto(`/wp-admin/admin.php?page=clubhouse-page-${area}&id=${id}`, {
    waitUntil: 'domcontentloaded',
  });
  await mounted(page);
}

/**
 * The editor is a JS app, and it does not exist until its bundle has run.
 * `php -S` answers one request at a time, so the wait is generous on purpose —
 * see playwright.config.js on why the budget belongs to the harness.
 */
async function mounted(page) {
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

/** One panel on the open editor, found by the heading a club would read. */
const panel = (page, title) => page.locator('section.bw-card').filter({ has: page.getByRole('heading', { name: title, exact: true }) });

/**
 * A panel's Shown switch. Located as the panel's own checkbox rather than by
 * its label: the library renders the switch as a styled span with the real
 * input behind it, which getByLabel('Shown') does not resolve. A panel has
 * exactly one checkbox — the switch — so this cannot pick up anything else.
 */
const shownSwitch = (page, title) => panel(page, title).locator('input[type="checkbox"]');

test.describe('@wordpress club page editor', () => {
  test('a change wakes the save bar, survives a tab switch, and saves clean', async ({ page }) => {
    await signIn(page);
    await openEditor(page, 'about', await pageId(page, 'About'));

    // New every run. The save bar only wakes for a real change, so re-typing
    // what the last run left would leave Save disabled and prove nothing.
    const words = `Crewe Vagrants ${Date.now()}`;
    const heading = page.locator('#hero_title_lead');
    await heading.fill(words);
    await expect(page.locator('.bw-savebar')).not.toContainText('Everything is saved');

    // Away and back: an unsaved change is the editor's to hold on to, and
    // losing it here is how somebody loses an afternoon's writing.
    await page.getByRole('tab', { name: /Publish/ }).click();
    await page.getByRole('tab', { name: /Content/ }).click();
    await expect(heading).toHaveValue(words);

    await page.getByRole('button', { name: 'Save changes' }).click();
    await mounted(page);

    // And it is on the page, not just in the browser.
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#hero_title_lead')).toHaveValue(words);
  });

  /**
   * Switches the panel both ways and checks the page after each, rather than
   * assuming which way it starts. A run that fails part-way through leaves the
   * switch wherever it got to, and a test that assumed "on" would then fail on
   * its first line for the rest of the day — reporting the harness, not the
   * plugin.
   */
  test('a section switched off on its own panel leaves the page', async ({ page }) => {
    await signIn(page);
    const id = await pageId(page, 'About');

    await openEditor(page, 'about', id);
    const wasOn = await shownSwitch(page, 'Values').isChecked();

    // Away from wherever it started, then back — so each pass is a real change
    // and the save bar has something to save.
    for (const on of [!wasOn, wasOn]) {
      await shownSwitch(page, 'Values').setChecked(on);
      await page.getByRole('button', { name: 'Save changes' }).click();
      await mounted(page);

      await page.goto('/about/');
      await expect(
        page.locator('#ch-about-values'),
        on ? 'switching the panel on left the section off the page' : 'switching the panel off left the section on the page',
      ).toHaveCount(on ? 1 : 0);

      await openEditor(page, 'about', id);
    }
  });

  test('global content is the one editor with a menu item of its own', async ({ page }) => {
    await signIn(page);
    await page.goto('/wp-admin/admin.php?page=clubhouse-global-content', {
      waitUntil: 'domcontentloaded',
    });
    await mounted(page);
    await expect(panel(page, 'Cookie notice')).toHaveCount(1);

    // The fourteen page editors are reached from the Pages list, so none of
    // them may add a second list of pages to the Clubhouse menu.
    const menu = page.locator('#adminmenu');
    await expect(menu.getByRole('link', { name: 'Global content' })).toHaveCount(1);
    await expect(menu.getByRole('link', { name: 'About', exact: true })).toHaveCount(0);
  });
});
