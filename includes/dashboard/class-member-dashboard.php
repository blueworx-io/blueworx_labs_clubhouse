<?php
// includes/dashboard/class-member-dashboard.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member's account page: this plugin's frame, the other plugins' data.
 *
 * SureCart seeds a customer dashboard page and LatePoint has a shortcode, and
 * a club that installs both ends up with a page that reads as two plugins in a
 * stack. This takes the page over: one design, one nav, and each panel filled
 * by whichever plugin owns that data. We do not re-render their records — see
 * the spec's non-goals.
 *
 * Taken over by filtering the_content rather than by a template, for the same
 * reason the welcome pack does it: the page is SureCart's and its template is
 * theirs to change. Priority 30, after SureCart expands its own dashboard at
 * 10 and after the welcome pack's old filter at 20, so whatever was there is
 * replaced rather than raced.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Member_Dashboard {

	/** After SureCart (10) and after the welcome pack's own filter (20). */
	private const PRIORITY = 30;

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		Blueworx_Clubhouse_Plugin_Slot::install_wordpress();
		Blueworx_Clubhouse_Dashboard_Assets::register();
		add_filter( 'the_content', array( self::class, 'take_over' ), self::PRIORITY );
	}

	/**
	 * Guards against this filter re-entering itself.
	 *
	 * The body below renders other plugins' blocks and shortcodes, and any one
	 * of them is free to apply the_content itself. On the same post that would
	 * come straight back in here and recurse until the memory limit killed the
	 * request — a white screen on the member's own account page. Neither plugin
	 * does it today; the guard costs a boolean and makes it impossible.
	 */
	private static bool $rendering = false;

	/** Whether this post is the page the member area is on. */
	public static function owns( int $post_id ): bool {
		return 'dashboard' === Blueworx_Clubhouse_Dashboard_Assets::page_key( $post_id );
	}

	/**
	 * Replace the customer dashboard with ours, and leave every other page
	 * alone.
	 *
	 * The cheap checks come first so the vast majority of requests leave after
	 * one comparison.
	 *
	 * @param string $content
	 */
	public static function take_over( $content ): string {
		$content = (string) $content;
		if ( self::$rendering ) {
			return $content;
		}
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! self::owns( (int) get_the_ID() ) ) {
			return $content;
		}
		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {
			// Nobody is signed in, so there is no account to show. The page's own
			// content is the shop's sign-in form, which is exactly what a visitor
			// who bookmarked this address needs — our frame would tell them the
			// club had set nothing up, which is not true and not their problem.
			return $content;
		}

		self::$rendering = true;
		try {
			return self::render( $content );
		} finally {
			self::$rendering = false;
		}
	}

	/**
	 * The member area itself, once take_over() has decided this request is ours.
	 *
	 * @param string $content The page's own content, returned untouched if a view cannot be drawn.
	 */
	private static function render( string $content ): string {
		Blueworx_Clubhouse_Dashboard_Assets::enqueue();
		self::enqueue_shop_assets();

		$views   = Blueworx_Clubhouse_Dashboard_Views::available(
			Blueworx_Clubhouse_SureCart_Products::is_active(),
			Blueworx_Clubhouse_Integrations::has_latepoint()
		);
		$current = Blueworx_Clubhouse_Dashboard_Views::resolve( self::requested_view(), $views );
		$view    = Blueworx_Clubhouse_Dashboard_Views::find( $current, $views );
		if ( null === $view ) {
			return $content; // Cannot happen — resolve() only returns a key it found.
		}

		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/';
		$base = self::page_url();
		// The page's own content is dropped rather than kept. On this page it is
		// the shop's dashboard block — the stack of panels this whole screen
		// exists to replace — so showing it as well would print the member's
		// orders twice. Checkout keeps its content for the opposite reason: what
		// is on that page is the payment form, and only the shop can draw it.
		$welcome = Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $current ? self::welcome_pack() : '';
		$body    = Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $current
			? self::overview( $welcome, $views, $home, $base )
			: self::view_body( $view, '', $home );

		// The pack brings its own rules, so they are only worth printing on a
		// view that actually draws one.
		$style = '' !== $welcome
			? '<style>' . Blueworx_Clubhouse_Welcome_Pack::css( ...self::accent() ) . '</style>'
			: '';

		return $style
			. Blueworx_Clubhouse_Dashboard_Shell::page(
				$views,
				$current,
				(string) $view['title'],
				(string) $view['lede'],
				$body,
				$home,
				self::club_name(),
				$base,
				self::logout_url()
			);
	}

	/**
	 * This page's own address, which every view link is built on.
	 *
	 * Resolved here rather than in the shell, which stays free of WordPress. It
	 * matters on a club whose permalinks are set to Plain, where the dashboard
	 * lives at /?page_id=42: a bare '?view=orders' would replace the whole query
	 * and send the member to the front page instead.
	 */
	private static function page_url(): string {
		if ( ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink();
		return is_string( $url ) ? $url : '';
	}

	/**
	 * The signed sign-out address, or '' when nothing can build one.
	 *
	 * The club's own header and footer are deliberately kept off this page, so
	 * without this a signed-in member has no way out of it. It is the same link
	 * the club site's header offers — one logout journey, already nonced and
	 * already returning wherever the club chose.
	 */
	private static function logout_url(): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Auth' ) || ! function_exists( 'wp_nonce_url' ) ) {
			return '';
		}
		return (string) Blueworx_Clubhouse_Auth::logout_url();
	}

	/** The view named in the address, unfiltered — resolve() decides what it means. */
	private static function requested_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which panel to show, not acting.
		$raw = $_GET[ Blueworx_Clubhouse_Dashboard_Shell::VIEW_ARG ] ?? '';
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * One view's contents.
	 *
	 * A shortcode view is handed the whole panel — LatePoint brings its own
	 * tabs and does not belong inside a card of ours. Blocks each get a card.
	 * A panel whose plugin says nothing shows the honest empty state rather
	 * than an empty card.
	 *
	 * @param array<string,mixed> $view
	 */
	public static function view_body( array $view, string $welcome, string $home_url ): string {
		$shortcode = (string) $view['shortcode'];
		if ( '' !== $shortcode ) {
			$out = Blueworx_Clubhouse_Plugin_Slot::shortcode( $shortcode );
			return '' !== $out ? $out : self::not_set_up( $home_url );
		}

		$out = '';
		foreach ( (array) $view['blocks'] as $block ) {
			$panel = Blueworx_Clubhouse_Plugin_Slot::block( (string) $block );
			if ( '' !== $panel ) {
				$out .= Blueworx_Clubhouse_Dashboard_Shell::card( '', $panel );
			}
		}
		if ( '' === $out ) {
			return self::not_set_up( $home_url );
		}
		// $welcome is always '' here in production — take_over() only ever
		// passes the pack into overview(). Kept as a parameter so the brief's
		// direct calls to view_body() can still exercise it; not a live path.
		return ( '' !== $welcome ? $welcome : '' ) . $out;
	}

	/**
	 * The overview: the club's welcome, then the way into everything else.
	 *
	 * The design draws next sessions, recent orders and an outstanding-invoice
	 * notice here. Composing those means reading two plugins' records and
	 * re-rendering them, which the spec rules out — so this is the pack plus
	 * links, and the records stay where the plugins draw them.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param string                         $base The dashboard page's own address, for the links.
	 */
	public static function overview( string $welcome, array $views, string $home_url, string $base = '' ): string {
		$links = '';
		foreach ( $views as $view ) {
			$key = (string) $view['key'];
			if ( Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $key ) {
				continue; // A link to the page you are on is a dead control.
			}
			$links .= '<a class="bw-card clubhouse-member__quick" href="'
				. htmlspecialchars( Blueworx_Clubhouse_Dashboard_Shell::view_url( $key, $base ), ENT_QUOTES, 'UTF-8' ) . '">'
				. '<span class="clubhouse-member__quick-icon">' . Blueworx_Clubhouse_Dashboard_Shell::icon( (string) $view['icon'] ) . '</span>'
				. '<span class="clubhouse-member__quick-title">' . htmlspecialchars( (string) $view['label'], ENT_QUOTES, 'UTF-8' ) . '</span>'
				. '<span class="clubhouse-member__quick-lede">' . htmlspecialchars( (string) $view['lede'], ENT_QUOTES, 'UTF-8' ) . '</span>'
				. '</a>';
		}
		if ( '' !== $links ) {
			$links = '<div class="clubhouse-member__quicks">' . $links . '</div>';
		}
		if ( '' === $welcome && '' === $links ) {
			// No pack written, and no other views to link to (SureCart inactive
			// and LatePoint absent, or deactivated after the page was seeded).
			// A member must never meet a blank frame here.
			return self::not_set_up( $home_url );
		}
		return $welcome . $links;
	}

	/** What a member sees where a panel would be if the club has not set that part up. */
	private static function not_set_up( string $home_url ): string {
		return Blueworx_Clubhouse_Dashboard_Shell::card(
			'',
			Blueworx_Clubhouse_Dashboard_Shell::empty_state(
				'Nothing here yet',
				'The club has not set this part up. Nothing is missing from your membership.',
				$home_url,
				'Back to the club site'
			)
		);
	}

	/** The club's welcome pack, or '' when nobody has written one. */
	private static function welcome_pack(): string {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		if ( ! ( new Blueworx_Clubhouse_Visibility( $storage ) )->is_section_visible( 'home', Blueworx_Clubhouse_Welcome_Pack::SECTION ) ) {
			return '';
		}
		$store = new Blueworx_Clubhouse_Content_Store( $storage );
		return Blueworx_Clubhouse_Welcome_Pack::render(
			array(
				'heading'    => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'heading', '' ),
				'body'       => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'body', '' ),
				'link_label' => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'link_label', '' ),
				'link_href'  => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'link_href', '' ),
			)
		);
	}

	/**
	 * The club's accent, for the welcome pack's own rules. Derived the same way
	 * the pack derives it — against a white ground and near-black text.
	 *
	 * @return array{0:string,1:string}
	 */
	private static function accent(): array {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		$derived  = Blueworx_Clubhouse_Color_Engine::derive( $branding->get_accent(), '#ffffff', '#111111' );
		return array( (string) ( $derived['--color-accent'] ?? '' ), (string) ( $derived['--color-accent-ink'] ?? '' ) );
	}

	/** The club's name for the page head, or '' when nothing is set. */
	public static function club_name(): string {
		if ( ! function_exists( 'get_bloginfo' ) ) {
			return '';
		}
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * SureCart's panels are web components, and the script that boots them is
	 * declared by its dashboard wrapper block — the one this page replaces. Its
	 * customer blocks declare no script of their own, so without this a panel
	 * can render correct markup that never comes alive. Guarded, so a shop that
	 * registers these under other names, or no shop at all, costs nothing.
	 */
	private static function enqueue_shop_assets(): void {
		if ( ! function_exists( 'wp_script_is' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		if ( wp_script_is( 'surecart-components', 'registered' ) ) {
			wp_enqueue_script( 'surecart-components' );
		}
		if ( function_exists( 'wp_style_is' ) && wp_style_is( 'surecart-themes-default', 'registered' ) ) {
			wp_enqueue_style( 'surecart-themes-default' );
		}
	}
}
