<?php
// tests/php/PlaytomicControllerTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( null );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( null );
	}

	private function subscription_settings(): Blueworx_Clubhouse_Playtomic_Settings {
		$settings = new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
		$settings->save( array( 'who' => 'subscriptions', 'price_ids' => array( 'p1' ) ) );
		return $settings;
	}

	/** @return array<string,mixed> */
	private function person( int $id, string $email ): array {
		return array( 'id' => $id, 'first' => 'Member', 'last' => (string) $id, 'display' => '', 'email' => $email, 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' );
	}

	public function test_subscription_rule_lists_nobody_when_the_shop_is_not_running(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $ids, int $page ): ?array => array(
			array( 'id' => 's1', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-1' ),
		) );
		$members = Blueworx_Clubhouse_Playtomic_Controller::members( $this->subscription_settings(), fn(): array => array( $this->person( 1, 'one@example.com' ) ) );
		$this->assertSame( array(), $members->rows( 'all', '', '' ) );
		$this->assertSame( array(), $members->exportable( array( 1 ) ) );
	}

	public function test_subscription_rule_asks_the_shop_once_for_everyone(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		update_user_meta( 2, 'sc_customer_ids', array( 'live' => 'cust-2' ) );
		$calls = 0;
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $ids, int $page ) use ( &$calls ): ?array {
			++$calls;
			return array( array( 'id' => 's1', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-2' ) );
		} );
		$members = Blueworx_Clubhouse_Playtomic_Controller::members(
			$this->subscription_settings(),
			fn(): array => array( $this->person( 1, 'one@example.com' ), $this->person( 2, 'two@example.com' ) )
		);
		$this->assertSame( array( 2 ), array_column( $members->rows( 'all', '', '' ), 'id' ) );
		$this->assertSame( array( 2 ), array_column( $members->exportable( array( 1, 2 ) ), 'id' ) );
		$this->assertSame( 1, $calls );
	}

	public function test_page_warns_when_the_rule_needs_a_missing_shop(): void {
		$notice = Blueworx_Clubhouse_Playtomic_Controller::page_notice( '', array( 'who' => 'subscriptions' ), false );
		$this->assertSame( 'warning', $notice['type'] );
		$this->assertStringContainsString( 'needs the shop, which is not running', $notice['text'] );
		$this->assertSame( 'Settings saved.', Blueworx_Clubhouse_Playtomic_Controller::page_notice( 'saved', array( 'who' => 'subscriptions' ), false )['text'] );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::page_notice( '', array( 'who' => 'subscriptions' ), true ) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::page_notice( '', array( 'who' => 'all' ), false ) );
	}

	public function test_a_venue_without_a_club_name_is_left_out_of_the_file(): void {
		$members = new Blueworx_Clubhouse_Playtomic_Members( fn(): array => array( $this->person( 1, 'one@example.com' ) ) );
		$named   = array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't1', 'club' => 'Club North', 'category' => 'Founding' ) );
		$blank   = array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't2', 'club' => '  ', 'category' => 'Other' ) );
		$payload = Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( 1 ), array( $blank, $named ), '2026-10-15' );
		$this->assertNotNull( $payload );
		$header = explode( "\r\n", $payload['body'] )[0];
		$this->assertStringContainsString( 'Club North', $header );
		$this->assertSame( Blueworx_Clubhouse_Playtomic_Csv::build( array(), array( $named ), '2026-10-15' ), $header . "\r\n" );
		$this->assertStringNotContainsString( 'Other', $payload['body'] );
	}

	public function test_no_venue_with_a_club_name_means_no_file(): void {
		$members = new Blueworx_Clubhouse_Playtomic_Members( fn(): array => array( $this->person( 1, 'one@example.com' ) ) );
		$blank   = array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't2', 'club' => '' ) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( 1 ), array( $blank ), '2026-10-15' ) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( 1 ), array(), '2026-10-15' ) );
		$this->assertSame( 'warning', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'no-venues' )['type'] );
		$this->assertSame( 'Give every venue a club name under Settings before downloading.', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'no-venues' )['text'] );
	}

	public function test_page_is_in_the_registry_for_administrators_and_owners(): void {
		$page = Blueworx_Clubhouse_Admin_Pages::find( 'clubhouse-playtomic' );
		$this->assertNotNull( $page );
		$this->assertSame( 'manage_clubhouse', $page['cap'] );
		$this->assertSame( array( 'administrator', 'clubhouse_owner' ), Blueworx_Clubhouse_Admin_Pages::roles_with_access( 'clubhouse-playtomic' ) );
	}

	public function test_test_connection_reports_venues_and_merges_them(): void {
		$t = new Blueworx_Clubhouse_Fake_Playtomic_Transport();
		$t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'token' => fake_playtomic_jwt( array( 'ten-a', 'ten-b' ) ), 'expires_in' => 3600 ) ) );
		$settings = new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
		$settings->save( array( 'client_id' => 'c', 'secret' => 's' ) );
		$client = new Blueworx_Clubhouse_Playtomic_Client( $t, 'c', 's', fn(): int => 1000 );

		$result = Blueworx_Clubhouse_Playtomic_Controller::test_connection( $client, $settings );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'Connected. Playtomic lists 2 venues: ten-a, ten-b. Give each a name below.', $result['text'] );
		$this->assertSame( array( 'ten-a', 'ten-b' ), array_column( $settings->venues(), 'id' ) );
		$this->assertStringStartsWith( 'Connected on ', $settings->read()['last_test'] );
	}

	public function test_test_connection_failure_is_plain_and_logged_without_secrets(): void {
		$t = new Blueworx_Clubhouse_Fake_Playtomic_Transport();
		$t->queue[] = array( 'status' => 401, 'body' => '{"error":"UNAUTHORIZED","echo":"top-secret-value"}' );
		$settings = new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
		$client   = new Blueworx_Clubhouse_Playtomic_Client( $t, 'c', 'top-secret-value', fn(): int => 1000 );

		$log  = (string) tempnam( sys_get_temp_dir(), 'ch' );
		$prev = ini_set( 'error_log', $log );
		try {
			$result = Blueworx_Clubhouse_Playtomic_Controller::test_connection( $client, $settings );
		} finally {
			ini_set( 'error_log', (string) $prev );
		}
		$logged = (string) file_get_contents( $log );
		unlink( $log );
		$this->assertStringContainsString( 'Test connection failed', $logged );
		$this->assertStringContainsString( '[hidden]', $logged );
		$this->assertStringNotContainsString( 'top-secret-value', $logged );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Playtomic did not accept the Client ID and Secret.', $result['text'] );
		$this->assertSame( 'Playtomic did not accept the Client ID and Secret.', $settings->read()['last_error'] );
	}

	public function test_download_payload_builds_the_file_for_ticked_complete_members(): void {
		$members = new Blueworx_Clubhouse_Playtomic_Members( fn(): array => array(
			array( 'id' => 1, 'first' => 'Ana', 'last' => 'Reyes', 'display' => '', 'email' => 'ana@example.com', 'registered' => '2026-09-01', 'phone' => '0412 345 678', 'gender' => 'Female', 'birthdate' => '', 'exported' => '' ),
			array( 'id' => 3, 'first' => '', 'last' => '', 'display' => '', 'email' => 'x@example.com', 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' ),
		) );
		$venue   = array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't', 'club' => 'PadlX North', 'category' => 'Founding' ) );
		$payload = Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( '1', '3', '99' ), array( $venue ), '2026-10-15' );
		$this->assertNotNull( $payload );
		$this->assertSame( 'playtomic-members-2026-10-15.csv', $payload['filename'] );
		$this->assertSame( array( 1 ), $payload['ids'] );
		$this->assertStringContainsString( "\r\nAna Reyes,ana@example.com,+61412345678,FEMALE,,Founding,2027-10-15,,\r\n", $payload['body'] );
	}

	public function test_download_with_no_valid_ids_marks_nobody(): void {
		$members = new Blueworx_Clubhouse_Playtomic_Members( fn(): array => array(
			array( 'id' => 3, 'first' => '', 'last' => '', 'display' => '', 'email' => 'x@example.com', 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' ),
		) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array(), array(), '2026-10-15' ) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( '3', '99' ), array(), '2026-10-15' ) );
		$this->assertSame( array(), wp_stub_calls( 'update_user_meta' ) );
	}

	public function test_notices_are_plain_sentences(): void {
		$this->assertSame( 'success', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'saved' )['type'] );
		$this->assertSame( 'warning', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'nothing' )['type'] );
		$this->assertStringContainsString( 'Tick at least one member', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'nothing' )['text'] );
		$this->assertSame( 'success', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'cleared' )['type'] );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'junk' ) );
	}
	/** @return array{filename:string,body:string,ids:array<int,int>} */
	private function payload(): array {
		return array( 'filename' => 'playtomic-members-2026-10-16.csv', 'body' => "name,email\r\nAna,ana@example.com\r\n", 'ids' => array( 1 ) );
	}

	public function test_send_attaches_the_file_and_reports_the_mailers_answer(): void {
		$seen = array();
		$ok   = Blueworx_Clubhouse_Playtomic_Controller::send(
			$this->payload(),
			'imports@playtomic.example',
			'PadlX',
			function ( string $to, string $subject, string $body, array $attachments ) use ( &$seen ): bool {
				$seen = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'attachments' => $attachments );
				$seen['file'] = file_get_contents( $attachments[0] );
				return true;
			}
		);
		$this->assertTrue( $ok );
		$this->assertSame( 'imports@playtomic.example', $seen['to'] );
		$this->assertSame( 'Member import from PadlX — 2026-10-16', $seen['subject'] );
		$this->assertStringContainsString( '1 member', $seen['body'] );
		$this->assertStringEndsWith( 'playtomic-members-2026-10-16.csv', $seen['attachments'][0] );
		$this->assertSame( "name,email\r\nAna,ana@example.com\r\n", $seen['file'] );
		$this->assertFileDoesNotExist( $seen['attachments'][0] );
	}

	public function test_send_returns_false_when_the_mailer_fails_and_cleans_up(): void {
		$path = '';
		$ok   = Blueworx_Clubhouse_Playtomic_Controller::send( $this->payload(), 'x@example.com', 'Club', function ( string $to, string $subject, string $body, array $attachments ) use ( &$path ): bool {
			$path = $attachments[0];
			return false;
		} );
		$this->assertFalse( $ok );
		$this->assertFileDoesNotExist( $path );
	}

	public function test_send_notices(): void {
		$this->assertSame( 'success', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'sent' )['type'] );
		$this->assertSame( 'danger', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'send-failed' )['type'] );
		$this->assertStringContainsString( 'nobody was marked', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'send-failed' )['text'] );
		$this->assertSame( 'warning', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'no-email' )['type'] );
	}
}
