<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a member lands after signing in and after signing out.
 *
 * Stored alongside the other owner-supplied settings, and read by the auth
 * handler. Both values are free text so an owner can point at a clubhouse page
 * key ('membership'), a path ('/members/') or a full URL — the handler decides
 * what is safe to honour, not this class, which only holds what was typed.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Auth_Settings {

	private const KEY = 'auth';

	private const DEFAULTS = array(
		// Empty means "the site's home page". Shipping a default of 'home' would
		// look identical but would survive an owner clearing the field, which is
		// how they say "just send them to the front page".
		'post_login'  => '',
		'post_logout' => '',
	);

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
		$raw  = array_key_exists( $field, $data ) ? $data[ $field ] : self::DEFAULTS[ $field ];
		return is_string( $raw ) ? $raw : '';
	}

	private function put( string $field, string $value ): void {
		$data           = $this->data();
		$data[ $field ] = $value;
		$this->storage->set( self::KEY, $data );
	}

	public function get_post_login(): string {
		return $this->value( 'post_login' );
	}

	public function set_post_login( string $target ): void {
		$this->put( 'post_login', trim( $target ) );
	}

	public function get_post_logout(): string {
		return $this->value( 'post_logout' );
	}

	public function set_post_logout( string $target ): void {
		$this->put( 'post_logout', trim( $target ) );
	}
}
