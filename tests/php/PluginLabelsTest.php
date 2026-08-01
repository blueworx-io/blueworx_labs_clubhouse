<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The bundled plugins ship under vendor product names that tell a club
 * administrator who wrote them, not what they do. These pin the rename — and,
 * just as importantly, pin its limits: it is a display-name change, so anything
 * an update, a licence check or an activation depends on has to come back
 * untouched.
 */
final class PluginLabelsTest extends TestCase {

	/** All seven, by directory — the authoritative signal for the plugins list. */
	public function test_every_bundled_plugin_gets_its_plain_english_name(): void {
		$expected = array(
			'surecart/surecart.php'         => 'eCommerce',
			'sureforms/sureforms.php'       => 'Form Builder',
			'latepoint/latepoint.php'       => 'Bookings',
			'surerank/surerank.php'         => 'SEO Rank',
			'suredonation/suredonation.php' => 'Donations',
			'surecontact/surecontact.php'   => 'CRM Management',
			'suremail/suremail.php'         => 'Mail Reports',
		);
		foreach ( $expected as $file => $label ) {
			$this->assertSame( $label, Blueworx_Clubhouse_Plugin_Labels::label_for_file( $file ), $file );
		}
		$this->assertCount( 7, Blueworx_Clubhouse_Plugin_Labels::map() );
	}

	/**
	 * Two of these ship under a plural directory — SureMail is "suremails",
	 * SureDonation is "suredonations". One map entry has to cover both spellings
	 * or the rename silently misses on a real install.
	 */
	public function test_a_plural_directory_is_the_same_plugin(): void {
		$this->assertSame( 'Mail Reports', Blueworx_Clubhouse_Plugin_Labels::label_for_file( 'suremails/suremails.php' ) );
		$this->assertSame( 'Donations', Blueworx_Clubhouse_Plugin_Labels::label_for_file( 'suredonations/suredonations.php' ) );
	}

	/** An entry file named nothing like its plugin still resolves — the directory is the plugin. */
	public function test_the_directory_decides_not_the_filename(): void {
		$this->assertSame( 'eCommerce', Blueworx_Clubhouse_Plugin_Labels::label_for_file( 'surecart/plugin.php' ) );
	}

