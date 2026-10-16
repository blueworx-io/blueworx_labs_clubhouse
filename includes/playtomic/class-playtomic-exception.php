<?php
// includes/playtomic/class-playtomic-exception.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One failure talking to Playtomic, said in plain English. The detail is
 * the scrubbed response for the log, never for the screen.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Exception extends RuntimeException {

	public function __construct( string $message, private int $status = 0, private string $detail = '' ) {
		parent::__construct( $message );
	}

	public function status(): int {
		return $this->status;
	}

	public function detail(): string {
		return $this->detail;
	}
}
