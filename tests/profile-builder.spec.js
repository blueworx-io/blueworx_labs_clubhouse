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

// The Clubhouse Setup screen calls wp_enqueue_media(), which is ~200 requests
// through a single-threaded php -S. Loading it is the expensive thing here, so
// the builder is driven once, in one test, rather than three times to define
// three fields — those are seeded by global-setup.js instead.
//
// Only the screen's own JS puts .is-active on a panel, and on this server that
// JS can be a long way behind DOMContentLoaded. A plain click() waits for a
// button that is still display:none and eventually gives up; dispatching once
// can land before the handler is attached and do nothing at all. So: keep
// asking until the panel is actually open.
async function showMembersTab(page) {
  const tab = page.locator('.clubhouse-tab[data-tab="members"]');
  const panel = page.locator('.clubhouse-panel[data-panel="members"]');
  await tab.waitFor({ state: 'attached' });
  await expect
    .poll(
      async () => {
        await tab.dispatchEvent('click').catch(() => {});
        return panel.evaluate((el) => el.classList.contains('is-active')).catch(() => false);
      },
      { timeout: 60_000, intervals: [250, 500, 1000], message: 'the Members tab never opened' }
    )
    .toBe(true);
}

async function openSetupMembers(page) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await showMembersTab(page);
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

    await expect(page.locator('[name="clubhouse_profile_field[0][key]"]')).toHaveValue('shirt_size');
    await expect(page.locator('[name="clubhouse_profile_field[1][key]"]')).toHaveValue('squad_number');
    await expect(page.locator('[name="clubhouse_profile_field[2][key]"]')).toHaveValue('notes');
    // Every kind of answer, and every setting for who fills it in.
    await expect(page.locator('[name="clubhouse_profile_field[0][type]"] option')).toHaveCount(7);
    await expect(page.locator('[name="clubhouse_profile_field[0][who]"] option')).toHaveCount(3);
    // One blank row past the end, carrying no key — the next field.
    await expect(page.locator('[name="clubhouse_profile_field[3][label]"]')).toHaveValue('');
    await expect(page.locator('[name="clubhouse_profile_field[3][key]"]')).toHaveCount(0);
  });

  test('an owner adds a field and then removes it', async ({ page }) => {
    test.slow();
    await signInAsAdmin(page);
    await openSetupMembers(page);

    await page.fill('[name="clubhouse_profile_field[3][label]"]', 'Dietary needs');
    await page.selectOption('[name="clubhouse_profile_field[3][type]"]', 'text');
    await page.selectOption('[name="clubhouse_profile_field[3][who]"]', 'member');
    await submitAdminForm(page, 'button[name="clubhouse_setup_submit"]');

    await showMembersTab(page);
    // Saved, and given a permanent key of its own from the label.
    await expect(page.locator('[name="clubhouse_profile_field[3][key]"]')).toHaveValue('dietary_needs');

    // Removing it takes it off the screen. The other three are untouched.
    //
    // force: this screen never sits still long enough for Playwright's
    // actionability check once the media library's assets are trickling in
    // behind it, and what is under test is that removing a field works — not
    // that a button nothing overlaps is un-overlapped.
    await submitAdminForm(page, 'button[name="clubhouse_profile_field_remove"][value="3"]');
    await showMembersTab(page);
    await expect(page.locator('[name="clubhouse_profile_field[3][key]"]')).toHaveCount(0);
    await expect(page.locator('[name="clubhouse_profile_field[0][key]"]')).toHaveValue('shirt_size');
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