	/** Anything not ours is not ours. No default, no guess. */
	public function test_an_unrelated_plugin_is_left_alone(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Labels::label_for_file( 'akismet/akismet.php' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Labels::label_for_file( 'blueworx_labs_clubhouse/blueworx-labs-clubhouse.php' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Labels::label_for_file( '' ) );
	}

	/** A vendor name inside a longer title is replaced in place, keeping the vendor's own wording. */
	public function test_a_vendor_name_inside_a_title_is_replaced_in_place(): void {
		$this->assertSame( 'Bookings', Blueworx_Clubhouse_Plugin_Labels::label_for_title( 'LatePoint' ) );
		$this->assertSame( 'Bookings Settings', Blueworx_Clubhouse_Plugin_Labels::label_for_title( 'LatePoint Settings' ) );
		$this->assertSame( 'eCommerce &lsaquo; My Club &#8212; WordPress', Blueworx_Clubhouse_Plugin_Labels::label_for_title( 'SureCart &lsaquo; My Club &#8212; WordPress' ) );
	}

	/** A title with nothing of ours in it comes back byte-for-byte. */
	public function test_an_unrelated_title_is_untouched(): void {
		$this->assertSame( 'Dashboard', Blueworx_Clubhouse_Plugin_Labels::label_for_title( 'Dashboard' ) );
		$this->assertSame( 'Sure thing', Blueworx_Clubhouse_Plugin_Labels::label_for_title( 'Sure thing' ) );
	}

	/**
	 * Idempotent. The menu is rewritten in place on a global WordPress rebuilds
	 * per request, so the transform runs over its own output routinely — and a
	 * vendor update that re-registers its menu simply gets rewritten again.
	 */
	public function test_renaming_twice_changes_nothing_the_second_time(): void {
		$once  = Blueworx_Clubhouse_Plugin_Labels::label_for_title( 'LatePoint Bookings Calendar' );
		$twice = Blueworx_Clubhouse_Plugin_Labels::label_for_title( $once );
		$this->assertSame( $once, $twice );
	}

	/**
	 * The menu keeps every field but its titles — capability, slug, classes, hook
	 * name and icon are what WordPress routes and locks on, and renaming any of
	 * them would break the very things the issue says must not break.
	 */
	public function test_the_menu_keeps_its_slug_capability_and_icon(): void {
		$menu = array(
			25 => array( 'SureCart', 'manage_sc_shop_settings', 'sc-dashboard', 'SureCart', '', 'toplevel_page_sc-dashboard', 'dashicons-cart' ),
			26 => array( 'LatePoint', 'manage_latepoint', 'latepoint', 'LatePoint Bookings', '', 'toplevel_page_latepoint', 'dashicons-calendar' ),
			27 => array( 'Posts', 'edit_posts', 'edit.php', 'Posts', '', 'menu-posts', 'dashicons-admin-post' ),
		);
		$out = Blueworx_Clubhouse_Plugin_Labels::rename_menu( $menu );

		$this->assertSame( 'eCommerce', $out[25][0] );
		$this->assertSame( 'Bookings', $out[26][0] );
		$this->assertSame( 'Bookings Bookings', $out[26][3] );

		// Everything routing or permission depends on is byte-identical.
		$this->assertSame( 'manage_sc_shop_settings', $out[25][1] );
		$this->assertSame( 'sc-dashboard', $out[25][2] );
		$this->assertSame( 'toplevel_page_sc-dashboard', $out[25][5] );
		$this->assertSame( 'dashicons-cart', $out[25][6] );
		$this->assertSame( 'latepoint', $out[26][2] );

		// An unrelated menu item is returned exactly as it arrived.
		$this->assertSame( $menu[27], $out[27] );
	}

	/** Submenus are renamed by their titles; the parent slug is a key and stays a key. */
	public function test_submenu_titles_rename_but_parent_slugs_do_not(): void {
		$submenu = array(
			'sc-dashboard' => array(
				array( 'SureCart', 'manage_sc_shop_settings', 'sc-dashboard' ),
				array( 'Products', 'edit_sc_products', 'sc-products' ),
			),
			'latepoint'    => array(
				array( 'LatePoint Settings', 'manage_latepoint', 'latepoint-settings' ),
			),
		);
		$out = Blueworx_Clubhouse_Plugin_Labels::rename_submenu( $submenu );

		$this->assertArrayHasKey( 'sc-dashboard', $out );
		$this->assertArrayHasKey( 'latepoint', $out );
		$this->assertSame( 'eCommerce', $out['sc-dashboard'][0][0] );
		$this->assertSame( 'sc-dashboard', $out['sc-dashboard'][0][2] );
		$this->assertSame( 'Products', $out['sc-dashboard'][1][0] );
		$this->assertSame( 'Bookings Settings', $out['latepoint'][0][0] );
		$this->assertSame( 'latepoint-settings', $out['latepoint'][0][2] );
	}

	/**
	 * The plugins list renames the Name column and nothing else. Version, author
	 * and the plugin file key are what updates and licensing are keyed on.
	 */
	public function test_the_plugins_list_renames_only_the_name(): void {
		$plugins = array(
			'surecart/surecart.php' => array(
				'Name'       => 'SureCart',
				'Version'    => '3.2.1',
				'Author'     => 'SureCart',
				'PluginURI'  => 'https://surecart.com',
				'TextDomain' => 'surecart',
			),
			'akismet/akismet.php'   => array( 'Name' => 'Akismet Anti-spam', 'Version' => '5.3' ),
		);
		$out = Blueworx_Clubhouse_Plugin_Labels::rename_plugins( $plugins );

		$this->assertArrayHasKey( 'surecart/surecart.php', $out, 'the plugin file key is what updates are keyed on' );
		$this->assertSame( 'eCommerce', $out['surecart/surecart.php']['Name'] );
		$this->assertSame( '3.2.1', $out['surecart/surecart.php']['Version'] );
		$this->assertSame( 'https://surecart.com', $out['surecart/surecart.php']['PluginURI'], 'licensing and support links are the vendor\'s' );
		$this->assertSame( 'surecart', $out['surecart/surecart.php']['TextDomain'] );
		$this->assertSame( $plugins['akismet/akismet.php'], $out['akismet/akismet.php'] );
	}

	/** A vendor shipping from an unexpected directory is still caught, by its registered name. */
	public function test_an_unknown_directory_falls_back_to_the_registered_name(): void {
		$out = Blueworx_Clubhouse_Plugin_Labels::rename_plugins(
			array( 'surecart-pro-build/index.php' => array( 'Name' => 'SureCart' ) )
		);
		$this->assertSame( 'eCommerce', $out['surecart-pro-build/index.php']['Name'] );
	}
}
