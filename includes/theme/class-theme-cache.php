<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches the composed :root token string in storage (an autoloaded option in
 * production) so the colour math runs only when the look or accent changes.
 * The admin flow calls invalidate() on save.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Theme_Cache {

	private const CSS_KEY = 'root_css';
	private const SIG_KEY = 'root_css_sig';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	public function root_css(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): string {
		$signature  = self::signature( $look, $branding );
		$cached_css = $this->storage->get( self::CSS_KEY, '' );
		$cached_sig = $this->storage->get( self::SIG_KEY, '' );

		if ( is_string( $cached_css ) && '' !== $cached_css && $cached_sig === $signature ) {
			return $cached_css;
		}

		$css = Blueworx_Clubhouse_Theme_Css::to_css(
			Blueworx_Clubhouse_Theme_Css::compose( $look, $branding )
		);
		$this->storage->set( self::CSS_KEY, $css );
		$this->storage->set( self::SIG_KEY, $signature );
		return $css;
	}

	public function invalidate(): void {
		$this->storage->delete( self::CSS_KEY );
		$this->storage->delete( self::SIG_KEY );
	}

	/** @param array<string,string> $tokens */
	private static function serialize_tokens( array $tokens ): string {
		ksort( $tokens );
		$parts = array();
		foreach ( $tokens as $key => $value ) {
			$parts[] = $key . ':' . $value;
		}
		return implode( ';', $parts );
	}

	private static function signature(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): string {
		// Tokens depend on the look's slug, its shell token *contents*, the derived
		// accent, and the plugin version. Hashing the token contents + version means
		// an upgrade that changes a look's tokens (same slug/accent) still busts the
		// cache. The version constant is absent under PHPUnit, so guard it.
		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		$tokens  = self::serialize_tokens( $look->tokens() );
		return md5( $look->slug() . '|' . $branding->get_accent() . '|' . $tokens . '|' . $version );
	}
}
