#!/usr/bin/env node
// Drives the real-WordPress harness for this plugin on its own derived ports.
//
//   node bin/wp-test.mjs up     provision + start local WordPress
//   node bin/wp-test.mjs test   run the Playwright suite against it
//   node bin/wp-test.mjs down   stop it
//
// This wrapper exists so the port arithmetic lives in one place (bin/dev-ports.js)
// and so the commands are identical on Windows and POSIX — npm scripts run under
// cmd.exe on Windows, where `$(...)` command substitution does not exist.

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { wordpressPort, slug } = require('./dev-ports.js');

// The harness is a foundation script, checked out as a sibling repo. Resolved
// rather than assumed so the failure is a clear message, not a stack trace.
const HARNESS = '../bluegroup_core_foundation/scripts/wp-test-env.mjs';

const command = process.argv[2] ?? 'up';
const port = wordpressPort();
const baseUrl = `http://127.0.0.1:${port}`;

if (!existsSync(HARNESS)) {
  console.error(
    `Harness not found at ${HARNESS}\n` +
      'Clone blueworx-io/bluegroup_core_foundation alongside this repo.'
  );
  process.exit(1);
}

const run = (cmd, args, env) => {
  const res = spawnSync(cmd, args, { stdio: 'inherit', shell: false, env: { ...process.env, ...env } });
  if (res.error) {
    console.error(res.error.message);
    process.exit(1);
  }
  process.exitCode = res.status ?? 1;
};

if (command === 'up') {
  run('node', [HARNESS, 'up', '--plugin', '.', '--slug', slug, '--port', String(port)]);
} else if (command === 'down') {
  run('node', [HARNESS, 'down', '--port', String(port)]);
} else if (command === 'test') {
  // PLAYWRIGHT_BASE_URL is what tells playwright.config.js not to boot the
  // preview server and to drop to a single worker.
  // No spec filtering here: the config's two projects route each spec to the
  // harness that can serve it (@preview → the preview, everything else → this
  // WordPress instance), so the whole suite still runs.
  console.log(`WordPress specs → ${baseUrl}; @preview specs → the PHP preview.`);

  // Resolve Playwright's CLI and run it on node directly. Going through `npx`
  // means spawning a .cmd shim on Windows, which node refuses without a shell
  // (EINVAL) — and adding a shell would then require quoting every argument.
  run('node', [require.resolve('@playwright/test/cli'), 'test', ...process.argv.slice(3)], {
    PLAYWRIGHT_BASE_URL: baseUrl,
    WP_ADMIN_USER: 'admin',
    WP_ADMIN_PASS: 'wptest-admin-pw',
  });
} else {
  console.error(`Unknown command "${command}". Use up, test, or down.`);
  process.exit(1);
}
