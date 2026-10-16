<?php
// tests/php/PlaytomicCsvTest.php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlaytomicCsvTest extends TestCase {

	/** @return array<string,mixed> */
	private function venue( array $over = array() ): array {
		return array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't1', 'club' => 'PadlX North', 'category' => 'Founding Member' ), $over );
	}

	public function test_headers_follow_the_brief_in_order(): void {
		$h = Blueworx_Clubhouse_Playtomic_Csv::headers( array( $this->venue(), $this->venue( array( 'id' => 't2', 'club' => 'PadlX South' ) ) ) );
		$this->assertSame(
			array(
				'name', 'email', 'phone_number', 'gender', 'birthdate',
				'CAT_IMP-PadlX North-cat_name', 'CAT_IMP-PadlX North-exp_date', 'CAT_IMP-PadlX North-benefit_price_id', 'CAT_IMP-PadlX North-next_due_date',
				'CAT_IMP-PadlX South-cat_name', 'CAT_IMP-PadlX South-exp_date', 'CAT_IMP-PadlX South-benefit_price_id', 'CAT_IMP-PadlX South-next_due_date',
			),
			$h
		);
	}

	#[DataProvider('phones')]
	public function test_phone_variants( string $in, string $out ): void {
		$this->assertSame( $out, Blueworx_Clubhouse_Playtomic_Csv::phone( $in ) );
	}

	/** @return array<int,array{string,string}> */
	public static function phones(): array {
		return array(
			array( '0412 345 678', '+61412345678' ),
			array( '+61 412 345 678', '+61412345678' ),
			array( '(04) 1234-5678', '+61412345678' ),
			array( '61412345678', '+61412345678' ),
			array( '0061412345678', '+61412345678' ),
			array( '02 6123 4567', '+61261234567' ),
			array( '+61 (0)412 345 678', '+61412345678' ),
			array( '', '' ),
			array( 'n/a', '' ),
		);
	}

	public function test_gender_maps_to_playtomic_words(): void {
		$this->assertSame( 'MALE', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Male' ) );
		$this->assertSame( 'FEMALE', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Female' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Other' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Prefer not to say' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::gender( '' ) );
	}

	public function test_add_months_handles_month_ends(): void {
		$this->assertSame( '2027-10-15', Blueworx_Clubhouse_Playtomic_Csv::add_months( '2026-10-15', 12 ) );
		$this->assertSame( '2027-02-28', Blueworx_Clubhouse_Playtomic_Csv::add_months( '2027-01-31', 1 ) );
	}

	public function test_expiry_and_due_rules(): void {
		$today = '2026-10-15';
		$this->assertSame( '2027-10-15', Blueworx_Clubhouse_Playtomic_Csv::expiry( $this->venue(), $today ) );
		$fixed = $this->venue( array( 'expiry_mode' => 'date', 'expiry_date' => '2027-01-31' ) );
		$this->assertSame( '2027-01-31', Blueworx_Clubhouse_Playtomic_Csv::expiry( $fixed, $today ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::due( $this->venue(), $today, '2027-10-15' ) );
		$this->assertSame( '2027-10-15', Blueworx_Clubhouse_Playtomic_Csv::due( $this->venue( array( 'due_mode' => 'expiry' ) ), $today, '2027-10-15' ) );
		$this->assertSame( '2026-11-15', Blueworx_Clubhouse_Playtomic_Csv::due( $this->venue( array( 'due_mode' => 'months', 'due_months' => 1 ) ), $today, '2027-10-15' ) );
	}

	public function test_a_row_fills_every_column(): void {
		$member = array( 'id' => 3, 'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'phone' => '0412 345 678', 'gender' => 'Female', 'birthdate' => '1990-05-04' );
		$row    = Blueworx_Clubhouse_Playtomic_Csv::row( $member, array( $this->venue( array( 'price_id' => 'price-9', 'due_mode' => 'expiry' ) ) ), '2026-10-15' );
		$this->assertSame( array( 'Ana Reyes', 'ana@example.com', '+61412345678', 'FEMALE', '1990-05-04', 'Founding Member', '2027-10-15', 'price-9', '2027-10-15' ), $row );
	}

	public function test_build_quotes_and_uses_crlf_without_bom(): void {
		$members = array( array( 'id' => 1, 'name' => 'O\'Brien, Pat', 'email' => 'pat@example.com', 'phone' => '', 'gender' => '', 'birthdate' => '' ) );
		$csv     = Blueworx_Clubhouse_Playtomic_Csv::build( $members, array( $this->venue() ), '2026-10-15' );
		$lines   = explode( "\r\n", $csv );
		$this->assertStringStartsWith( 'name,email', $csv );
		$this->assertSame( '"O\'Brien, Pat",pat@example.com,,,,Founding Member,2027-10-15,,', $lines[1] );
		$this->assertSame( '', $lines[2], 'ends with one CRLF' );
	}
}
