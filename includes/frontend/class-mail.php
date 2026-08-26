<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who the site's email comes from.
 *
 * Left alone, WordPress sends everything as "WordPress <wordpress@thedomain>".
 * A member reading a password reset sees a system message from software they
 * have never heard of, and every club we run looks identical in the inbox. The
 * club's name is the one thing that tells them this is their club writing.
 *
 * The pure half is here so both answers can be tested without a WordPress
 * runtime; register() applies them to core's two filters.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Mail {

	/**
	 * The mailbox the address is built on. Deliberately not a real mailbox: the
	 * club has not been asked to make one, and a From address that bounces is
	 * honest where one that silently disappears is not. A club that does have a
	 * mailbox says so, and their answer wins.
	 */
	private const MAILBOX = 'noreply';

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		// Priority 20: after the default 10, so a club running a dedicated mail
		// plugin — which is the tool for this job — keeps whatever it set.
		add_filter( 'wp_mail_from', array( self::class, 'filter_from' ), 20 );
		add_filter( 'wp_mail_from_name', array( self::class, 'filter_from_name' ), 20 );
	}

	/**
	 * The name on the From header.
	 *
	 * @param string $club  The club's name, from its branding or the site title.
	 * @param string $typed What the club typed under Setup, '' for none.
	 * @return string '' when there is nothing better than core's own default.
	 */
	public static function from_name( string $club, string $typed ): string {
		$typed = trim( $typed );
		return '' !== $typed ? $typed : trim( $club );
	}

	/**
	 * The address on the From header.
	 *
	 * @param string $home  The site's home address, for the domain.
	 * @param string $typed What the club typed under Setup, '' for none.
	 * @return string '' when no address can be built, leaving core's alone.
	 */
	public static function from_address( string $home, string $typed ): string {
		$typed = trim( $typed );
		// A typed address that is not one would put a From header on every mail
		// the club sends that no receiving server will accept. The derived one
		// at least works.
		if ( '' !== $typed && false !== filter_var( $typed, FILTER_VALIDATE_EMAIL ) ) {
			return $typed;
		}
		$domain = self::domain( $home );
		return '' === $domain ? '' : self::MAILBOX . '@' . $domain;
	}

	/**
	 * The bare domain a mailbox would live at. No www, no port, no path — the
	 * mailbox is at club.co.uk whatever address the site is served on.
	 */
	private static function domain( string $home ): string {
		$home = trim( $home );
		if ( '' === $home ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, unit-tested without a WordPress runtime.
		$host = (string) ( parse_url( $home, PHP_URL_HOST ) ?? '' );
		if ( '' === $host ) {
			// A bare "club.co.uk" with no scheme parses as a path, not a host.
			$host = (string) strtok( ltrim( $home, '/' ), '/' );
			$host = (string) strtok( $host, ':' );
		}
		$host = strtolower( $host );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * Core's wp_mail_from filter. Anything unbuildable falls through to whatever
	 * core was going to send, which is always a working address.
	 *
	 * @param string $address Core's own answer.
	 */
	public static function filter_from( $address ): string {
		$ours = self::from_address( self::home_url(), self::settings()->get_from_address() );
		return '' !== $ours ? $ours : (string) $address;
	}

	/**
	 * Core's wp_mail_from_name filter.
	 *
	 * @param string $name Core's own answer.
	 */
	public static function filter_from_name( $name ): string {
		$ours = self::from_name( self::club_name(), self::settings()->get_from_name() );
		return '' !== $ours ? $ours : (string) $name;
	}

	private static function settings(): Blueworx_Clubhouse_Mail_Settings {
		return new Blueworx_Clubhouse_Mail_Settings( new Blueworx_Clubhouse_Options_Storage() );
	}

	/** The club's own name where one is set, otherwise the site's title. */
	private static function club_name(): string {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		$club     = trim( $branding->get_club_name() );
		if ( '' !== $club ) {
			return $club;
		}
		return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
	}

	private static function home_url(): string {
		return function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
	}
}
