<?php
// tests/php/PlaytomicControllerTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_page_is_in_the_registry_for_administrators_only(): void {
		$page = Blueworx_Clubhouse_Admin_Pages::find( 'clubhouse-playtomic' );
		$this->assertNotNull( $page );
		$this->assertSame( 'manage_options', $page['cap'] );
		$this->assertSame( array( 'administrator' ), Blueworx_Clubhouse_Admin_Pages::roles_with_access( 'clubhouse-playtomic' ) );
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
		$t->queue[] = array( 'status' => 401, 'body' => '{"error":"UNAUTHORIZED","echo":"s"}' );
		$settings = new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
		$client   = new Blueworx_Clubhouse_Playtomic_Client( $t, 'c', 's', fn(): int => 1000 );

		$result = Blueworx_Clubhouse_Playtomic_Controller::test_connection( $client, $settings );
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
}
