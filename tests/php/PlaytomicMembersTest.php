<?php
// tests/php/PlaytomicMembersTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicMembersTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	/** @return array<int,array<string,mixed>> */
	private function people(): array {
		$base = array( 'first' => '', 'last' => '', 'display' => '', 'email' => '', 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' );
		return array(
			array_merge( $base, array( 'id' => 1, 'first' => 'Ana', 'last' => 'Reyes', 'email' => 'ana@example.com' ) ),
			array_merge( $base, array( 'id' => 2, 'display' => 'pat', 'email' => 'pat@example.com', 'registered' => '2026-10-10', 'exported' => '2026-10-12' ) ),
			array_merge( $base, array( 'id' => 3, 'email' => 'noname@example.com' ) ),
			array_merge( $base, array( 'id' => 4, 'first' => 'No', 'last' => 'Email' ) ),
		);
	}

	private function members( ?callable $qualifies = null ): Blueworx_Clubhouse_Playtomic_Members {
		return new Blueworx_Clubhouse_Playtomic_Members( fn(): array => $this->people(), $qualifies );
	}

	public function test_new_filter_hides_the_exported(): void {
		$ids = array_column( $this->members()->rows( 'new', '', '' ), 'id' );
		$this->assertSame( array( 1, 3, 4 ), $ids );
	}

	public function test_all_filter_shows_everyone_with_their_date(): void {
		$rows = $this->members()->rows( 'all', '', '' );
		$this->assertSame( array( 1, 2, 3, 4 ), array_column( $rows, 'id' ) );
		$this->assertSame( '2026-10-12', $rows[1]['exported'] );
	}

	public function test_date_range_is_on_joined(): void {
		$this->assertSame( array( 2 ), array_column( $this->members()->rows( 'all', '2026-10-01', '2026-10-31' ), 'id' ) );
		$this->assertSame( array( 1, 3, 4 ), array_column( $this->members()->rows( 'all', '', '2026-09-30' ), 'id' ) );
	}

	public function test_name_falls_back_to_display_name(): void {
		$rows = $this->members()->rows( 'all', '', '' );
		$this->assertSame( 'Ana Reyes', $rows[0]['name'] );
		$this->assertSame( 'pat', $rows[1]['name'] );
	}

	public function test_incomplete_member_is_never_exportable(): void {
		$rows = $this->members()->rows( 'all', '', '' );
		$this->assertFalse( $rows[2]['complete'] );
		$this->assertSame( 'No name on their account.', $rows[2]['reason'] );
		$this->assertFalse( $rows[3]['complete'] );
		$this->assertSame( 'No email address on their account.', $rows[3]['reason'] );
		$this->assertSame( array( 1 ), array_column( $this->members()->exportable( array( 1, 3, 4, 99 ) ), 'id' ) );
	}

	public function test_qualifies_rule_is_applied(): void {
		$m = $this->members( fn( int $id ): bool => 2 === $id );
		$this->assertSame( array( 2 ), array_column( $m->rows( 'all', '', '' ), 'id' ) );
		$this->assertSame( array(), $m->exportable( array( 1 ) ) );
	}

	public function test_mark_and_clear_write_user_meta(): void {
		Blueworx_Clubhouse_Playtomic_Members::mark( array( 1, 3 ), '2026-10-15' );
		$this->assertSame( '2026-10-15', get_user_meta( 1, 'clubhouse_playtomic_exported', true ) );
		$this->assertSame( '2026-10-15', get_user_meta( 3, 'clubhouse_playtomic_exported', true ) );
		Blueworx_Clubhouse_Playtomic_Members::clear( 1 );
		$this->assertSame( '', get_user_meta( 1, 'clubhouse_playtomic_exported', true ) );
	}

	public function test_from_users_reads_meta_and_dates(): void {
		update_user_meta( 5, 'clubhouse_profile_phone', '0412' );
		update_user_meta( 5, 'clubhouse_playtomic_exported', '2026-01-02' );
		$user = (object) array( 'ID' => 5, 'first_name' => 'A', 'last_name' => 'B', 'display_name' => 'AB', 'user_email' => 'a@b.c', 'user_registered' => '2026-03-04 10:11:12' );
		$rows = Blueworx_Clubhouse_Playtomic_Members::from_users( array( $user ) );
		$this->assertSame( array( 'id' => 5, 'first' => 'A', 'last' => 'B', 'display' => 'AB', 'email' => 'a@b.c', 'registered' => '2026-03-04', 'phone' => '0412', 'gender' => '', 'birthdate' => '', 'exported' => '2026-01-02' ), $rows[0] );
	}
}
