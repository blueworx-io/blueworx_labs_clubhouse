<?php
// includes/membership/class-checkout-page.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the shop has a checkout page a buyer can actually reach, and the
 * repair when it does not.
 *
 * SureCart creates this page itself, on activation — a page named "Checkout"
 * holding its checkout-form block, with the page's id in the option below. So
 * on a healthy site there is nothing here to do, and this class exists for the
 * two states that are not healthy:
 *
 *   - the page was deleted or trashed, which is the state the club's own site
 *     is in (issue #169) and the reason every Join button falls back to the
 *     contact page;
 *   - the page is a draft, which reads as "exists" to the option but is a 404
 *     to a buyer.
 *
 * Both are repaired rather than worked around, because a checkout page SureCart
 * does not recognise is worse than none: SureCart's own links, its slide-out
 * cart and its post-purchase redirects all read the same option. So the repair
 * writes what SureCart writes — same slug, same block, same option — and a
 * page created here is indistinguishable from one SureCart seeded.
 *
 * Nothing in here runs on its own. Blueworx_Clubhouse_Checkout_Page_Controller
 * shows an admin notice and an owner presses the button; the plugin never
 * creates a page on a club's live site unasked.
 *
 * Every name below is read from SureCart's own source rather than guessed —
 * see docs/integrations/surecart-notes.md.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout_Page {

	/**
	 * Where SureCart keeps the id of its checkout page.
	 *
	 * SureCart builds this name at runtime as 'surecart_' . $option . '_' .
	 * $post_type . '_id' (PageService::getOptionName), so 'checkout' + 'page'
	 * is this constant. It is also listed literally in its uninstall routine.
	 */
	public const PAGE_OPTION = 'surecart_checkout_page_id';

	/** Where SureCart keeps the id of the checkout form the page renders. */
	public const FORM_OPTION = 'surecart_checkout_sc_form_id';

	/** The post type SureCart stores checkout forms in. */
	public const FORM_POST_TYPE = 'sc_form';

	/** The slug and title SureCart gives the page, matched so a repair looks the same. */
	public const SLUG  = 'checkout';
	public const TITLE = 'Checkout';

	/** There is no shop on this site, so there is nothing to check. */
	public const STATUS_NO_SHOP = 'no-shop';

	/** A published checkout page exists. Nothing to do. */
	public const STATUS_OK = 'ok';

	/** No checkout page at all — deleted, or never seeded. */
	public const STATUS_MISSING = 'missing';

	/** A checkout page exists but is trashed or a draft, so a buyer gets a 404. */
	public const STATUS_UNPUBLISHED = 'unpublished';

	/**
	 * A checkout page is missing AND the shop has no checkout form to put on
	 * one. Creating the page anyway would produce a checkout that renders
	 * nothing, so this state sends the owner to SureCart instead.
	 */
	public const STATUS_NO_FORM = 'no-form';

	/**
	 * The only post status a buyer can reach. Deliberately stricter than
	 * SureCart's own check, which treats a draft as a usable page: a draft is a
	 * 404 to a logged-out visitor, which is every buyer. 'private' is excluded
	 * for the same reason — visible to staff, not to the person paying.
	 */
	private const REACHABLE = 'publish';

	/**
	 * What state the checkout page is in. Pure, so every branch is testable
	 * without WordPress; status() below reads the arguments off the site.
	 *
	 * The form id arrives as a callable because finding it is a database query
	 * and only the "there is no page" branch needs it — while every membership
	 * tier on every front-end render resolves a checkout link through here.
	 *
	 * @param bool           $shop_active Whether SureCart is live here.
	 * @param int            $page_id     The stored checkout page id, 0 if none.
	 * @param string         $post_status That page's status, '' if the id points at nothing.
	 * @param callable():int $form_id     The checkout form id, 0 if the shop has none.
	 */
	public static function decide( bool $shop_active, int $page_id, string $post_status, callable $form_id ): string {
		if ( ! $shop_active ) {
			return self::STATUS_NO_SHOP;
		}
		if ( $page_id > 0 && self::REACHABLE === $post_status ) {
			return self::STATUS_OK;
		}
		// A page that exists but cannot be reached is republished, not replaced:
		// creating a second one would leave the club with two checkouts and
		// WordPress would suffix the slug, so SureCart's own links would point
		// at the wrong one.
		if ( $page_id > 0 && '' !== $post_status ) {
			return self::STATUS_UNPUBLISHED;
		}
		return $form_id() > 0 ? self::STATUS_MISSING : self::STATUS_NO_FORM;
	}

	/**
	 * The page content SureCart puts on its own checkout page: its checkout-form
	 * block, naming the form to render. Pure.
	 *
	 * The id is cast and interpolated rather than JSON-encoded because the block
	 * comment's attribute object is not arbitrary JSON — SureCart matches this
	 * exact shape when it looks the form up again (Models\Form).
	 */
	public static function content_for( int $form_id ): string {
		return '<!-- wp:surecart/checkout-form {"id":' . $form_id . '} --><!-- /wp:surecart/checkout-form -->';
	}

	/**
	 * Whether a repair is something an owner can act on, as opposed to a healthy
	 * site or one with no shop at all. Pure.
	 */
	public static function needs_attention( string $status ): bool {
		return in_array( $status, array( self::STATUS_MISSING, self::STATUS_UNPUBLISHED, self::STATUS_NO_FORM ), true );
	}

	/** The stored checkout page id, or 0 when the option is absent or junk. */
	public static function page_id(): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}
		$stored = get_option( self::PAGE_OPTION, 0 );
		return is_numeric( $stored ) ? max( 0, (int) $stored ) : 0;
	}

	/**
	 * The checkout form to render on the page.
	 *
	 * The option is SureCart's own record of the form it seeded. It is checked
	 * first and the post-type query is the fallback, because a site whose option
	 * was cleared may still have a perfectly good form sitting there.
	 */
	public static function form_id(): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}
		$stored = get_option( self::FORM_OPTION, 0 );
		if ( is_numeric( $stored ) && (int) $stored > 0 ) {
			return (int) $stored;
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$forms = get_posts( array(
			'post_type'   => self::FORM_POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
		) );
		if ( ! is_array( $forms ) || array() === $forms ) {
			return 0;
		}
		$first = reset( $forms );
		return is_numeric( $first ) ? (int) $first : 0;
	}

	/** The status of this site's checkout page. */
	public static function status(): string {
		$active  = Blueworx_Clubhouse_SureCart_Products::is_active();
		$page_id = $active ? self::page_id() : 0;

		return self::decide(
			$active,
			$page_id,
			self::post_status( $page_id ),
			static fn (): int => self::form_id()
		);
	}

	/**
	 * The checkout page's URL, or '' when there is not a reachable one.
	 *
	 * '' is the honest answer rather than a failure: it makes every membership
	 * tier fall back to its typed price and the contact link, which is what a
	 * club with no working shop should show.
	 */
	public static function url(): string {
		if ( self::STATUS_OK !== self::status() || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( self::page_id() );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Put the site into STATUS_OK: publish the page that is there, or create the
	 * one that is not. Returns whether the site is healthy afterwards.
	 *
	 * Called only from the controller's nonce'd, capability-gated handler.
	 */
	public static function repair(): bool {
		$status = self::status();
		if ( self::STATUS_UNPUBLISHED === $status ) {
			return self::publish_existing( self::page_id() );
		}
		if ( self::STATUS_MISSING === $status ) {
			return self::create( self::form_id() );
		}
		// OK, no shop, or no form: nothing here can fix it.
		return self::STATUS_OK === $status;
	}

	/** Move an existing checkout page back into public view. */
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
	 * Create the checkout page and tell SureCart where it is.
	 *
	 * The option is written whether or not SureCart would have written it
	 * itself, because that option is the whole contract: SureCart's links, its
	 * cart and its redirects all resolve through it, and a page it cannot find
	 * is a page nothing points at.
	 */
	private static function create( int $form_id ): bool {
		if ( $form_id <= 0 || ! function_exists( 'wp_insert_post' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}
		$page_id = wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => self::REACHABLE,
			'post_name'      => self::SLUG,
			'post_title'     => self::TITLE,
			'post_content'   => self::content_for( $form_id ),
			'comment_status' => 'closed',
		) );
		if ( ! is_numeric( $page_id ) || (int) $page_id <= 0 ) {
			return false;
		}
		update_option( self::PAGE_OPTION, (int) $page_id );
		return true;
	}

	/**
	 * A page's status, or '' when the id points at nothing.
	 *
	 * get_post_status() answers false for a missing post and a status string
	 * otherwise, including 'trash' — which is precisely the case this class
	 * exists to catch, and the one a bare get_permalink() call silently turned
	 * into a dead link.
	 */
	private static function post_status( int $page_id ): string {
		if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) ) {
			return '';
		}
		$status = get_post_status( $page_id );
		return is_string( $status ) ? $status : '';
	}
}
