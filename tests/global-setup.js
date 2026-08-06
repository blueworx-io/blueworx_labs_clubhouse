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

  // Also seeds a page standing in for SureCart's customer dashboard: a page this
  // plugin does not own, carrying the page template slug External_Chrome keys
  // off. CI has no SureCart, and installing it to assert our own wrapper would
  // be testing SureCart. The template slug IS the contract, so a page that
  // declares it is the honest fixture.
  const php = join(tmpdir(), 'clubhouse-global-setup.php');
  writeFileSync(
    php,
    `<?php
require_once ${JSON.stringify(WP_LOAD)};
update_option( 'clubhouse_demo_active', true, true );

// menu-editor.spec.js edits the stored header menu — reset it before the run
// so every run starts from Menu::current()'s defaults, the same way demo mode
// above is seeded fresh each time rather than trusting a prior run's option.
delete_option( 'clubhouse_menu' );

$existing = get_page_by_path( 'external-chrome-fixture' );
$id = $existing instanceof WP_Post ? $existing->ID : wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_name'    => 'external-chrome-fixture',
	'post_title'   => 'External chrome fixture',
	'post_content' => '<p id="foreign-content">FOREIGN CONTENT</p>',
) );
if ( is_int( $id ) && $id > 0 ) {
	update_post_meta( $id, '_wp_page_template', 'pages/template-surecart-dashboard.php' );
}

// A real blog post, for the news story a visitor opens from the News page. It
// carries no template slug and belongs to no plugin — exactly the page that
// used to render as bare theme output with no header, footer or way back.
$post = get_page_by_path( 'clubhouse-post-fixture', OBJECT, 'post' );
$post_id = $post instanceof WP_Post ? $post->ID : wp_insert_post( array(
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_name'    => 'clubhouse-post-fixture',
	'post_title'   => 'Clubhouse post fixture',
	'post_content' => '<p id="post-content">POST CONTENT</p>',
) );

echo ( get_option( 'clubhouse_demo_active' ) && is_int( $id ) && $id > 0 && is_int( $post_id ) && $post_id > 0 ) ? "on" : "off";
`
  );
  const res = spawnSync('php', [php], { encoding: 'utf8' });
  rmSync(php, { force: true });

  if (res.status !== 0 || res.stdout.trim() !== 'on') {
    // Fail loudly. Continuing would produce a wall of demo-spec failures whose
    // cause is this, not the plugin.
    throw new Error(
      `global-setup: could not seed demo mode and the external-page fixture (exit ${res.status}). ` +
        `stdout=${res.stdout?.trim()} stderr=${res.stderr?.trim()}`
    );
  }
  console.log('global-setup: demo mode on, external-page fixture seeded.');
};
