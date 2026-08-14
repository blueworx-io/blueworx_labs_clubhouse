<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The link from a membership tier to a checkout with that tier in the basket.
 *
 * Pure: the environment installs the checkout page's URL (WordPress asks the
 * shop where its checkout lives; the preview and tests set their own), and this
 * builds the link. Empty base or empty price means no link at all — a tier then
 * falls back to the contact page rather than offering a dead button.
 *
 * The parameter names below are what the shop was observed to accept — see
 * docs/integrations/surecart-notes.md. They live here as constants because they
 * are the one thing in this plugin that a shop update could change.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout {

	/** The query parameter naming the price to buy. */
	public const PRICE_PARAM = 'line_items[0][price_id]';

	/** The query parameter naming how many of it. */
	public const QUANTITY_PARAM = 'line_items[0][quantity]';

	private static string $base_url = '';

	/**
	 * A resolver, for when the URL cannot be known yet at set-up time — see
	 * set_resolver(). Takes priority over $base_url when installed.
	 *
	 * @var (callable():string)|null
	 */
	private static $resolver = null;

	/** Memoised result of $resolver, so it runs at most once per request. */
	private static ?string $resolved = null;

	public static function set_base_url( string $url ): void {
		self::$base_url = $url;
		self::$resolver  = null;
		self::$resolved  = null;
	}

	/**
	 * Install a resolver instead of a ready-made URL, for a value that is not
	 * safe to compute yet at the point where the environment wires this class
	 * up. SureCart_Products::checkout_url() reaches WordPress's get_permalink(),
	 * which touches $wp_rewrite — and WordPress does not create $wp_rewrite
	 * until after plugins_loaded, which is exactly when Frontend::register()
	 * runs. function_exists( 'get_permalink' ) only proves the function is
	 * *loaded*; it says nothing about whether $wp_rewrite exists yet, so that
	 * guard cannot substitute for deferring the call itself. The resolver is
	 * invoked lazily, the first time a checkout link is actually built — by
	 * which point WordPress has finished booting.
	 *
	 * @param (callable():string)|null $resolver
	 */
	public static function set_resolver( ?callable $resolver ): void {
		self::$resolver = $resolver;
		self::$resolved = null;
		self::$base_url = '';
	}

	public static function base_url(): string {
		return self::resolve();
	}

	private static function resolve(): string {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}
		if ( null !== self::$resolver ) {
			self::$resolved = ( self::$resolver )();
			return self::$resolved;
		}
		return self::$base_url;
	}

	/** The checkout URL for a price, or '' when we cannot build one. */
	public static function url( string $price_id ): string {
		$base_url = self::resolve();
		if ( '' === $base_url || '' === $price_id ) {
			return '';
		}
		$separator = ( false !== strpos( $base_url, '?' ) ) ? '&' : '?';

		return $base_url . $separator
			. rawurlencode( self::PRICE_PARAM ) . '=' . rawurlencode( $price_id )
			. '&' . rawurlencode( self::QUANTITY_PARAM ) . '=1';
	}
}
