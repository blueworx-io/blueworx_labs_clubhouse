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
