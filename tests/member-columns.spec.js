const { test, expect } = require('@playwright/test');

// @wordpress only: this is the WordPress Users screen, and what it shows
// depends on real users with real answers stored against them. The DB-free
// preview has neither.
//
// The four custom member fields are seeded by tests/global-setup.js — three
// chosen as columns, one deliberately not. The answers are set here, through
// the member's own screen, because global-setup clears them at the start of
// every run: what this asserts is what this test put there.

const MEMBER_NOTE =
  'Paid subs in cash on the night, and asked to be reminded before the away fixture in March.';

async function signInAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function memberId(page) {
  await page.goto('/wp-admin/users.php');
  const row = page.locator('#the-list tr', { has: page.locator('.username strong', { hasText: /^member$/ }) });
  const edit = await row.locator('.row-actions .edit a').first().getAttribute('href');
  const id = (edit || '').match(/user_id=(\d+)/)?.[1];
  expect(id, 'no member user in the harness').toMatch(/^\d+$/);
  return id;
}

const cell = (row, key) => row.locator(`.clubhouse_${key}`);
const rowFor = (page, login) =>
  page.locator('#the-list tr', { has: page.locator('.username strong', { hasText: new RegExp(`^${login}$`) }) });

test.describe('@wordpress custom member fields as columns', () => {
  // One flow rather than four tests: each of these needs the answers in place,
  // and a wp-admin screen against a single-threaded php -S is expensive enough
  // that re-signing in and re-saving per assertion costs more than the rest of
  // this file put together.
  test('a chosen field shows every member its answer, and sorting keeps them all', async ({ page }) => {
    await signInAsAdmin(page);
    const id = await memberId(page);

    // Answers set the way a club would set them — on the member's own screen.
    await page.goto(`/wp-admin/user-edit.php?user_id=${id}`, { waitUntil: 'domcontentloaded' });
    await page.selectOption('[name="clubhouse_profile[shirt_size]"]', 'Medium');
    await page.fill('[name="clubhouse_profile[squad_number]"]', '7');
    await page.fill('[name="clubhouse_profile[notes]"]', MEMBER_NOTE);
    await page.locator('#submit').click();
    await expect(page.locator('#message')).toBeVisible();

    await page.goto('/wp-admin/users.php');

    // The three the club chose are there, and the one it did not is not.
    await expect(page.locator('th#clubhouse_shirt_size')).toHaveText(/Shirt size/);
    await expect(page.locator('th#clubhouse_squad_number')).toHaveText(/Squad number/);
    await expect(page.locator('th#clubhouse_notes')).toHaveText(/Notes/);
    await expect(page.locator('th#clubhouse_emergency_contact')).toHaveCount(0);

    // The answers, without opening anybody.
    const member = rowFor(page, 'member');
    await expect(cell(member, 'shirt_size')).toHaveText('Medium');
    await expect(cell(member, 'squad_number')).toHaveText('7');

    // A member who has not answered says so, rather than leaving a blank cell
    // somebody has to interpret.
    await expect(cell(rowFor(page, 'admin'), 'squad_number')).toHaveText('—');

    // A long answer is cut short rather than pushing every other column off.
    const note = (await cell(member, 'notes').innerText()).trim();
    expect(note.length, note).toBeLessThan(70);
    expect(note).toContain('…');

    // The bug this pins. Ordering by a meta key the short way makes WordPress
    // INNER JOIN on it, and every member who has not answered vanishes from the
    // list — which reads as members having been deleted, on a screen where that
    // would be alarming. Counting the rows is the point: the order alone looked
    // perfectly correct while a member was missing.
    const before = await page.locator('#the-list tr').count();
    expect(before, 'the harness needs more than one member for this to mean anything').toBeGreaterThan(1);

    // Followed rather than clicked: the column heading is a link, and what it
    // links to is the whole of what sorting is. Clicking it waits on a sticky
    // table header settling under the admin bar, which is a wp-admin styling
    // question and not this one.
    const sortHref = await page.locator('th#clubhouse_squad_number a').first().getAttribute('href');
    expect(sortHref, 'the column offers no sort link').toContain('orderby=clubhouse_squad_number');
    await page.goto(sortHref);
    await expect(page.locator('#the-list tr')).toHaveCount(before);

    // And it is a number sort, so 9 comes after 7 rather than before it.
    const numbers = (await page.locator('#the-list .clubhouse_squad_number').allInnerTexts())
      .filter((t) => /^\d+$/.test(t.trim()))
      .map(Number);
    expect(numbers).toEqual([...numbers].sort((a, b) => a - b));
  });
});
