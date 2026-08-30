const { test, expect } = require('@playwright/test');

// Issue #276: a club invents its own member fields, members fill in their own,
// and club staff see every one of them on the WordPress user screen.
//
// The member area belongs to the shop (issue #261), so these need one installed.
// CI has none — see tests/helpers/shop.js.
const { hasShop } = require('./helpers/shop');

test.beforeEach(async ({ page }) => {
  test.skip(!(await hasShop(page)), 'no shop installed — run npm run wp:shop');
});

// Same sign-in helpers as member-area-page.spec.js, reused rather than
// reinvented. The member is a subscriber seeded by global-setup.js.
// The admin bar is the one thing every signed-in destination shares. It gets a
// generous wait rather than the default five seconds: wp-admin's dashboard is a
// couple of hundred requests through a single-threaded php -S, and a slow sign
// -in is the harness being busy, not a broken login.
async function signIn(page, user, pass) {
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await page.click('#wp-submit');
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('#wpadminbar')).toBeAttached({ timeout: 60_000 });
}

const signInAsAdmin = (page) => signIn(page, 'admin', 'wptest-admin-pw');
const signInAsMember = (page) => signIn(page, 'member', 'wptest-member-pw');

// The builder is a repeater on the Setup screen's Members tab now. Its rows are
// driven once, in one test, rather than three times to define three fields —
// those are seeded by global-setup.js instead.
async function showMembersTab(page) {
  // The editor mounts itself, so nothing on the screen is real until the save
  // bar is.
  await expect(page.locator('.bw-savebar')).toBeVisible({ timeout: 60_000 });
  await page.locator('.bw-tab', { hasText: 'Members' }).first().click();
  await expect(page.locator('.bw-repeater').first()).toBeVisible();
}

async function openSetupMembers(page) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await showMembersTab(page);
}

/** One member field's row, by position. */
function fieldRow(page, i) {
  return page.locator('.bw-repeater__row').nth(i);
}

