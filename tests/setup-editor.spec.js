const { test, expect } = require('@playwright/test');

// @wordpress only: Setup is a wp-admin screen, and the DB-free preview has
// neither wp-admin nor roles.
//
// Signed in as the owner throughout, never as an administrator. An
// administrator holds every capability WordPress has and so can never notice a
// role missing one — which is how deleting the Club Pages screen locked owners
// out of every page and no spec caught it.
//
// The owner and content editor users are seeded by tests/global-setup.js.

const OWNER = { login: 'clubowner', pass: 'owner-test-pw' };

async function signIn(page, user) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', user.login);
  await page.fill('#user_pass', user.pass);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function openSetup(page) {
  await page.goto('/wp-admin/admin.php?page=clubhouse-setup', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.bw-savebar')).toBeVisible({ timeout: 30_000 });
}

async function openTab(page, label) {
  await page.locator('.bw-tab', { hasText: label }).first().click();
}

test.describe('@wordpress Clubhouse Setup', () => {
  test('an owner gets the six tabs, in order', async ({ page }) => {
    await signIn(page, OWNER);
    await openSetup(page);

    // The design system uppercases a tab and appends its panel count, so the
    // rendered text is 'BASE LOOK & BRANDING2'. Compare the words only.
    const labels = await page.locator('.bw-tab').allInnerTexts();
    const trimmed = labels.map((l) => l.split('\n')[0].replace(/\d+$/, '').trim().toUpperCase());

    expect(trimmed.slice(0, 5)).toEqual([
      'BASE LOOK & BRANDING',
      'VISIBILITY',
      'MENU',
      'MEMBERS',
      'SETTINGS',
    ]);
  });

  test('changing a field wakes the save bar, and the change survives a tab switch', async ({ page }) => {
    await signIn(page, OWNER);
    await openSetup(page);

    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved');

    await page.locator('#club_name').fill('Ashwood RFC');
    await expect(page.locator('.bw-savebar')).not.toContainText('Everything is saved');

    await openTab(page, 'Visibility');
    await openTab(page, 'Base Look & Branding');
    await expect(page.locator('#club_name')).toHaveValue('Ashwood RFC');
  });

  test('a colour too pale to read on is refused, on the field, and nothing is written', async ({ page }) => {
    await signIn(page, OWNER);
    await openSetup(page);

    // Mid-grey: neither the shell's ink nor white clears AA on it.
    await page.locator('#accent').fill('#7a7a7a');
    await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();

    await expect(page.locator('.bw-field__error')).toContainText('too low in contrast', { timeout: 30_000 });
    await expect(page.locator('.bw-savebar')).not.toContainText('Everything is saved');
  });

  test('a colour that reads saves, and the screen goes clean', async ({ page }) => {
    await signIn(page, OWNER);
    await openSetup(page);

    // Away and then back to the shipped lime, for two reasons: filling a field
    // with the value it already holds leaves the screen clean and Save rightly
    // disabled, and the accent is site-wide — the colour specs read the tint
    // derived from it, so this has to end where they expect it, not merely
    // where it started. A run that ended halfway through would otherwise leave
    // the club a colour every later run inherits.
    const SHIPPED = '#c6f24e';

    for (const colour of ['#0b6fd1', SHIPPED]) {
      await page.locator('#accent').fill(colour);
      await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();
      await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
    }
  });

  test('switching a page off drafts it, and its address stops resolving', async ({ page }) => {
    await signIn(page, OWNER);
    await openSetup(page);
    await openTab(page, 'Visibility');

    await page.locator('#page_visible_contact').uncheck();
    await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();
    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });

    const response = await page.goto('/contact/', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(404);

    // And the switch still reads the page, not a copy of the flag: publish it
    // from WordPress's own Pages list and the switch follows.
    await page.goto('/wp-admin/edit.php?post_type=page&post_status=draft', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#the-list')).toContainText('Contact');

    await openSetup(page);
    await openTab(page, 'Visibility');
    await expect(page.locator('#page_visible_contact')).not.toBeChecked();

    // Put it back, so the rest of the suite finds the site it expects.
    await page.locator('#page_visible_contact').check();
    await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();
    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
  });

  test('the owner dashboard points at Setup rather than embedding it', async ({ page }) => {
    await signIn(page, OWNER);
    await page.goto('/wp-admin/index.php', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#clubhouse_setup_dashboard')).toContainText('Welcome back');
    await expect(page.locator('#clubhouse_setup_dashboard a[href*="page=clubhouse-setup"]')).toBeVisible();
    await expect(page.locator('#clubhouse_setup_dashboard .bw-savebar')).toHaveCount(0);
  });

  /**
   * Issue #307: the panel is written in the design system's markup, but the
   * dashboard was never given the design system, so it drew as bare text and
   * plain links — the first thing an owner sees, looking broken. The test
   * above could not tell, because unstyled words are still words. This one
   * asks the browser what the button actually looks like.
   */
  test('the owner dashboard is dressed, not bare @wordpress', async ({ page }) => {
    await signIn(page, OWNER);
    await page.goto('/wp-admin/index.php', { waitUntil: 'domcontentloaded' });

    const button = page.locator('#clubhouse_setup_dashboard .bw-btn').first();
    await expect(button).toBeVisible();
    const drawn = await button.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { radius: cs.borderRadius, padding: cs.paddingLeft, display: cs.display };
    });
    expect(drawn.display, 'the design system stylesheet never reached the dashboard').not.toBe('inline');
    expect(parseFloat(drawn.radius), 'the button has no corners, so nothing is styling it').toBeGreaterThan(0);
    expect(parseFloat(drawn.padding), 'the button has no padding, so nothing is styling it').toBeGreaterThan(0);

    // As a guest, though: the full-bleed chrome belongs to a screen that is
    // entirely ours, and here it would move WordPress's own widgets.
    await expect(page.locator('body.clubhouse-bw')).toHaveCount(0);
    await expect(page.locator('#wpfooter')).toBeVisible();
  });
});
