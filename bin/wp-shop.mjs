#!/usr/bin/env node
// Put SureCart into the local WordPress harness and switch it on.
//
//   node bin/wp-shop.mjs          # download (or reuse) and activate
//   node bin/wp-shop.mjs --force  # re-download even if it is already there
//
// WHY THIS EXISTS
// Half of what this plugin does only exists next to SureCart: the member area's
// panels, its edit and update journeys, the checkout links, and the member
// sign-in. The harness provisions one plugin — this one — so none of that could
// be exercised locally, and the SureCart-facing work was being written against
// source read from a downloaded zip rather than against a running shop.
//
// Deliberately NOT part of `wp:up`. The suite that runs today expects a site
// with no shop, and pages this plugin only offers alongside SureCart would
// appear the moment one existed. Opt in when the thing being worked on needs a
// shop; leave it alone otherwise.
//
// WHAT YOU STILL DO NOT GET
// A connected store. SureCart's own API is not reachable from a throwaway local
// install, so anything that talks to it — prices, checkouts, customers,
// passwordless sign-in codes — stays unavailable. What DOES work is everything
// SureCart implements in plain WordPress, which includes password sign-in
// (`wp_authenticate`) and the dashboard's block routing.

import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync, unlinkSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { slug } = require('./dev-ports.js');

const ZIP_URL = 'https://downloads.wordpress.org/plugin/surecart.zip';
const WP_DIR = resolve('.wp-test/wp');
const PLUGINS = join(WP_DIR, 'wp-content/plugins');
const TARGET = join(PLUGINS, 'surecart');
const force = process.argv.includes('--force');

if (!existsSync(WP_DIR)) {
  console.error(
    `No harness at ${WP_DIR}.\nRun "npm run wp:up" first — this adds a shop to an install that already exists.`
  );
  process.exit(1);
}

if (force && existsSync(TARGET)) {
  rmSync(TARGET, { recursive: true, force: true });
}

if (!existsSync(TARGET)) {
  const zip = join(WP_DIR, 'surecart-download.zip');
  console.log(`Downloading ${ZIP_URL} …`);
  run('curl', ['-sSL', '-o', zip, ZIP_URL]);
  console.log('Unpacking …');
  // bsdtar rather than PowerShell's Expand-Archive, for the same reason the zip
  // build insists on it: consistent behaviour across platforms.
  run(tarBin(), ['-x', '-f', zip, '-C', PLUGINS]);
  unlinkSync(zip);
} else {
  console.log('SureCart is already unpacked — activating it. Use --force to re-download.');
}

// Activation goes through WordPress itself rather than by writing the option,
// so its activation hooks run and the install ends up in the state a real one
// would be in.
const probe = join(WP_DIR, `${slug}-shop-activate.php`);
writeFileSync(
  probe,
  `<?php
define( 'WP_USE_THEMES', false );
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$res = activate_plugin( 'surecart/surecart.php', '', false, false );
if ( is_wp_error( $res ) ) {
\techo 'FAILED: ' . $res->get_error_message() . "\\n";
\texit( 1 );
}
echo 'SureCart ' . ( defined( 'SURECART_VERSION' ) ? SURECART_VERSION : '' ) . " active\\n";
echo 'Store connected: ' . ( \\SureCart::account()->id ?? 'no — local install, expected' ) . "\\n";
`,
  'utf8'
);
try {
  run('php', [probe]);
} finally {
  if (existsSync(probe)) unlinkSync(probe);
}

console.log('\nDone. The harness is disposable — "npm run wp:down" and a fresh "wp:up" clears it.');

function tarBin() {
  return process.platform === 'win32' ? `${process.env.WINDIR}\\System32\\tar.exe` : 'tar';
}

function run(cmd, args) {
  try {
    execFileSync(cmd, args, { stdio: 'inherit' });
  } catch (err) {
    console.error(`\n${cmd} failed: ${err.message}`);
    process.exit(1);
  }
}