async function saveSetup(page) {
  await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

// A wp-admin screen served by a single-threaded php -S never sits still long
// enough for Playwright's actionability check: assets keep arriving and the
// layout keeps shifting, so a plain click() waits out the timeout on a button
// that is right there and perfectly clickable. Nothing here is testing whether
// a button is overlapped, so the check is skipped rather than waited on.
async function submitAdminForm(page, selector) {
  await page.locator(selector).click({ force: true });
  await page.waitForLoadState('domcontentloaded');
}

// The member's own WordPress profile screen. Found by their email address
// rather than by a link reading "member", which also matches the admin row and
// half the column headings — and picking the wrong row means writing the club's
// answers onto the wrong person, which is exactly the bug these tests exist to
// catch.
async function openMemberUserScreen(page) {
  await page.goto('/wp-admin/users.php', { waitUntil: 'domcontentloaded' });
  const link = page.locator('tr:has-text("member@club.test") a[href*="user-edit.php"]').first();
  const href = await link.getAttribute('href');
  expect(href, 'no user-edit link for member@club.test').toBeTruthy();
  await page.goto(href, { waitUntil: 'domcontentloaded' });
}

// Serial: these read and write one club's state in order.
test.describe.serial('profile builder', () => {

  test('the builder shows the club the fields it has', async ({ page }) => {
    test.slow();
    await signInAsAdmin(page);
    await openSetupMembers(page);

    await expect(fieldRow(page, 0).locator('#key-0')).toHaveValue('shirt_size');
    await expect(fieldRow(page, 1).locator('#key-1')).toHaveValue('squad_number');
    await expect(fieldRow(page, 2).locator('#key-2')).toHaveValue('notes');
    // Every kind of answer, and every setting for who fills it in. One more
    // option than the model has in each case: the library's select carries a
    // leading blank.
    await expect(fieldRow(page, 0).locator('#type-0 option')).toHaveCount(8);
    await expect(fieldRow(page, 0).locator('#who-0 option')).toHaveCount(4);
    // No blank row past the end — a row is added deliberately now.
    await expect(page.locator('.bw-repeater__row')).toHaveCount(3);
  });

  test('an owner adds a field and then removes it', async ({ page }) => {
    test.slow();
    await signInAsAdmin(page);
    await openSetupMembers(page);

    await page.locator('button', { hasText: 'Add a row' }).click();
    await fieldRow(page, 3).locator('#label-3').fill('Dietary needs');
    await fieldRow(page, 3).locator('#type-3').selectOption('text');
    await fieldRow(page, 3).locator('#who-3').selectOption('member');
    await saveSetup(page);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await showMembersTab(page);
    // Saved, and given a permanent key of its own from the label.
    await expect(fieldRow(page, 3).locator('#key-3')).toHaveValue('dietary_needs');

    // Removing it takes it off the screen. The other three are untouched.
    await fieldRow(page, 3).locator('[aria-label="Remove this row"]').click();
    await saveSetup(page);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await showMembersTab(page);
    await expect(page.locator('.bw-repeater__row')).toHaveCount(3);
    await expect(fieldRow(page, 0).locator('#key-0')).toHaveValue('shirt_size');
  });

  /**
   * The key is what every answer is stored under. The old screen carried it in
   * a hidden input; it is a visible cell now, because the library rebuilds a
   * repeater row from its declared cells alone and a key that is not one does
   * not survive a save. Renaming the question must still keep the answers.
   */
  test('renaming a question keeps the answers already given', async ({ page }) => {
    test.slow();
    await signInAsAdmin(page);
    await openSetupMembers(page);

    await fieldRow(page, 0).locator('#label-0').fill('Kit size');
    await saveSetup(page);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await showMembersTab(page);
    await expect(fieldRow(page, 0).locator('#key-0')).toHaveValue('shirt_size');

    // Put the question back, so the rest of the suite finds what it expects.
    await fieldRow(page, 0).locator('#label-0').fill('Shirt size');
    await saveSetup(page);
  });

  test('the club sets a field only it can fill in', async ({ page }) => {
    await signInAsAdmin(page);
    await openMemberUserScreen(page);
    await expect(page.locator('h2:has-text("Club details")')).toBeVisible();

    await page.fill('[name="clubhouse_profile[squad_number]"]', '9');
    await page.fill('[name="clubhouse_profile[notes]"]', 'Paid subs in cash.');
    await submitAdminForm(page, '#submit');

    await expect(page.locator('[name="clubhouse_profile[squad_number]"]')).toHaveValue('9');
  });

  test('a member fills in their own field and it saves', async ({ page }) => {
    await signInAsMember(page);
    await page.goto('/member-dashboard/?view=profile', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.clubhouse-profile')).toBeVisible();
    await page.selectOption('[name="clubhouse_profile[shirt_size]"]', 'Medium');
    await page.click('.clubhouse-profile__save');
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.clubhouse-profile__notice--success')).toBeVisible();
    await expect(page.locator('[name="clubhouse_profile[shirt_size]"]')).toHaveValue('Medium');
  });

  test('a club field is shown but the member cannot change it', async ({ page }) => {
    await signInAsMember(page);
    await page.goto('/member-dashboard/?view=profile', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.clubhouse-profile')).toContainText('Squad number');
    await expect(page.locator('.clubhouse-profile')).toContainText('9');
    await expect(page.locator('[name="clubhouse_profile[squad_number]"]')).toHaveCount(0);
  });

  test('a private field never reaches the member', async ({ page }) => {
    await signInAsMember(page);
    await page.goto('/member-dashboard/?view=profile', { waitUntil: 'domcontentloaded' });

    const html = await page.content();
    expect(html).not.toContain('Paid subs in cash.');
    expect(html).not.toContain('clubhouse_profile[notes]');
  });

  test('the member can see their own name and password on Profile', async ({ page }) => {
    await signInAsMember(page);
    await page.goto('/member-dashboard/?view=profile', { waitUntil: 'domcontentloaded' });

    // <sc-wordpress-user> is what SureCart's wordpress-account block actually
    // renders — its own custom element, not a wrapper of ours.
    const panel = page.locator('[data-view="profile"]');
    await expect(panel.locator('sc-wordpress-user')).toHaveCount(1);
    // Their own details first, then whatever the club asks them.
    const html = await panel.innerHTML();
    expect(html.indexOf('sc-wordpress-user')).toBeLessThan(html.indexOf('clubhouse-profile'));
  });

  test('the answer the member saved shows on their WordPress profile', async ({ page }) => {
    await signInAsAdmin(page);
    await openMemberUserScreen(page);

    await expect(page.locator('[name="clubhouse_profile[shirt_size]"]')).toHaveValue('Medium');
    // Every field is editable here, including the one the member never sees.
    await expect(page.locator('[name="clubhouse_profile[notes]"]')).toHaveValue('Paid subs in cash.');
  });

  test('Account still shows how the member pays', async ({ page }) => {
    await signInAsMember(page);
    await page.goto('/member-dashboard/?view=account', { waitUntil: 'domcontentloaded' });

    // Scoped to the panel: the member area draws every panel and shows one, so
    // Profile's own contents are on this page too — just not on this screen.
    const panel = page.locator('[data-view="account"]');
    // The old address still resolves rather than falling back to the overview.
    await expect(panel).toBeVisible();
    await expect(panel.locator('sc-dashboard-customer-details')).toHaveCount(1);
    await expect(panel.locator('sc-payment-methods-list')).toHaveCount(1);
    // And what moved to Profile is not also here.
    await expect(panel.locator('sc-wordpress-user')).toHaveCount(0);
    await expect(panel.locator('.clubhouse-profile')).toHaveCount(0);
  });
});
