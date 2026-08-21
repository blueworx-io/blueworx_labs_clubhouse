<?php
// includes/dashboard/class-commerce-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout and order confirmation, in the member area's look.
 *
 * The same frame as the member dashboard, minus the nav: someone mid-purchase
 * should not be offered six places to wander off to. The page's own content is
 * passed through untouched — the shop renders the shop, exactly as everywhere
 * else in this plugin.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Commerce_Pages {

	/** After SureCart has expanded its own blocks into the content. */
	private const PRIORITY = 30;

	/**
	 * The pages taken over, keyed the way Shop_Pages keys them.
	 *
	 * @var array<string,array{title:string,lede:string}>
	 */
	// Checkout no longer reads its own title and lede — it draws its heading
	// from Dashboard_Shell::checkout() instead — but the entry stays: dress()
	// gates on isset( self::PAGES[ $key ] ) to decide whether a post is one of
	// ours at all, and removing it would stop the page being dressed.
	public const PAGES = array(
		'checkout'           => array(
			'title' => 'Checkout',
			'lede'  => 'A few details and you are done.',
		),
		'order-confirmation' => array(
			'title' => 'Thank you',
			'lede'  => 'Your order is confirmed. A receipt is on its way by email.',
		),
	);

	/**
	 * Guards against this filter re-entering itself.
	 *
	 * The page's own content is the shop's checkout, and a block or shortcode
	 * inside it is free to apply the_content itself. On the same post that would
	 * come straight back in here and recurse until the request ran out of
	 * memory — a white screen where someone is trying to pay. The shop does not
	 * do it today; the guard costs a boolean and makes it impossible.
	 */
	private static bool $rendering = false;

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'the_content', array( self::class, 'dress' ), self::PRIORITY );
		// A block theme draws its own core/post-title block above whatever
		// the_content returns. A browser test caught it: on the checkout fixture
		// the page carried two h1s — the theme's own title, then the frame's
		// "Checkout" heading right underneath it — on the one page where a buyer
		// is mid-payment. render_block sees each block before it lands on the
		// page, so the theme's title is dropped here rather than fought after
		// the fact once both are already in the markup.
		add_filter( 'render_block', array( self::class, 'strip_post_title' ), 10, 3 );
		// These two pages serve their own document — see serve_template() below.
		add_filter( 'template_include', array( self::class, 'serve_template' ) );
	}

	/** Where this plugin's commerce template lives. */
	private static function template_path(): string {
		return dirname( __DIR__, 2 ) . '/templates/commerce.php';
	}

	/**
	 * Which template a page should be served with. Pure.
	 *
	 * @param string $page_key Empty for any page this plugin does not dress.
	 * @param string $default  Whatever WordPress had chosen.
	 * @param string $ours     This plugin's commerce template.
	 */
	public static function template_for( string $page_key, string $default, string $ours ): string {
		return isset( self::PAGES[ $page_key ] ) ? $ours : $default;
	}

	/**
	 * Give checkout and the thank-you page a document of their own.
	 *
	 * Left to the theme, both render inside its page template, which draws its
	 * own header above the frame and its own footer below it — so the checkout
	 * carried the club's site footer, several hundred pixels of it, underneath
	 * its own. The approved design is a self-contained checkout, and a page
	 * cannot be self-contained while something else owns the document.
	 *
	 * The frame itself is untouched: templates/commerce.php runs WordPress's
	 * ordinary loop, dress() is still a the_content filter, and it still fires
	 * exactly where it did.
	 *
	 * @param string $template
	 */
	public static function serve_template( $template ): string {
		$template = (string) $template;
		if ( ! function_exists( 'get_queried_object_id' ) ) {
			return $template;
		}
		return self::template_for(
			Blueworx_Clubhouse_Dashboard_Assets::page_key( (int) get_queried_object_id() ),
			$template,
			self::template_path()
		);
	}

	/**
	 * Blanks the theme's own core/post-title block on the pages this plugin
	 * dresses, so the frame's own heading is the only one on the page.
	 *
	 * @param string              $block_content
	 * @param array<string,mixed> $block
	 * @param mixed               $instance
	 */
	public static function strip_post_title( $block_content, $block, $instance = null ): string {
		$block_content = (string) $block_content;
		// render_block fires for every block on every page of the site, and
		// core/post-title appears at most once or twice on any of them. Bail on
		// the block name alone — an array lookup — before page_key() below,
		// which reads options, ever runs.
		if ( ! is_array( $block ) || 'core/post-title' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}
		$post_id = 0;
		if ( is_object( $instance ) && isset( $instance->context['postId'] ) ) {
			$post_id = (int) $instance->context['postId'];
		} elseif ( function_exists( 'get_the_ID' ) ) {
			$post_id = (int) get_the_ID();
		}
		return '' === Blueworx_Clubhouse_Dashboard_Assets::page_key( $post_id ) ? $block_content : '';
	}

	/**
	 * Which of these pages a post is, or '' for anything else. Pure.
	 *
	 * An id of 0 means the shop has not recorded that page, and must never
	 * match — 0 would otherwise dress whatever a broken query returned.
	 */
	public static function page_key( int $post_id, int $checkout_id, int $confirmation_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		if ( $post_id === $checkout_id ) {
			return 'checkout';
		}
		if ( $post_id === $confirmation_id ) {
			return 'order-confirmation';
		}
		return '';
	}

	/**
	 * @param string $content
	 */
	public static function dress( $content ): string {
		$content = (string) $content;
		if ( self::$rendering ) {
			return $content;
		}
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$key = Blueworx_Clubhouse_Dashboard_Assets::page_key( (int) get_the_ID() );
		if ( ! isset( self::PAGES[ $key ] ) ) {
			return $content;
		}
		// No check on whether anyone is signed in, unlike the member dashboard:
		// a club sells to guests, and someone paying without an account is an
		// ordinary sale rather than a mistake. Nothing here needs to know who
		// they are — the page's own content is the shop's, and it decides.

		self::$rendering = true;
		try {
			Blueworx_Clubhouse_Dashboard_Assets::enqueue();

			if ( 'checkout' === $key ) {
				// Resolved once and closed over, rather than called from inside the
				// visibility callback: context() rebuilds options storage, the full
				// look registry and the demo lookup on every call, and the callback
				// below runs once per candidate link. On the page where a buyer is
				// waiting to pay, that would mean building it up to three times.
				$visibility = Blueworx_Clubhouse_Frontend::context()->visibility;
				return Blueworx_Clubhouse_Dashboard_Shell::checkout(
					array(
						'club_name'  => Blueworx_Clubhouse_Member_Dashboard::club_name(),
						'logo_url'   => Blueworx_Clubhouse_Member_Dashboard::logo_url(),
						'home_url'   => Blueworx_Clubhouse_Frontend::link_url( 'home' ),
						'home_label' => self::back_label( Blueworx_Clubhouse_Member_Dashboard::club_name() ),
						'body'       => $content,
						// Nothing in the plugin stores a club's registration number, and
						// adding a settings field for it is outside this work — left
						// blank on purpose, not an oversight.
						'footnote'   => '',
						'links'      => self::footer_links(
							static fn ( string $slug ): bool => $visibility->is_page_visible( $slug ),
							static fn ( string $slug ): string => Blueworx_Clubhouse_Frontend::link_url( $slug )
						),
					)
				);
			}

			return Blueworx_Clubhouse_Dashboard_Shell::bare(
				self::PAGES[ $key ]['title'],
				self::PAGES[ $key ]['lede'],
				Blueworx_Clubhouse_Dashboard_Shell::card( '', $content ),
				Blueworx_Clubhouse_Frontend::link_url( 'home' ),
				Blueworx_Clubhouse_Member_Dashboard::club_name()
			);
		} finally {
			self::$rendering = false;
		}
	}

	/**
	 * The club pages a buyer is entitled to read before paying, and their
	 * addresses. Pure — the callers hand in the two questions this cannot
	 * answer itself.
	 *
	 * Contact is here as well as the two legal pages: someone who has hit a
	 * problem halfway through paying needs a way to ask about it, and the
	 * header offers none.
	 *
	 * @param callable(string):bool   $visible
	 * @param callable(string):string $url
	 * @return array<int,array{label:string,href:string}>
	 */
	public static function footer_links( callable $visible, callable $url ): array {
		$out = array();
		foreach ( array(
			'terms'   => 'Terms and conditions',
			'privacy' => 'Privacy notice',
			'contact' => 'Contact the club',
		) as $slug => $label ) {
			if ( ! $visible( $slug ) ) {
				continue;
			}
			$href = trim( $url( $slug ) );
			if ( '' === $href ) {
				continue;
			}
			$out[] = array(
				'label' => $label,
				'href'  => $href,
			);
		}
		return $out;
	}

	/**
	 * "Back to Crewe Vagrants", or the generic wording when a club has not
	 * named itself yet. Pure.
	 */
	public static function back_label( string $club_name ): string {
		$club = trim( $club_name );
		return '' !== $club ? 'Back to ' . $club : 'Back to the club site';
	}
}
