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
		// Ships empty on purpose. An unset secondary is DERIVED from the accent by
		// the colour engine, so a club that never opens the field still gets a
		// harmonious partner colour; storing a default here would instead pin every
		// site to one colour and make it clash the moment the accent changed.
		'secondary' => '',
		'club_name' => 'ClubHouse',
		'logo'      => '',
		'facebook'  => 'https://facebook.com/clubhouse',
		'instagram' => 'https://instagram.com/clubhouse',
		'linkedin'  => 'https://linkedin.com/company/clubhouse',
		// X ships empty, unlike the three networks above: it arrived after clubs had
		// already saved their branding, and a demo default would silently add a dead
		// link to a live footer. Empty = no X icon until the owner sets one.
		'x'         => '',
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

	/**
	 * The shipped accent, exposed so the colour picker's "reset to default" has
	 * something to reset TO without a second copy of the value living in the admin
	 * screen or the JavaScript.
	 */
	public static function default_accent(): string {
		return self::DEFAULTS['accent'];
	}

	public function get_accent(): string {
		return (string) $this->value( 'accent' );
	}

	public function set_accent( string $hex ): void {
		$this->put( 'accent', '#' . strtolower( ltrim( trim( $hex ), '#' ) ) );
	}

	/**
	 * The club's stored secondary, or '' when it has never set one. Callers
	 * wanting a colour to paint with should use effective_secondary() — this is
	 * the raw input, and '' is a meaningful value (it means "derive it").
	 */
	public function get_secondary(): string {
		return (string) $this->value( 'secondary' );
	}

	public function set_secondary( string $hex ): void {
		$hex = trim( $hex );
		// Empty clears the setting rather than storing '#', which is what the
		// picker's Clear button posts and what returns a club to the derived
		// default.
		$this->put( 'secondary', '' === ltrim( $hex, '#' ) ? '' : '#' . strtolower( ltrim( $hex, '#' ) ) );
	}

	public function has_secondary(): bool {
		return '' !== $this->get_secondary();
	}

	/**
	 * The secondary to actually paint with on a given look: the club's own, or one
	 * derived from its accent. The single place that fallback is decided, so the
	 * front end, the admin preview and the cache signature can never disagree
	 * about which colour is in force.
	 *
	 * Takes the look's shell because the derivation is shell-dependent — the
	 * lightness a derived partner has to reach to stay legible is different on a
	 * light look and a dark one. A club with a secondary of its own gets it back
	 * unchanged whatever the look, exactly as the accent behaves.
	 */
	public function effective_secondary( Blueworx_Clubhouse_Base_Look $look ): string {
		if ( $this->has_secondary() ) {
			return $this->get_secondary();
		}
		$tokens = $look->tokens();
		return Blueworx_Clubhouse_Color_Engine::default_secondary(
			$this->get_accent(),
			$tokens['--color-bg'],
			$tokens['--color-ink']
		);
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

	public function get_x_url(): string {
		return (string) $this->value( 'x' );
	}

	public function set_x_url( string $url ): void {
		$this->put( 'x', $url );
	}

	public function get_favicon(): string {
		return (string) $this->value( 'favicon' );
	}

	public function set_favicon( string $url_or_id ): void {
		$this->put( 'favicon', $url_or_id );
	}
}
