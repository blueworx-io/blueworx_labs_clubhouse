<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the club wants its email to come from, where they have said.
 *
 * Both blank is the normal case and the one every club starts on: the name
 * becomes the club's own and the address noreply@ their domain, with nothing
 * for anybody to fill in. These exist for the club that has a real mailbox and
 * would rather members could reply to it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Mail_Settings {

	private const KEY = 'mail';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,mixed> */
	private function data(): array {
		$data = $this->storage->get( self::KEY, array() );
		return is_array( $data ) ? $data : array();
	}

	private function value( string $field ): string {
		$data = $this->data();
		$raw  = $data[ $field ] ?? '';
		return is_string( $raw ) ? trim( $raw ) : '';
	}

	private function put( string $field, string $value ): void {
		$data           = $this->data();
		$data[ $field ] = trim( $value );
		$this->storage->set( self::KEY, $data );
	}

	public function get_from_name(): string {
		return $this->value( 'from_name' );
	}

	public function set_from_name( string $name ): void {
		$this->put( 'from_name', $name );
	}

	public function get_from_address(): string {
		return $this->value( 'from_address' );
	}

	public function set_from_address( string $address ): void {
		$this->put( 'from_address', $address );
	}
}
