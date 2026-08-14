<?php
// includes/membership/class-shop-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the shop's own pages are there and reachable, and the repair when
 * they are not.
 *
 * The shop runs the shop. This plugin does not build checkouts, take payments
 * or style SureCart's screens — it makes sure the pages SureCart needs exist,
 * so the links pointing at them go somewhere. That is the whole job here.
 *
 * Which is why the repair does not write these pages itself. It calls
 * SureCart's own seeder, the same one its activation runs, so the pages come
 * out exactly as SureCart makes them and stay right when SureCart changes. The
 * one thing done directly is republishing a page that already exists and has
 * been trashed — the seeder would create a second page beside it rather than
 * bring it back, leaving the club with two checkouts and its links pointing at
 * the wrong one.
 *
 * Nothing here runs on its own. The controller shows a notice and an owner
 * presses the button.
 *
 * Page names and option keys are read from SureCart's source, and the whole
 * flow is verified against a real install — see
 * docs/integrations/surecart-notes.md.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Shop_Pages {

	/** The container key SureCart registers its page seeder under. */
	private const SEEDER = 'surecart.pages.seeder';

	/** A published page is the only kind a buyer can reach. */
	private const REACHABLE = 'publish';

	public const STATUS_NO_SHOP     = 'no-shop';
	public const STATUS_OK          = 'ok';
	public const STATUS_MISSING     = 'missing';
	public const STATUS_UNPUBLISHED = 'unpublished';

	/**
	 * The pages the shop needs, and what a visitor loses without each.
	 *
	 * 'seeded' records whether SureCart's own seeder creates it. Its
	 * order-confirmation page is made during onboarding rather than by the
	 * seeder, so a missing one is worth saying out loud but is not something
	 * the repair button can fix — better to send the owner to SureCart than to
	 * invent a page SureCart did not write.
	 *
	 * @return array<string,array{label:string,consequence:string,seeded:bool}>
	 */
	public static function pages(): array {
		return array(
			'checkout'           => array(
				'label'       => 'checkout page',
				'consequence' => 'nobody can pay and membership Join buttons fall back to your contact page',
				'seeded'      => true,
			),
			'order-confirmation' => array(
				'label'       => 'order confirmation page',
				'consequence' => 'anyone who pays sees nothing afterwards',
				'seeded'      => false,
			),
			'dashboard'          => array(
				'label'       => 'customer dashboard',
				'consequence' => 'members have nowhere to manage what they have paid for',
				'seeded'      => true,
			),
			'shop'               => array(
				'label'       => 'shop page',
				'consequence' => 'your products have nowhere to be listed',
				'seeded'      => true,
			),
		);
	}

	/**
	 * Where SureCart keeps a page's id. Built the way SureCart builds it
	 * (PageService::getOptionName), so the two cannot drift.
	 */
	public static function option_name( string $key ): string {
		return 'surecart_' . $key . '_page_id';
	}

	/**
	 * What state one page is in. Pure — status() below reads the arguments off
	 * the site.
	 */
	public static function decide( bool $shop_active, int $page_id, string $post_status ): string {
		if ( ! $shop_active ) {
			return self::STATUS_NO_SHOP;
		}
		if ( $page_id > 0 && self::REACHABLE === $post_status ) {
			return self::STATUS_OK;
		}
		if ( $page_id > 0 && '' !== $post_status ) {
			return self::STATUS_UNPUBLISHED;
		}
		return self::STATUS_MISSING;
	}

	/** Only the pages with something wrong. Pure.
	 *
	 * @param array<string,string> $statuses From statuses().
	 * @return array<string,string>
	 */
	public static function problems( array $statuses ): array {
		return array_filter(
			$statuses,
			static fn ( string $status ): bool => in_array( $status, array( self::STATUS_MISSING, self::STATUS_UNPUBLISHED ), true )
		);
	}

	/**
	 * Which of those the repair button can actually put right. Pure.
	 *
	 * An unpublished page always can be — that is just republishing it. A
	 * missing one only if SureCart's seeder makes it.
	 *
	 * @param array<string,string>                                        $problems From problems().
	 * @param array<string,array{label:string,consequence:string,seeded:bool}> $pages    From pages().
	 * @return array<string,string>
	 */
	public static function repairable( array $problems, array $pages ): array {
		return array_filter(
			$problems,
			static fn ( string $status, string $key ): bool =>
				self::STATUS_UNPUBLISHED === $status || ! empty( $pages[ $key ]['seeded'] ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/** The stored id for a page, or 0 when the option is absent or junk. */
	public static function page_id( string $key ): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}
		$stored = get_option( self::option_name( $key ), 0 );
		return is_numeric( $stored ) ? max( 0, (int) $stored ) : 0;
	}

	/** The state of one page on this site. */
	public static function status( string $key ): string {
		$active  = Blueworx_Clubhouse_SureCart_Products::is_active();
		$page_id = $active ? self::page_id( $key ) : 0;
		return self::decide( $active, $page_id, self::post_status( $page_id ) );
	}

	/**
	 * The state of every page.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		$out = array();
		foreach ( array_keys( self::pages() ) as $key ) {
			$out[ $key ] = self::status( $key );
		}
		return $out;
	}

	/**
	 * A page's URL, or '' when there is not a reachable one.
	 *
	 * '' is the honest answer rather than a failure: for the checkout page it
	 * makes every membership tier fall back to its typed price and the contact
	 * link, which is what a club with no working shop should show.
	 */
	public static function url( string $key ): string {
		if ( self::STATUS_OK !== self::status( $key ) || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( self::page_id( $key ) );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Whether SureCart's seeder can be reached to create what is missing. False
	 * on a shop whose internals have moved on, in which case the notice says so
	 * rather than offering a button that would do nothing.
	 */
	public static function can_seed(): bool {
		if ( ! Blueworx_Clubhouse_SureCart_Products::is_active() || ! is_callable( array( 'SureCart', 'resolve' ) ) ) {
			return false;
		}
		try {
			return is_object( \SureCart::resolve( self::SEEDER ) );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Put right what can be put right: republish the pages that are only
	 * trashed, then let SureCart seed whatever is still missing.
	 *
	 * Returns whether every problem it could act on is now resolved.
	 */
	public static function repair(): bool {
		$problems = self::problems( self::statuses() );

		foreach ( $problems as $key => $status ) {
			if ( self::STATUS_UNPUBLISHED === $status ) {
				self::publish_existing( self::page_id( $key ) );
			}
		}

		$still_missing = array_filter(
			self::problems( self::statuses() ),
			static fn ( string $status ): bool => self::STATUS_MISSING === $status
		);
		if ( array() !== $still_missing && self::can_seed() ) {
			self::seed();
		}

		// The link catalogue memoises which shop pages exist, and this request
		// has just changed the answer — without this the Shop link would not
		// appear in the nav until the next page load.
		Blueworx_Clubhouse_Link_Catalogue::forget_shop_targets();

		return array() === self::repairable( self::problems( self::statuses() ), self::pages() );
	}

	/**
	 * Hand page creation back to SureCart. Its seeder is idempotent — it finds
	 * or creates, so pages already there are left exactly as they are.
	 */
	private static function seed(): void {
		try {
			\SureCart::resolve( self::SEEDER )->seed();
		} catch ( \Throwable $e ) {
			// A shop that cannot seed is reported by the next status read; there
			// is nothing to say here that the notice will not say better.
			return;
		}
	}

	/** Bring a trashed or draft page back into public view. */
	private static function publish_existing( int $page_id ): bool {
		if ( $page_id <= 0 || ! function_exists( 'wp_update_post' ) ) {
			return false;
		}
		$result = wp_update_post( array(
			'ID'          => $page_id,
			'post_status' => self::REACHABLE,
		) );
		return is_numeric( $result ) && (int) $result > 0;
	}

	/**
	 * A page's status, or '' when the id points at nothing.
	 *
	 * get_post_status() answers false for a missing post and a status string
	 * otherwise, including 'trash' — the case a bare permalink lookup silently
	 * turned into a dead link.
	 */
	private static function post_status( int $page_id ): string {
		if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) ) {
			return '';
		}
		$status = get_post_status( $page_id );
		return is_string( $status ) ? $status : '';
	}
}
