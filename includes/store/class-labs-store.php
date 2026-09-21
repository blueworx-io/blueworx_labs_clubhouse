<?php
// includes/store/class-labs-store.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place Clubhouse talks to the BlueWorx Labs plugin.
 *
 * Labs serves the member area, the checkout and the thank-you page — the
 * frame, the nav, the shop's panels, the redirect from SureCart's own
 * dashboard page. Clubhouse adds what only a club knows: a Bookings view
 * when LatePoint is here, the club's welcome pack above the overview, its
 * own profile questions under the shop's, the crest and the club's own
 * sign-in and sign-out, and which club pages a buyer may read before paying.
 *
 * Every Labs filter is hooked here, and every Labs function the rest of
 * Clubhouse needs is wrapped here, so nothing else in the plugin has to ask
 * whether Labs is installed. When it is not, every wrapper answers the safe
 * default — no page, no address, a plain "not available" screen — and the
 * admin sees one notice saying what to install.
 *
 * The pure halves (claim(), crest(), views(), notice_html()) are public so the
 * unit tests can hit them without a WordPress runtime.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Labs_Store {

	/** The first Labs release that ships the store pages and their filters. */
	public const MIN_LABS_VERSION = '1.87.0';

	/** Labs' own dashboard stylesheet, which the profile rules layer on top of. */
	private const LABS_STYLE_HANDLE = 'blueworx-store';

	/** The handle for the club's profile-card rules. */
	private const STYLE_HANDLE = 'clubhouse-member-profile';

	/** The shortcode that fills the Bookings view — LatePoint's own account tabs. */
	private const BOOKINGS_SHORTCODE = 'latepoint_customer_dashboard';

	/** The view every dashboard has, and the one Bookings sits directly after. */
	private const DASHBOARD_VIEW = 'dashboard';

	/** The Labs feature switch that turns the store pages on. */
	private const LABS_FEATURE = 'store_pages';

	/**
	 * True when Labs is here, new enough, and its Store pages feature is on.
	 *
	 * Checks the function as well as the version: a Labs build old enough to
	 * predate the store pages defines the constant and none of the functions,
	 * and the version compare alone would not catch a build with the constant
	 * removed. The feature switch matters too: with it off Labs registers no
	 * assets, so a screen drawn anyway would be a frame no stylesheet reaches.
	 */
	public static function available(): bool {
		return self::installed() && self::feature_on();
	}

	/** True when a Labs new enough to serve the store pages is active. */
	private static function installed(): bool {
		if ( ! function_exists( 'blueworx_store_dashboard_screen' ) || ! defined( 'BLUEWORX_LABS_VERSION' ) ) {
			return false;
		}
		return version_compare( (string) BLUEWORX_LABS_VERSION, self::MIN_LABS_VERSION, '>=' );
	}

	/**
	 * Whether Labs' Store pages feature is switched on. True when Labs has no
	 * feature switches to ask, so an older answer is never mistaken for "off".
	 */
	private static function feature_on(): bool {
		return ! function_exists( 'blueworx_feature_enabled' ) || (bool) blueworx_feature_enabled( self::LABS_FEATURE );
	}

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'blueworx_store_views', array( self::class, 'views' ) );
		add_filter( 'blueworx_store_panel', array( self::class, 'panel' ), 10, 3 );
		add_filter( 'blueworx_store_context', array( self::class, 'context' ) );
		add_filter( 'blueworx_store_checkout_links', array( self::class, 'checkout_links' ), 10, 2 );
		add_filter( 'blueworx_store_dashboard_url', array( self::class, 'dashboard_url' ) );
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_notice_assets' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * The five filters.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Adds the Bookings view when LatePoint can fill it.
	 *
	 * Detection is by the shortcode the view renders, not by has_latepoint():
	 * the question is "will this panel have anything in it?", and a LatePoint
	 * that registers its calendar but not its customer dashboard would
	 * otherwise offer a tab with nothing behind it.
	 *
	 * Placed straight after the dashboard so it reads first among the things
	 * a member does with the club, ahead of the shop's own views. Appended
	 * when no dashboard entry is there to sit after — Labs guarantees one, but
	 * another filter may have run before this and reordered things.
	 *
	 * @param mixed $views Labs' views, in nav order.
	 * @return array<int,array<string,mixed>>
	 */
	public static function views( $views ): array {
		$views = array_values( (array) $views );
		if ( ! Blueworx_Clubhouse_Integrations::provides( self::BOOKINGS_SHORTCODE ) ) {
			return $views;
		}
		$out    = array();
		$placed = false;
		foreach ( $views as $view ) {
			$out[] = $view;
			if ( ! $placed && is_array( $view ) && self::DASHBOARD_VIEW === (string) ( $view['key'] ?? '' ) ) {
				$out[]  = self::bookings_view();
				$placed = true;
			}
		}
		if ( ! $placed ) {
			$out[] = self::bookings_view();
		}
		return $out;
	}

	/**
	 * The Bookings entry, as the member area declared it before Labs took the
	 * frame over. No blocks: the shortcode takes the whole panel, because
	 * LatePoint brings its own tabs and does not belong boxed inside a card.
	 *
	 * @return array<string,mixed>
	 */
	private static function bookings_view(): array {
		return array(
			'key'       => 'bookings',
			'label'     => 'Bookings',
			'title'     => 'Bookings',
			'lede'      => 'What you have booked, and anything coming up.',
			'icon'      => 'calendar',
			'where'     => 'both',
			'blocks'    => array(),
			'shortcode' => self::BOOKINGS_SHORTCODE,
		);
	}

	/**
	 * The club's additions to two panels: its welcome above the overview, and
	 * its own profile questions under the shop's account block.
	 *
	 * The welcome pack's rules ride with it in a <style> tag, as they always
	 * have: a handful of rules on exactly one panel, and enqueueing them would
	 * put them on every dashboard request including the ones with no pack.
	 *
	 * The profile card copies the markup Labs' own card helper emits rather
	 * than calling it, so this filter stays pure and loads without Labs.
	 *
	 * @param mixed $html    The panel as Labs drew it.
	 * @param mixed $key     The view's key.
	 * @param mixed $context From blueworx_store_context(); unused here.
	 */
	public static function panel( $html, $key, $context = null ): string {
		$html = (string) $html;
		$key  = (string) $key;
		if ( self::DASHBOARD_VIEW === $key ) {
			$welcome = self::welcome_pack();
			if ( '' === $welcome ) {
				return $html;
			}
			$accent = Blueworx_Clubhouse_Welcome_Pack::accent_pair( new Blueworx_Clubhouse_Options_Storage() );
			return '<style>' . Blueworx_Clubhouse_Welcome_Pack::css( ...$accent ) . '</style>' . $welcome . $html;
		}
		if ( 'profile' === $key && class_exists( 'Blueworx_Clubhouse_Profile_Form' ) ) {
			$own = Blueworx_Clubhouse_Profile_Form::panel( 'profile' );
			return '' !== $own ? $html . self::card( $own ) : $html;
		}
		return $html;
	}

	/**
	 * Who the site is, as the club has set it up rather than as WordPress
	 * guesses: the crest, the club's own sign-in page, and the way back out
	 * to the club site instead of to WordPress's login form.
	 *
	 * @param mixed $context Labs' defaults.
	 * @return array<string,mixed>
	 */
	public static function context( $context ): array {
		$context = (array) $context;
		$club    = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';

		$context['site_name']  = $club;
		$context['logo_url']   = self::logo_url();
		$context['home_url']   = self::link_url( 'home' );
		$context['home_label'] = '' !== $club ? 'Back to ' . $club : 'Back to the club site';
		$context['login_url']  = self::link_url( 'login' );
		$context['logout_url'] = self::logout_url();
		return $context;
	}

	/**
	 * The club pages a buyer is entitled to read before paying, and their
	 * addresses. Replaces Labs' own list rather than adding to it: Labs offers
	 * a "Privacy policy" link from WordPress's privacy setting, and the club's
	 * privacy notice is the same page under its own name — a footer with both
	 * reads as two policies.
	 *
	 * Contact is here as well as the two legal pages: someone who has hit a
	 * problem halfway through paying needs a way to ask about it, and the
	 * checkout's header offers none.
	 *
	 * @param mixed $links   Labs' own links; discarded, see above.
	 * @param mixed $context From blueworx_store_context(); unused here.
	 * @return array<int,array{label:string,href:string}>
	 */
	public static function checkout_links( $links, $context = null ): array {
		if ( ! class_exists( 'Blueworx_Clubhouse_Frontend' ) ) {
			return array_values( (array) $links );
		}
		// Resolved once and closed over, rather than called from inside the
		// visibility callback: context() rebuilds options storage, the full
		// look registry and the demo lookup on every call, and the callback
		// runs once per candidate link.
		$visibility = Blueworx_Clubhouse_Frontend::context()->visibility;
		return self::footer_links(
			static fn ( string $slug ): bool => $visibility->is_page_visible( $slug ),
			static fn ( string $slug ): string => Blueworx_Clubhouse_Frontend::link_url( $slug )
		);
	}

	/**
	 * The four club pages, in footer order, kept to the ones the club shows
	 * and has an address for. Pure — the callers hand in the two questions
	 * this cannot answer itself.
	 *
	 * @param callable(string):bool   $visible
	 * @param callable(string):string $url
	 * @return array<int,array{label:string,href:string}>
	 */
	private static function footer_links( callable $visible, callable $url ): array {
		$out = array();
		foreach ( array(
			'terms'   => 'Terms and conditions',
			'privacy' => 'Privacy notice',
			'rules'   => 'Club rules',
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
	 * Claims the dashboard for the club's own member area, when the club is
	 * serving one. A club that has switched the member area off under Setup →
	 * Visibility keeps whatever Labs decided — SureCart's own dashboard page
	 * must go on rendering, not be redirected into a 404.
	 *
	 * @param mixed $url Whatever Labs, or an earlier filter, answered.
	 */
	public static function dashboard_url( $url ): string {
		$url = (string) $url;
		if ( ! class_exists( 'Blueworx_Clubhouse_Frontend' ) || ! class_exists( 'Blueworx_Clubhouse_Page_Map' ) ) {
			return $url;
		}
		// Same condition Page_Renderer::header_account()'s call site uses to
		// decide whether the header can offer the member area at all.
		$serving = Blueworx_Clubhouse_Page_Map::is_available( Blueworx_Clubhouse_Frontend::MEMBER_AREA )
			&& ( new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() ) )->is_page_visible( Blueworx_Clubhouse_Frontend::MEMBER_AREA );
		return self::claim( $serving, self::link_url( Blueworx_Clubhouse_Frontend::MEMBER_AREA ), $url );
	}

	/**
	 * The pure half of dashboard_url(): the member area's address when it is
	 * being served and has one, otherwise what was there before.
	 */
	public static function claim( bool $serving, string $member_url, string $otherwise ): string {
		$member_url = trim( $member_url );
		return $serving && '' !== $member_url ? $member_url : $otherwise;
	}

	/*
	 * ---------------------------------------------------------------------
	 * The Labs functions the rest of Clubhouse calls, with Labs-absent defaults.
	 * ---------------------------------------------------------------------
	 */

	/** The post id of one store page, or 0 when Labs is absent or has none. */
	public static function page_id( string $key ): int {
		return self::available() ? (int) blueworx_store_page_id( $key ) : 0;
	}

	/** One store page's address, or '' when Labs is absent or the page is missing. */
	public static function page_url( string $key ): string {
		return self::available() ? (string) blueworx_store_page_url( $key ) : '';
	}

	/** Which store page a post is, or '' for any other post — and for every post without Labs. */
	public static function page_key( int $post_id ): string {
		return self::available() ? (string) blueworx_store_page_key( $post_id ) : '';
	}

	/**
	 * The member area itself, drawn by Labs on the club's own route. Without
	 * Labs, a plain screen saying so — never a blank page where a member's
	 * account should be.
	 *
	 * @param string $base The member area's own address — every view link is built on it.
	 * @param string $home The club site's front page, for the way back out.
	 */
	public static function screen( string $base, string $home ): string {
		return self::available() ? (string) blueworx_store_dashboard_screen( $base, $home ) : self::unavailable( $home );
	}

	/**
	 * Labs' dashboard assets, then the club's profile-card rules on top. The
	 * rules depend on Labs' stylesheet so they land after it; without Labs
	 * the dependency is unmet and WordPress simply prints nothing.
	 */
	public static function enqueue_dashboard(): void {
		if ( self::available() ) {
			blueworx_store_enqueue_dashboard();
		}
		if ( ! function_exists( 'wp_enqueue_style' ) || ! defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ) {
			return;
		}
		wp_enqueue_style(
			self::STYLE_HANDLE,
			BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/member-profile.css',
			array( self::LABS_STYLE_HANDLE ),
			defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : null
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Telling the admin what is missing.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Prints the notice for someone who can act on it, on every admin screen
	 * until Labs is installed and new enough.
	 */
	public static function render_notice(): void {
		if ( self::available() || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$installed = defined( 'BLUEWORX_LABS_VERSION' );
		// Escaped by notice_html(); the version is the only variable in it.
		echo self::notice_html( $installed, $installed ? (string) BLUEWORX_LABS_VERSION : '', self::feature_on() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * The design system's stylesheet and icons, on whichever admin screen the
	 * notice is printed on. admin_notices runs on Plugins and the Dashboard
	 * as much as on this plugin's own screens, and there nothing else loads
	 * them — without this the notice is a plain box with an empty icon.
	 */
	public static function enqueue_notice_assets(): void {
		if ( self::available() || ! class_exists( 'Blueworx_Clubhouse_Admin_Assets' ) ) {
			return;
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue_as_a_guest();
	}

	/**
	 * The notice's markup. Pure: says which of the three things is wrong —
	 * Labs missing, Labs too old, or its Store pages feature switched off —
	 * and what would fix it.
	 *
	 * The outer core .notice is what admin_notices places at the top of the
	 * screen; the .bw-admin inside it opts the notice into the design system,
	 * so it is drawn as the same danger notice the plugin's own screens use.
	 *
	 * @param bool   $installed  Whether any Labs is active at all.
	 * @param string $version    The version it reports, '' when none.
	 * @param bool   $feature_on Whether Labs' Store pages feature is on; only read when Labs is new enough.
	 */
	public static function notice_html( bool $installed, string $version, bool $feature_on = true ): string {
		if ( ! $installed ) {
			$reason = 'It is not active.';
		} elseif ( version_compare( $version, self::MIN_LABS_VERSION, '<' ) ) {
			$reason = 'You have ' . self::e( $version ) . '.';
		} elseif ( ! $feature_on ) {
			$reason = 'Its Store pages feature is switched off — turn it on under BlueWorx → Enhancements.';
		} else {
			$reason = 'You have ' . self::e( $version ) . '.';
		}
		return '<div class="notice"><div class="bw-admin">'
			. '<div class="bw-notice bw-notice--danger" role="alert">'
			. '<i class="bw-icon bw-notice__icon" data-lucide="circle-alert"></i>'
			. '<div class="bw-notice__body">'
			. '<p class="bw-notice__title">Clubhouse needs the BlueWorx Labs plugin</p>'
			. '<p class="bw-notice__text">Version ' . self::e( self::MIN_LABS_VERSION ) . ' or newer serves the member area, '
			. 'checkout and thank-you pages. ' . $reason . '</p>'
			. '</div></div></div></div>';
	}

	/*
	 * ---------------------------------------------------------------------
	 * The pieces the filters are built from.
	 * ---------------------------------------------------------------------
	 */

	/** What a member sees on the member-area route when Labs is not there to draw it. */
	private static function unavailable( string $home ): string {
		return '<div class="bw-admin bw-page"><h1>Member area</h1>'
			. '<p>The member area is not available right now. Please try again later.</p>'
			. '<p><a href="' . self::e( $home ) . '">Back to the club site</a></p></div>';
	}

	/** One card, in the markup Labs' own card helper emits for an untitled card. */
	private static function card( string $inner ): string {
		return '<section class="bw-card"><div class="bw-card__body">' . $inner . '</div></section>';
	}

	/** The club's welcome pack, or '' when nobody has written one or it is switched off. */
	private static function welcome_pack(): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Welcome_Pack' ) || ! class_exists( 'Blueworx_Clubhouse_Page_Content' ) ) {
			return '';
		}
		$store = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Options_Storage() );
		// The same Shown switch the Global content editor writes, read the same
		// way Welcome_Pack::add_to_dashboard() reads it — one switch, one answer.
		if ( ! $store->is_section_shown( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION ) ) {
			return '';
		}
		$field = static fn ( string $name ): string => (string) $store->get(
			Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE,
			Blueworx_Clubhouse_Welcome_Pack::SECTION,
			$name,
			''
		);
		return Blueworx_Clubhouse_Welcome_Pack::render(
			array(
				'heading'    => $field( 'heading' ),
				'body'       => $field( 'body' ),
				'link_label' => $field( 'link_label' ),
				'link_href'  => $field( 'link_href' ),
			)
		);
	}

	/**
	 * The mark for the sidebar's brand block and the checkout header, or ''
	 * when the club has set neither.
	 */
	private static function logo_url(): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Frontend' ) || ! class_exists( 'Blueworx_Clubhouse_Branding' ) ) {
			return '';
		}
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		return self::crest(
			Blueworx_Clubhouse_Frontend::resolve_logo( $branding->get_favicon() ),
			Blueworx_Clubhouse_Frontend::resolve_logo( $branding->get_logo() )
		);
	}

	/**
	 * The pure half of logo_url(). The favicon wins: it is the square,
	 * small-size mark, which is exactly what the 44px corner box wants — a wide
	 * logo shrinks to nothing in it. Falls back to the logo, then to nothing,
	 * and Labs draws the club's initials instead.
	 */
	public static function crest( string $favicon, string $logo ): string {
		return '' !== $favicon ? $favicon : $logo;
	}

	/** A club page's address, or '' before the link resolver can answer. */
	private static function link_url( string $key ): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Frontend' ) || ! function_exists( 'home_url' ) ) {
			return '';
		}
		return (string) Blueworx_Clubhouse_Frontend::link_url( $key );
	}

	/**
	 * The signed sign-out address, or '' when nothing can build one. The same
	 * link the club site's header offers — one logout journey, already nonced
	 * and already returning wherever the club chose.
	 */
	private static function logout_url(): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Auth' ) || ! function_exists( 'wp_nonce_url' ) ) {
			return '';
		}
		return (string) Blueworx_Clubhouse_Auth::logout_url();
	}

	/** Escape for HTML text and attributes; the same rule everywhere, with or without WordPress. */
	private static function e( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
