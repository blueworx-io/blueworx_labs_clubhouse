const { test, expect } = require('@playwright/test');

// @wordpress only: these are wp-admin screens, and the DB-free preview has
// neither wp-admin nor roles.
//
// Signed in as the owner, not an administrator: an administrator holds every
// capability WordPress has and so can never notice a role missing one.
//
// The six collections used to be edited through a "Details" box bolted onto
// WordPress's own post screen. Each has its own editor now, reached from its
// own list — which is where somebody looking for a fixture looks.

const OWNER = { login: 'clubowner', pass: 'owner-test-pw' };

async function signInAsOwner(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', OWNER.login);
  await page.fill('#user_pass', OWNER.pass);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function mounted(page) {
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

/** The first row of a collection's own list, and the address its Edit points at. */
async function firstRecord(page, type) {
  await page.goto(`/wp-admin/edit.php?post_type=${type}`, { waitUntil: 'domcontentloaded' });
  const link = page.locator('#the-list a.row-title').first();
  await expect(link).toBeVisible({ timeout: 30_000 });
  return { href: await link.getAttribute('href'), title: (await link.innerText()).trim() };
}

test.describe('@wordpress collection editors', () => {
  test('a list opens its record in the collection editor, not the post screen', async ({ page }) => {
    await signInAsOwner(page);

    const { href } = await firstRecord(page, 'clubhouse_team');
    expect(href, 'Edit still went to WordPress own post screen').toContain('page=clubhouse-edit-clubhouse_team');

    await page.goto(href, { waitUntil: 'domcontentloaded' });
    await mounted(page);
  });

  test('every collection has an editor an owner can open', async ({ page }) => {
    test.slow();
    await signInAsOwner(page);

    for (const type of [
      'clubhouse_sport',
      'clubhouse_team',
      'clubhouse_fixture',
      'clubhouse_event',
      'clubhouse_sponsor',
      'clubhouse_person',
    ]) {
      const { href } = await firstRecord(page, type);
      await page.goto(href, { waitUntil: 'domcontentloaded' });
      await mounted(page);
    }
  });

  test('a change saves and reaches the site', async ({ page }) => {
    test.slow();
    await signInAsOwner(page);

    const { href } = await firstRecord(page, 'clubhouse_team');
    await page.goto(href, { waitUntil: 'domcontentloaded' });
    await mounted(page);

    // Something different every time: typing the value a field already holds
    // leaves the screen clean and Save rightly disabled.
    const league = `Division ${Date.now() % 1000}`;
    await page.locator('#league').fill(league);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await mounted(page);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#league')).toHaveValue(league, { timeout: 30_000 });

    // And it is on the site, not only in the editor.
    await page.goto('/teams/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(league);
  });

  test('the collections list keeps the columns a club reads it by', async ({ page }) => {
    await signInAsOwner(page);
    await page.goto('/wp-admin/edit.php?post_type=clubhouse_fixture', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('th#clubhouse_matchup')).toBeVisible();
    await expect(page.locator('th#clubhouse_result')).toBeVisible();
  });

  /** One Clubhouse menu, not a second one beside it. */
  test('there is no Collections menu of its own', async ({ page }) => {
    await signInAsOwner(page);
    await page.goto('/wp-admin/index.php', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#adminmenu a[href="admin.php?page=clubhouse-content"]')).toHaveCount(0);
    await expect(page.locator('#adminmenu')).toContainText('Teams');
  });
});
