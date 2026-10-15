// tests/playtomic-export.spec.js
const { test, expect } = require('@playwright/test');
const { readFileSync } = require('node:fs');
const { ADMIN_PASS } = require('./helpers/credentials');
const { targetingWordPress } = require('./harness');

// The Playtomic export: an administrator saves a venue, reviews the members
// table, ticks the seeded member, downloads the CSV, and sees them marked.
// Needs real WordPress (users, user meta, admin-post); the preview has none.

test.beforeEach(async ({ page }) => {
  test.skip(!targetingWordPress(), 'needs WordPress');
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeAttached({ timeout: 60_000 });
});

const SCREEN = '/wp-admin/admin.php?page=clubhouse-playtomic';

test.describe.serial('playtomic export', () => {
  test('settings save and a venue row appears in the headers', async ({ page }) => {
    test.slow();
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h1.bw-pagehead__h1')).toHaveText('Playtomic');

    await page.fill('input[name="clubhouse_playtomic[client_id]"]', 'test-client');
    await page.fill('input[name="clubhouse_playtomic[venues][0][id]"]', 'ten-a');
    await page.fill('input[name="clubhouse_playtomic[venues][0][name]"]', 'North');
    await page.fill('input[name="clubhouse_playtomic[venues][0][club]"]', 'Club North');
    await page.fill('input[name="clubhouse_playtomic[venues][0][category]"]', 'Founding');
    await page.locator('.clubhouse-playtomic__settings button.bw-btn--primary').click({ force: true });
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.bw-notice')).toContainText('Settings saved.');
    await expect(page.locator('input[name="clubhouse_playtomic[venues][0][id]"]')).toHaveValue('ten-a');
    await expect(page.locator('input[name="clubhouse_playtomic[venues][1][id]"]')).toHaveValue('');
  });

  test('the member is listed, ticked, downloaded and marked', async ({ page }) => {
    test.slow();
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    const row = page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' });
    await expect(row).toBeVisible();
    await expect(row.locator('input[type="checkbox"]')).toBeChecked();
    await expect(row).toContainText('0412 345 678');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.clubhouse-playtomic__export button[type="submit"]').click({ force: true }),
    ]);
    expect(download.suggestedFilename()).toMatch(/^playtomic-members-\d{4}-\d{2}-\d{2}\.csv$/);
    const csv = readFileSync(await download.path(), 'utf8');
    const lines = csv.split('\r\n');
    expect(lines[0]).toBe('name,email,phone_number,gender,birthdate,CAT_IMP-Club North-cat_name,CAT_IMP-Club North-exp_date,CAT_IMP-Club North-benefit_price_id,CAT_IMP-Club North-next_due_date');
    const member = lines.find((l) => l.includes('member@club.test'));
    expect(member).toBeTruthy();
    expect(member).toContain(',+61412345678,');
    expect(member).toMatch(/,Founding,\d{4}-\d{2}-\d{2},,$/);

    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' })).toHaveCount(0);

    await page.goto(SCREEN + '&playtomic_filter=all', { waitUntil: 'domcontentloaded' });
    const again = page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' });
    await expect(again).toBeVisible();
    await expect(again).toContainText(/\d{4}-\d{2}-\d{2}/);
    await expect(again.locator('input[type="checkbox"]')).not.toBeChecked();

    await again.locator('a', { hasText: 'Clear export mark' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.bw-notice')).toContainText('Export mark cleared.');
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' })).toBeVisible();
  });

  test('test connection with made-up credentials fails in plain words', async ({ page }) => {
    test.slow();
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="clubhouse_playtomic[client_id]"]', 'test-client');
    await page.fill('#playtomic-secret', 'not-a-real-secret');
    await page.locator('.clubhouse-playtomic__settings button.bw-btn--secondary').click({ force: true });
    await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
    await expect(page.locator('.bw-notice')).toContainText('Could not connect.');
    await expect(page.locator('.clubhouse-playtomic__error')).toContainText(/Playtomic (did not accept|could not be reached|Could not reach)/);
    const html = await page.content();
    expect(html).not.toContain('not-a-real-secret');
  });
});
