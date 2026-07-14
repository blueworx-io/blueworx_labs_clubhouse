<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner-supplied brand inputs: one accent, club name, logo, favicon, socials. Stored as a single
 * autoloaded option (via the storage abstraction). Colour derivation lives in
 * the colour engine — this class only holds the raw inputs.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Branding {

	private const KEY = 'branding';

	private const DEFAULTS = array(
		'accent'    => '#c6f24e',
		'club_name' => 'ClubHouse',
		'logo'      => '',
		'facebook'  => 'https://facebook.com/clubhouse',
		'instagram' => 'https://instagram.com/clubhouse',
		'linkedin'  => 'https://linkedin.com/company/clubhouse',
		'favicon'   => '',
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

	private function value( string $field ): mixed {
		$data = $this->data();
		return array_key_exists( $field, $data ) ? $data[ $field ] : self::DEFAULTS[ $field ];
	}

	private function put( string $field, mixed $value ): void {
		$data            = $this->data();
		$data[ $field ]  = $value;
		$this->storage->set( self::KEY, $data );
	}

	public function get_accent(): string {
		return (string) $this->value( 'accent' );
	}

	public function set_accent( string $hex ): void {
		$this->put( 'accent', '#' . strtolower( ltrim( trim( $hex ), '#' ) ) );
	}

	public function get_club_name(): string {
		return (string) $this->value( 'club_name' );
	}

	public function set_club_name( string $name ): void {
		$this->put( 'club_name', $name );
	}

	public function get_logo(): string {
		return (string) $this->value( 'logo' );
	}

	public function set_logo( string $url_or_id ): void {
		$this->put( 'logo', $url_or_id );
	}

	public function get_facebook_url(): string {
		return (string) $this->value( 'facebook' );
	}

	public function set_facebook_url( string $url ): void {
		$this->put( 'facebook', $url );
	}

	public function get_instagram_url(): string {
		return (string) $this->value( 'instagram' );
	}

	public function set_instagram_url( string $url ): void {
		$this->put( 'instagram', $url );
	}

	public function get_linkedin_url(): string {
		return (string) $this->value( 'linkedin' );
	}

	public function set_linkedin_url( string $url ): void {
		$this->put( 'linkedin', $url );
	}

	public function get_favicon(): string {
		return (string) $this->value( 'favicon' );
	}

	public function set_favicon( string $url_or_id ): void {
		$this->put( 'favicon', $url_or_id );
	}
}
