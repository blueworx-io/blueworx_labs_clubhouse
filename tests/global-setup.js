// Prepares a real WordPress instance before the suite runs.
//
// Only does anything when the run targets WordPress (PLAYWRIGHT_BASE_URL set by
// bin/wp-test.mjs locally, or by the foundation's ci-wordpress workflow). The
// preview harness needs none of this.
//
// Demo mode is the one piece of state the specs cannot set for themselves. It is
// a site-wide stored flag: the preview turns it on with `?demo=1`, but WordPress
// reads the option, so without seeding it the demo specs fail against real
// WordPress for a reason unrelated to what they assert. Both the local harness
// and CI install to `.wp-test/wp`, so the same seeding works in both.

const { spawnSync } = require('node:child_process');
const { existsSync, writeFileSync, rmSync } = require('node:fs');
const { join, resolve } = require('node:path');
const { tmpdir } = require('node:os');

const WP_LOAD = resolve('.wp-test/wp/wp-load.php');

module.exports = async () => {
  const targetingWordPress = !!(process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL);
  if (!targetingWordPress) return;

  if (!existsSync(WP_LOAD)) {
    // Targeting a WordPress we did not provision (a real preview host, say).
    // Not an error — just nothing here we can seed.
    console.log(`global-setup: no local WordPress at ${WP_LOAD} — skipping demo-mode seeding.`);
    return;
  }

  const php = join(tmpdir(), 'clubhouse-global-setup.php');
  writeFileSync(
    php,
    `<?php
require_once ${JSON.stringify(WP_LOAD)};
update_option( 'clubhouse_demo_active', true, true );
echo get_option( 'clubhouse_demo_active' ) ? "on" : "off";
`
  );
  const res = spawnSync('php', [php], { encoding: 'utf8' });
  rmSync(php, { force: true });

  if (res.status !== 0 || res.stdout.trim() !== 'on') {
    // Fail loudly. Continuing would produce a wall of demo-spec failures whose
    // cause is this, not the plugin.
    throw new Error(
      `global-setup: could not enable demo mode (exit ${res.status}). ` +
        `stdout=${res.stdout?.trim()} stderr=${res.stderr?.trim()}`
    );
  }
  console.log('global-setup: demo mode on.');
};
