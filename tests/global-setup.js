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

// A second, separate page standing in for the customer dashboard itself. The
// external-chrome fixture above only proves a foreign page is left alone; the
// member area replaces the dashboard page's content entirely, so it needs its
// own fixture rather than sharing one whose whole test asserts nothing changed.
$member_existing = get_page_by_path( 'member-area-fixture' );
$member_id        = $member_existing instanceof WP_Post ? $member_existing->ID : wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_name'    => 'member-area-fixture',
	'post_title'   => 'Member area fixture',
	'post_content' => '<p id="foreign-content">FOREIGN CONTENT</p>',
) );
if ( is_int( $member_id ) && $member_id > 0 ) {
	update_post_meta( $member_id, '_wp_page_template', 'pages/template-surecart-dashboard.php' );
	// Register that page as the customer dashboard, which is the option the
	// welcome pack keys off. CI has no SureCart to write it, and the option IS
	// the contract — SureCart's own PageService builds the same name.
	update_option( 'surecart_dashboard_page_id', $member_id );
}

// A page standing in for SureCart's checkout. CI has no SureCart, and
// installing it to assert our own frame would be testing SureCart. The stored
// page id IS the contract — Commerce_Pages dresses whichever post it names.
$checkout_existing = get_page_by_path( 'checkout-fixture' );
$checkout_id       = $checkout_existing instanceof WP_Post ? $checkout_existing->ID : wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_name'    => 'checkout-fixture',
	'post_title'   => 'Checkout fixture',
	'post_content' => '<p id="shop-content">SHOP CONTENT</p>',
) );
if ( is_int( $checkout_id ) && $checkout_id > 0 ) {
	update_option( 'surecart_checkout_page_id', $checkout_id );
}

// A welcome pack for that dashboard to carry, written through the plugin's own
// store rather than a hand-built option, so the fixture cannot drift from how
// the admin screen saves.
$store = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Options_Storage() );
$store->set( 'global', 'welcome', 'heading', 'Welcome to the club' );
$store->set( 'global', 'welcome', 'body', "The gate code is on your membership card.\n\nParking is behind the pitch." );
$store->set( 'global', 'welcome', 'link_label', 'Read the handbook' );
$store->set( 'global', 'welcome', 'link_href', 'https://club.example/handbook' );

// A real blog post, for the news story a visitor opens from the News page. It
// carries no template slug and belongs to no plugin — exactly the page that
// used to render as bare theme output with no header, footer or way back.
// Three posts with fixed dates: the fixture a visitor opens, and one story
// either side of it so the previous/next control has real neighbours to find.
// The dates are re-applied on every run rather than only at creation — a
// fixture left over from an earlier run carries that run's date, which would
// silently put it at the wrong end of the order.
$post_id = 0;
foreach ( array(
	array( 'clubhouse-post-older', 'Clubhouse post older', '2026-06-01 12:00:00', '<p>Neighbour.</p>' ),
	array( 'clubhouse-post-fixture', 'Clubhouse post fixture', '2026-06-15 12:00:00', '<p id="post-content">POST CONTENT</p>' ),
	array( 'clubhouse-post-newer', 'Clubhouse post newer', '2026-06-29 12:00:00', '<p>Neighbour.</p>' ),
) as $fixture ) {
	list( $slug, $title, $when, $body ) = $fixture;
	$row  = get_page_by_path( $slug, OBJECT, 'post' );
	$args = array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_name'     => $slug,
		'post_title'    => $title,
		'post_content'  => $body,
		'post_date'     => $when,
		'post_date_gmt' => $when,
	);
	if ( $row instanceof WP_Post ) {
		$args['ID'] = $row->ID;
		$id         = wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
	}
	if ( 'clubhouse-post-fixture' === $slug ) {
		$post_id = is_int( $id ) ? $id : 0;
	}
}

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
