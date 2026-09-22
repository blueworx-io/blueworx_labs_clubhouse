const { test, expect } = require('@playwright/test');
const { spawnSync } = require('node:child_process');
const { existsSync, writeFileSync, rmSync } = require('node:fs');
const { join, resolve } = require('node:path');
const { tmpdir } = require('node:os');
const { ADMIN_USER, ADMIN_PASS } = require('./helpers/credentials');
const { hasShop } = require('./helpers/shop');

// @wordpress only, and only with a shop: what a club looks like the day
// BlueWorx Labs is switched off — by mistake, or before it has been
// installed on a site that already runs Clubhouse.
//
// Labs draws the member area, the checkout and the thank-you page now
// (includes/store/class-labs-store.php is the seam). Without it, Clubhouse
// must degrade honestly rather than fatal: the admin is told what to install,
// a member gets a plain "not available" screen instead of a blank one, and
// the shop's own checkout content is left to render on its own.
//
// Labs is switched off through WordPress's own functions rather than the
// Plugins screen: this plugin declares `Requires Plugins: blueworx-labs-
// wordpress`, and WordPress 6.5+ disables the Deactivate link of a plugin
// that an active plugin requires. deactivate_plugins() has no such guard.
// Silent both ways — no activation or deactivation hooks — so nothing Labs
// tears down on the way out (its support-access roles, say) is disturbed;
// the only thing that changes is whether Labs' code loads.
//
// Needs the harness this repo provisions (.wp-test/wp): the switch is a PHP
// call against that install, so a run pointed at any other WordPress skips.
const WP_LOAD = resolve('.wp-test/wp/wp-load.php');
const LABS = 'blueworx-labs-wordpress/blueworx-labs-wordpress.php';

test.beforeEach(async ({ page }) => {
  test.skip(!existsSync(WP_LOAD), `no local harness at ${WP_LOAD} — run npm run wp:up`);
  test.skip(!(await hasShop(page)), 'no shop installed — run npm run wp:shop');
});

async function signInAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', ADMIN_USER);
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

/** Runs a few lines of PHP inside the harness's WordPress, and returns stdout. */
function wp(body) {
  const file = join(tmpdir(), `clubhouse-labs-missing-${process.pid}.php`);
  writeFileSync(
    file,
    `<?php
define( 'WP_USE_THEMES', false );
require ${JSON.stringify(WP_LOAD)};
require_once ABSPATH . 'wp-admin/includes/plugin.php';
${body}
`,
    'utf8'
  );
  try {
    const res = spawnSync('php', [file], { encoding: 'utf8' });
    if (res.status !== 0) {
      throw new Error(`php exited ${res.status}: ${res.stdout}${res.stderr}`);
    }
    return res.stdout.trim();
  } finally {
    rmSync(file, { force: true });
  }
}

function deactivateLabs() {
  const out = wp(`
deactivate_plugins( ${JSON.stringify(LABS)}, true );
echo is_plugin_active( ${JSON.stringify(LABS)} ) ? 'still active' : 'off';
`);
  if (out !== 'off') throw new Error(`could not switch Labs off: ${out}`);
}

function activateLabs() {
  const out = wp(`
$res = activate_plugin( ${JSON.stringify(LABS)}, '', false, true );
if ( is_wp_error( $res ) ) {
	echo 'FAILED: ' . $res->get_error_message();
	exit( 1 );
}
echo is_plugin_active( ${JSON.stringify(LABS)} ) ? 'on' : 'still off';
`);
  if (out !== 'on') throw new Error(`could not switch Labs back on: ${out}`);
}

test('without BlueWorx Labs the club degrades honestly, and nothing fatals @wordpress', async ({
  page,
}) => {
  // Three page loads and two PHP round trips — slow by nature, not by
  // failure. The harness carries the budget (see playwright.config.js).
  await signInAsAdmin(page);

  deactivateLabs();
  try {
    // The admin is told what is missing, on every admin screen.
    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.notice', { hasText: 'BlueWorx Labs' })).toBeVisible();

    // A member gets a plain screen saying so — a real page, not a blank one
    // and not a PHP error where their account should be.
    const dashboard = await page.goto('/member-dashboard/', { waitUntil: 'domcontentloaded' });
    expect(dashboard.status()).toBe(200);
    await expect(page.locator('body')).toContainText('not available');
    await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught|Warning:/);
    await expect(page.locator('.blueworx-store')).toHaveCount(0);

    // The checkout is the shop's own page again: its content still renders,
    // and nobody draws a frame around it.
    const checkout = await page.goto('/checkout-fixture/', { waitUntil: 'domcontentloaded' });
    expect(checkout.status()).toBe(200);
    await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
    await expect(page.locator('.blueworx-checkout')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught|Warning:/);
  } finally {
    // Always put Labs back: it is site-wide, and every member-area spec after
    // this one would otherwise be looking at a club with no member area.
    activateLabs();
  }

  // Proves the switch back worked, not just that it was asked for.
  await page.goto('/member-dashboard/');
  await expect(page.locator('.bw-admin.blueworx-store')).toHaveCount(1);
});
