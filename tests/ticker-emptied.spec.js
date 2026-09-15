const { test, expect } = require('@playwright/test');
const { ADMIN_PASS } = require('./helpers/credentials');

// @wordpress only: the words live on the Home page's own record.
//
// Issue #320. The ticker's demo messages stand in until a club writes its
// own — but a club that deleted every message got the demo ones straight
// back, because an emptied list and a never-written one read the same. Now
// an emptied ticker is simply not drawn.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function homeId(page) {
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');
  const row = page.locator('#the-list tr', { has: page.locator('a.row-title', { hasText: /^Home$/ }) }).first();
  const id = ((await row.getAttribute('id')) || '').replace('post-', '');
  expect(id, 'no page called "Home"').toMatch(/^\d+$/);
  return id;
}

async function openHome(page) {
  await page.goto(`/wp-admin/admin.php?page=clubhouse-page-home&id=${await homeId(page)}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

// A repeater-only panel is an intro card followed by a loose block holding the rows.
const tickerPanel = (page) => page.locator('.bw-card--intro:has(.bw-card__title:text-is("Ticker")) + .bw-panel__loose');

async function save(page) {
  await page.locator('.bw-savebar button', { hasText: 'Save changes' }).click();
  await expect(page.locator('.bw-savebar')).toContainText('Everything is saved', { timeout: 30_000 });
}

test('deleting every ticker message removes the ticker, not just the words @wordpress', async ({ page }) => {
  await signIn(page);
  await openHome(page);

  const panel = tickerPanel(page);
  const rows = panel.locator('.bw-repeater__row');

  // Positive control: the demo messages are on the site while the list is
  // untouched (or after this test's own restore below).
  if ((await rows.count()) === 0) {
    await panel.locator('button', { hasText: 'Add a row' }).click();
    await rows.first().locator('input[type="text"]').first().fill('Open Day — Sat 26 Jul, 10:00–14:00');
    await save(page);
  }
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.ch-ticker')).toBeVisible();

  await openHome(page);
  while ((await rows.count()) > 0) {
    await rows.first().locator('[aria-label="Remove this row"]').click();
  }
  await save(page);

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.ch-ticker')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('1st XV promoted');

  // Put one message back so the rest of the suite sees a ticker.
  await openHome(page);
  await panel.locator('button', { hasText: 'Add a row' }).click();
  await rows.first().locator('input[type="text"]').first().fill('Open Day — Sat 26 Jul, 10:00–14:00');
  await save(page);
});
