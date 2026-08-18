<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The cache in front of whichever feed source is active, and the only thing the
 * renderer talks to. Three stores, copied deliberately from the SureCart price
 * cache (class-surecart-products.php):
 *
 *  - a transient holding the last good fetch, for the normal path;
 *  - a short failure marker, so a platform outage does not re-hit the source on
 *    every page load for the whole TTL;
 *  - a last-good option with no expiry, read only once a fetch has failed.
 *
 * Unlike prices, the feed is identical for every visitor, so there is no
 * logged-in/logged-out cache context to split on.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Social_Feed {

	/** Posts to show. */
	public const OK = 'ok';

	/** Nothing has ever been connected — the front end shows nothing at all. */
	public const NOT_CONNECTED = 'not_connected';

	/** A fetch failed and none has ever succeeded — the admin needs to know. */
	public const ERROR = 'error';

	/**
	 * Long enough that a page render almost never pays for a fetch; short
	 * enough that a post published this morning is on the site this afternoon.
	 */
	private const CACHE_TTL = 900;

	/** How long a failed fetch is remembered before the next request may try again. */
	private const FAILURE_TTL = 60;

	/**
	 * Deliberately NOT scoped to the plugin version, unlike cache_key(): this is
	 * the safety net a blip falls back to, and every release changes the
	 * version — keying it there would empty the net on every update, which is
	 * the exact failure it exists to prevent.
	 */
	private const LAST_GOOD_OPTION = 'blueworx_clubhouse_social_feed_last_good';

	private Blueworx_Clubhouse_Feed_Source $source;

	/** @var array<int,array<string,string>>|null Memoised so one render resolves once. */
	private ?array $resolved = null;

	private string $status = self::NOT_CONNECTED;

	public function __construct( Blueworx_Clubhouse_Feed_Source $source ) {
		$this->source = $source;
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	public function posts(): array {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$cached = get_transient( self::cache_key() );
		if ( is_array( $cached ) ) {
			$this->status   = array() === $cached ? self::NOT_CONNECTED : self::OK;
			$this->resolved = $cached;
			return $this->resolved;
		}

		if ( false !== get_transient( self::failure_key() ) ) {
			// A fetch failed moments ago. Serve whatever last succeeded rather
			// than paying for the failure again on every render of an outage.
			$this->resolved = $this->after_failure();
			return $this->resolved;
		}

		$fetched = $this->source->posts();
		if ( null === $fetched ) {
			set_transient( self::failure_key(), true, self::FAILURE_TTL );
			$this->resolved = $this->after_failure();
			return $this->resolved;
		}

		$posts = self::clean( $fetched );
		set_transient( self::cache_key(), $posts, self::CACHE_TTL );
		if ( array() !== $posts ) {
			// No expiry: this is read only when a later fetch fails, so it has
			// to still be here whenever that happens.
			update_option( self::LAST_GOOD_OPTION, $posts, false );
		}
		$this->status   = array() === $posts ? self::NOT_CONNECTED : self::OK;
		$this->resolved = $posts;
		return $this->resolved;
	}

	/**
	 * Which of the three states this feed is in. Resolves the feed if that has
	 * not happened yet, so callers may ask in either order.
	 */
	public function status(): string {
		$this->posts();
		return $this->status;
	}

	/**
	 * What to serve once a fetch has failed. Posts we have shown before stay up
	 * and the visitor is told nothing; a club should not lose its feed because
	 * Meta had a bad minute. With no history there is nothing to show, and that
	 * is a different fact from never having connected — the club's next action
	 * is to fix the connection, not to make one.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function after_failure(): array {
		$last         = self::last_good();
		$this->status = array() === $last ? self::ERROR : self::OK;
		return $last;
	}

	/**
	 * Drop anything that is not a usable post. A record with no id has no render
	 * key and one with no permalink is a card leading nowhere; both are better
	 * absent than drawn.
	 *
	 * @param array<int,mixed> $rows
	 * @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>
	 */
	private static function clean( array $rows ): array {
		$posts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id        = trim( (string) ( $row['id'] ?? '' ) );
			$permalink = trim( (string) ( $row['permalink'] ?? '' ) );
			if ( '' === $id || '' === $permalink ) {
				continue;
			}
			$posts[] = array(
				'id'        => $id,
				'image'     => (string) ( $row['image'] ?? '' ),
				'caption'   => (string) ( $row['caption'] ?? '' ),
				'date'      => (string) ( $row['date'] ?? '' ),
				'permalink' => $permalink,
			);
		}
		return $posts;
	}

	/**
	 * The last successfully fetched posts, or array() when none ever were.
	 * Filtered on the way out: the option has no expiry and nothing else
	 * validates it once written, so a value corrupted by a manual edit or a
	 * future format change must read as absent rather than reach the renderer.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function last_good(): array {
		$stored = get_option( self::LAST_GOOD_OPTION, array() );
		return is_array( $stored ) ? self::clean( $stored ) : array();
	}

	/** Transient key, scoped to the running plugin version like Theme_Cache. */
	private static function cache_key(): string {
		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		return 'blueworx_clubhouse_social_feed_' . md5( (string) $version );
	}

	/** Transient key for the short "a fetch just failed" marker — see FAILURE_TTL. */
	private static function failure_key(): string {
		return 'blueworx_clubhouse_social_feed_failed';
	}
}
