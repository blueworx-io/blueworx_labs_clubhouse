#!/usr/bin/env node
// Put BlueWorx Labs into the local WordPress harness and switch it on, with
// every feature off except store_pages.
//
//   node bin/wp-labs.mjs          # copy/download (or reuse) and activate
//   node bin/wp-labs.mjs --force  # re-copy/re-download even if already there
//
// WHY THIS EXISTS
// ClubHouse's member area, checkout and thank-you pages are moving to Labs.
// This puts a real copy of Labs beside this plugin so that move can be
// exercised locally and in CI, the same way wp-shop.mjs puts a real SureCart
// in.
//
// SOURCE, in order of preference:
//   1. BLUEWORX_LABS_DIR — a local checkout of blueworx_labs_wordpress. Only
//      the runtime files are copied (never symlinked — a symlinked plugin
//      folder breaks plugin_basename() and the custom login path; see the
//      memory note on the duplicate-plugin symlink bug).
//   2. The pinned GitHub release zip, once one exists.
//
// Deliberately NOT part of `wp:up`, for the same reason wp-shop.mjs isn't:
// opt in when the thing being worked on needs Labs; leave it alone otherwise.

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync, unlinkSync, mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { slug } = require('./dev-ports.js');

const VERSION = '1.88.0';
const ZIP_URL = `https://github.com/blueworx-io/blueworx_labs_wordpress/releases/download/v${VERSION}/blueworx-labs-wordpress-${VERSION}.zip`;
const WP_DIR = resolve('.wp-test/wp');
const PLUGINS = join(WP_DIR, 'wp-content/plugins');
const TARGET = join(PLUGINS, 'blueworx-labs-wordpress');
const force = process.argv.includes('--force');

// Only the runtime — never dev-only directories (tests, docs, node_modules, …).
const RUNTIME_ENTRIES = [
  'blueworx-labs-wordpress.php',
  'uninstall.php',
  'readme.txt',
  'includes',
  'assets',
  'plugin-update-checker',
];

if (!existsSync(WP_DIR)) {
  console.error(
    `No harness at ${WP_DIR}.\nRun "npm run wp:up" first — this adds Labs to an install that already exists.`
  );
  process.exit(1);
}

if (force && existsSync(TARGET)) {
  rmSync(TARGET, { recursive: true, force: true });
}

if (!existsSync(TARGET)) {
  const labsDir = process.env.BLUEWORX_LABS_DIR;
  if (labsDir) {
    const source = resolve(labsDir);
    if (!existsSync(source)) {
      console.error(`BLUEWORX_LABS_DIR is set to ${source}, but that path does not exist.`);
      process.exit(1);
    }
    console.log(`Copying Labs from ${source} …`);
    mkdirSync(TARGET, { recursive: true });
    for (const entry of RUNTIME_ENTRIES) {
      const from = join(source, entry);
      if (!existsSync(from)) continue; // e.g. plugin-update-checker may not exist yet
      copyEntry(from, join(TARGET, entry));
    }
  } else {
    const zip = join(WP_DIR, 'blueworx-labs-download.zip');
    console.log(`Downloading ${ZIP_URL} …`);
    // --fail: curl exits non-zero on a 404 instead of writing the error page to
    // disk, so a missing release reports as "curl failed" (a download failure)
    // rather than surfacing later as "could not unpack" once unpack() tries to
    // open that HTML as a zip.
    run('curl', ['-sSL', '--fail', '-o', zip, ZIP_URL]);
    console.log('Unpacking …');
    // unzip -> System32 tar.exe -> bsdtar fallback, the same chain wp-shop.mjs
    // uses, for the same reason: consistent behaviour across platforms.
    unpack(zip, PLUGINS);
    unlinkSync(zip);
  }
} else {
  console.log('BlueWorx Labs is already in place — activating it. Use --force to re-copy.');
}

// Activation goes through WordPress itself rather than by writing the option,
// so its activation hooks run and the install ends up in the state a real one
// would be in.
const probe = join(WP_DIR, `${slug}-labs-activate.php`);
writeFileSync(
  probe,
  `<?php
define( 'WP_USE_THEMES', false );
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$res = activate_plugin( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php', '', false, false );
if ( is_wp_error( $res ) ) {
\techo 'FAILED: ' . $res->get_error_message() . "\\n";
\texit( 1 );
}

// Switch off every Labs feature except store_pages, so Labs' login
// relocation, site protection and admin re-skin cannot interfere with
// ClubHouse's own specs.
if ( function_exists( 'blueworx_get_feature_definitions' ) ) {
\tforeach ( array_keys( blueworx_get_feature_definitions() ) as $key ) {
\t\tupdate_option( 'blueworx_feature_' . $key, 'store_pages' === $key ? '1' : '0' );
\t}
} else {
\techo 'FAILED: blueworx_get_feature_definitions() is not defined' . "\\n";
\texit( 1 );
}

echo 'BlueWorx Labs active, store_pages only' . "\\n";
`,
  'utf8'
);
try {
  run('php', [probe]);
} finally {
  if (existsSync(probe)) unlinkSync(probe);
}

console.log('\nDone. The harness is disposable — "npm run wp:down" and a fresh "wp:up" clears it.');

function copyEntry(from, to) {
  if (process.platform === 'win32') {
    // robocopy exit codes below 8 are success (0-7); 8+ signal a real failure.
    const isDir = statIsDir(from);
    if (isDir) {
      const res = spawnSync('robocopy', [from, to, '/E'], { stdio: 'inherit' });
      if ((res.status ?? 0) >= 8) {
        console.error(`\nrobocopy failed copying ${from} (exit ${res.status})`);
        process.exit(1);
      }
    } else {
      mkdirSync(resolve(to, '..'), { recursive: true });
      const destDir = resolve(to, '..');
      const fileName = to.split(/[\\/]/).pop();
      const res = spawnSync('robocopy', [resolve(from, '..'), destDir, fileName], {
        stdio: 'inherit',
      });
      if ((res.status ?? 0) >= 8) {
        console.error(`\nrobocopy failed copying ${from} (exit ${res.status})`);
        process.exit(1);
      }
    }
  } else {
    mkdirSync(resolve(to, '..'), { recursive: true });
    run('cp', ['-R', from, to]);
  }
}

function statIsDir(path) {
  try {
    return require('node:fs').statSync(path).isDirectory();
  } catch {
    return false;
  }
}

function unpack(zip, dest) {
  // Prefer `unzip` when present, then System32's tar.exe (bsdtar) on Windows,
  // then whatever `tar` resolves to elsewhere.
  const attempts =
    process.platform === 'win32'
      ? [['unzip', ['-o', zip, '-d', dest]], [tarBin(), ['-x', '-f', zip, '-C', dest]]]
      : [
          ['unzip', ['-o', zip, '-d', dest]],
          ['tar', ['-x', '-f', zip, '-C', dest]],
        ];
  let lastErr;
  for (const [cmd, args] of attempts) {
    try {
      execFileSync(cmd, args, { stdio: 'inherit' });
      return;
    } catch (err) {
      lastErr = err;
    }
  }
  console.error(`\nCould not unpack ${zip}: ${lastErr?.message}`);
  process.exit(1);
}

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
