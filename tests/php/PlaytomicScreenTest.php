<?php
// tests/php/PlaytomicScreenTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicScreenTest extends TestCase {

	/** @return array<string,mixed> */
	private function model( array $over = array() ): array {
		$settings               = Blueworx_Clubhouse_Playtomic_Settings::defaults();
		unset( $settings['secret'] );
		$settings['has_secret'] = true;
		$settings['venues']     = array( array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 'ten-a', 'name' => 'North', 'club' => 'PadlX North' ) ) );
		return array_merge(
			array(
				'page_url'    => 'https://club.test/wp-admin/admin.php?page=clubhouse-playtomic',
				'action_url'  => 'https://club.test/wp-admin/admin-post.php',
				'nonce_field' => '<input type="hidden" name="_wpnonce" value="n">',
				'actions'     => array( 'save' => 'clubhouse_playtomic_save', 'test' => 'clubhouse_playtomic_test', 'download' => 'clubhouse_playtomic_download', 'clear' => 'clubhouse_playtomic_clear' ),
				'clear_urls'  => array( 2 => 'https://club.test/wp-admin/admin-post.php?action=clubhouse_playtomic_clear&id=2&_wpnonce=n' ),
				'role_tags'   => '',
				'notice'      => null,
				'settings'    => $settings,
				'can_encrypt' => true,
				'shop'        => true,
				'prices'      => array( array( 'id' => 'p1', 'label' => 'Founding — $99/yr' ) ),
				'filter'      => 'new',
				'from'        => '',
				'to'          => '',
				'members'     => array(
					array( 'id' => 1, 'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'phone' => '0412', 'registered' => '2026-09-01', 'exported' => '', 'complete' => true, 'reason' => '' ),
					array( 'id' => 2, 'name' => 'Pat', 'email' => 'pat@example.com', 'phone' => '', 'registered' => '2026-10-10', 'exported' => '2026-10-12', 'complete' => true, 'reason' => '' ),
					array( 'id' => 3, 'name' => '', 'email' => 'x@example.com', 'phone' => '', 'registered' => '2026-10-11', 'exported' => '', 'complete' => false, 'reason' => 'No name on their account.' ),
				),
			),
			$over
		);
	}

	public function test_secret_is_never_echoed(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model() );
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'Saved. Enter a new one only to replace it.', $html );
		$this->assertStringNotContainsString( 'value="hunter2"', $html );
	}

	public function test_venue_rows_plus_one_blank(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="clubhouse_playtomic[venues][0][id]" value="ten-a"', $html );
		$this->assertStringContainsString( 'name="clubhouse_playtomic[venues][1][id]" value=""', $html );
	}

	public function test_members_table_ticks_only_complete_unexported_rows(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model() );
		$this->assertMatchesRegularExpression( '/name="clubhouse_playtomic_ids\[\]" value="1"[^>]*checked/', $html );
		$this->assertDoesNotMatchRegularExpression( '/name="clubhouse_playtomic_ids\[\]" value="2"[^>]*checked/', $html );
		$this->assertMatchesRegularExpression( '/name="clubhouse_playtomic_ids\[\]" value="3"[^>]*disabled/', $html );
		$this->assertStringContainsString( 'No name on their account.', $html );
		$this->assertStringContainsString( '2026-10-12', $html );
		$this->assertStringContainsString( 'Clear export mark', $html );
		$this->assertStringContainsString( 'data-playtomic-count', $html );
	}

	public function test_download_button_is_disabled_with_nothing_listed(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model( array( 'members' => array() ) ) );
		$this->assertStringContainsString( 'Nobody to export with this filter.', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*name="' . 'clubhouse_playtomic_download' . '"[^>]*disabled/', $html );
	}

	public function test_subscription_rule_is_greyed_without_a_shop(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model( array( 'shop' => false, 'prices' => array() ) ) );
		$this->assertStringContainsString( 'needs a shop', $html );
		$this->assertMatchesRegularExpression( '/value="subscriptions"[^>]*disabled/', $html );
	}

	public function test_a_stored_subscription_rule_stays_chosen_without_a_shop(): void {
		$m = $this->model( array( 'shop' => false, 'prices' => array() ) );
		$m['settings']['who']       = 'subscriptions';
		$m['settings']['price_ids'] = array( 'p1' );
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertMatchesRegularExpression( '/value="subscriptions" checked disabled/', $html );
		$this->assertDoesNotMatchRegularExpression( '/value="all"[^>]*checked/', $html );
		// Carried through a Save, since a disabled radio is not sent.
		$this->assertStringContainsString( '<input type="hidden" name="clubhouse_playtomic[who]" value="subscriptions">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="clubhouse_playtomic[price_ids][]" value="p1">', $html );
		$this->assertLessThan( strpos( $html, 'value="all"' ), strpos( $html, 'type="hidden" name="clubhouse_playtomic[who]"' ) );
	}

	public function test_no_hidden_rule_when_the_shop_is_running_or_the_rule_is_everyone(): void {
		$m = $this->model();
		$m['settings']['who'] = 'subscriptions';
		$this->assertStringNotContainsString( 'type="hidden" name="clubhouse_playtomic[who]"', Blueworx_Clubhouse_Playtomic_Screen::render( $m ) );
		$this->assertStringNotContainsString( 'type="hidden" name="clubhouse_playtomic[who]"', Blueworx_Clubhouse_Playtomic_Screen::render( $this->model( array( 'shop' => false, 'prices' => array() ) ) ) );
	}

	public function test_venue_without_a_club_name_is_flagged(): void {
		$m = $this->model( array( 'today' => '2026-10-15' ) );
		$m['settings']['venues'][0]['club'] = '';
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertSame( 1, substr_count( $html, 'Needs a club name before it can go in the file' ) );
		$this->assertStringContainsString( 'bw-badge bw-badge--warning">Needs a club name', $html );
	}

	public function test_venue_with_a_past_fixed_expiry_is_flagged(): void {
		$m = $this->model( array( 'today' => '2026-10-15' ) );
		$m['settings']['venues'][0]['expiry_mode'] = 'date';
		$m['settings']['venues'][0]['expiry_date'] = '2026-10-14';
		$m['settings']['venues'][]                 = array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 'ten-b', 'club' => 'South', 'expiry_mode' => 'date', 'expiry_date' => '2026-10-15' ) );
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertSame( 1, substr_count( $html, 'Expiry date is in the past' ) );
		$this->assertStringContainsString( 'bw-badge bw-badge--warning">Expiry date is in the past', $html );
	}

	public function test_complete_venues_and_the_blank_row_carry_no_badge(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model( array( 'today' => '2026-10-15' ) ) );
		$this->assertStringNotContainsString( 'Needs a club name', $html );
		$this->assertStringNotContainsString( 'Expiry date is in the past', $html );
	}

	public function test_notice_and_last_test_show(): void {
		$m = $this->model( array( 'notice' => array( 'type' => 'success', 'text' => 'Connected. Playtomic lists 2 venues.' ) ) );
		$m['settings']['last_test'] = 'Connected on 2026-10-15: ten-a, ten-b.';
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertStringContainsString( 'bw-notice--success', $html );
		$this->assertStringContainsString( 'Connected. Playtomic lists 2 venues.', $html );
		$this->assertStringContainsString( 'Connected on 2026-10-15', $html );
	}

	public function test_every_club_string_is_escaped(): void {
		$m = $this->model();
		$m['members'][0]['name'] = '<b>x</b>';
		$m['settings']['venues'][0]['club'] = '"quoted"';
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringContainsString( '&quot;quoted&quot;', $html );
	}
}
