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
 * Served as a Clubhouse page at /member-dashboard/, the same way About and
 * Membership are, rather than by rewriting the page the shop seeds. So there is
 * no other plugin's template to be surprised by, no re-entrancy to guard, and
 * no page whose own content has to be judged and thrown away.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Member_Dashboard {

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		Blueworx_Clubhouse_Plugin_Slot::install_wordpress();
		Blueworx_Clubhouse_Dashboard_Assets::register();
		// Priority 5: before Frontend's own 404 pass at the default 10, so a
		// signed-out visitor is moved on rather than shown a page they cannot use.
		add_action( 'template_redirect', array( self::class, 'route' ), 5 );
	}

	/**
	 * Where this request should be sent instead, or '' to stay put.
	 *
	 * Two journeys meet here. A member who followed the shop's own account link,
	 * or an old bookmark, lands on the page SureCart seeded and is carried
	 * across to ours with the panel they asked for intact. A signed-out visitor
	 * who reaches the member area is sent to the club's own login page, rather
	 * than a frame with nothing in it and no way to sign in.
	 *
	 * Pure, so both journeys are testable without a WordPress runtime.
	 *
	 * @param int    $queried_id          The post this request resolved to, 0 for none.
	 * @param int    $dashboard_id        The page id the shop recorded, 0 when it has none.
	 * @param bool   $on_member_area      Whether this request is our own member-area route.
	 * @param bool   $member_area_serving Whether this site is actually serving the member
	 *                                    area — Page_Map thinks it available, and the club
	 *                                    has not switched it off under Setup → Visibility.
	 *                                    A club that has switched it off must keep SureCart's
	 *                                    own dashboard page rendering, not be 302'd into a 404.
	 * @param bool   $signed_in           Whether anyone is signed in.
	 * @param string $view                The panel named in the address, '' for the overview.
	 * @param string $member_url          The member area's address, '' when it cannot be built.
	 * @param string $login_url           The club's login page, '' when it cannot be built.
	 * @param string $model               The shop record an action address names, '' for none.
	 * @param string $action              What it asks be done with it, '' for none.
	 * @param string $id                  Which record, '' when the action needs none.
	 */
	public static function redirect_to(
		int $queried_id,
		int $dashboard_id,
		bool $on_member_area,
		bool $member_area_serving,
		bool $signed_in,
		string $view,
		string $member_url,
		string $login_url,
		string $model = '',
		string $action = '',
		string $id = ''
	): string {
		if ( $on_member_area ) {
			return $signed_in ? '' : $login_url;
		}
		if ( ! $member_area_serving || $dashboard_id <= 0 || $queried_id !== $dashboard_id || '' === $member_url ) {
			return '';
		}
		$target = '' === $view ? $member_url : Blueworx_Clubhouse_Dashboard_Shell::view_url( $view, $member_url );

		// A bookmark of the shop's own account page can name an action as well
		// as a panel — "edit this customer". Dropping those two args on the way
		// across would land the member on a screen that reads their details back
		// at them instead of the form they saved last time.
		if ( '' === trim( $model ) || '' === trim( $action ) ) {
			return $target;
		}
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' )
			. Blueworx_Clubhouse_Dashboard_Actions::MODEL_ARG . '=' . rawurlencode( trim( $model ) )
			. '&' . Blueworx_Clubhouse_Dashboard_Actions::ACTION_ARG . '=' . rawurlencode( trim( $action ) );
		// Most actions name the record they act on; a few (adding a card) do not.
		return '' === trim( $id ) ? $target : $target . '&id=' . rawurlencode( trim( $id ) );
	}

	/**
	 * Act on that decision.
	 *
	 * template_redirect, so the answer is settled before WordPress picks a
	 * template and long before anything has been sent to the browser.
	 */
	public static function route(): void {
		if ( ! function_exists( 'wp_safe_redirect' ) || ! function_exists( 'get_queried_object_id' ) ) {
			return;
		}
		$asked  = Blueworx_Clubhouse_Dashboard_Actions::requested();
		$target = self::redirect_to(
			(int) get_queried_object_id(),
			Blueworx_Clubhouse_Shop_Pages::page_id( 'dashboard' ),
			Blueworx_Clubhouse_Frontend::MEMBER_AREA === Blueworx_Clubhouse_Frontend::current_page_slug(),
			// Same condition Page_Renderer::header_account()'s call site uses to
			// decide whether the header can offer the member area at all.
			Blueworx_Clubhouse_Page_Map::is_available( Blueworx_Clubhouse_Frontend::MEMBER_AREA )
				&& ( new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() ) )->is_page_visible( Blueworx_Clubhouse_Frontend::MEMBER_AREA ),
			function_exists( 'is_user_logged_in' ) && is_user_logged_in(),
			self::requested_view(),
			// link_url() rather than Links::url(): the link resolver is not
			// installed until rendering starts, which is after this runs.
			Blueworx_Clubhouse_Frontend::link_url( Blueworx_Clubhouse_Frontend::MEMBER_AREA ),
			Blueworx_Clubhouse_Frontend::link_url( 'login' ),
			$asked['model'],
			$asked['action'],
			self::requested_record()
		);
		if ( '' === $target ) {
			return;
		}
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * The member area itself: the nav, the panel that was asked for, and the
	 * club's welcome above the overview.
	 *
	 * Takes its two addresses as arguments rather than reaching for them, so
	 * the screen can be rendered from a Clubhouse route, from the preview, and
	 * from a unit test without a WordPress runtime under it.
	 *
	 * @param string $base The member area's own address — every view link is built on it.
	 * @param string $home The club site's front page, for the way back out.
	 * @return string '' when no view can be drawn at all, which callers treat as "render nothing".
	 */
	public static function screen( string $base, string $home ): string {
		$views   = Blueworx_Clubhouse_Dashboard_Views::available(
			Blueworx_Clubhouse_SureCart_Products::is_active(),
			Blueworx_Clubhouse_Integrations::has_latepoint()
		);
		$current = Blueworx_Clubhouse_Dashboard_Views::resolve( self::requested_view(), $views );
		if ( array() === $views ) {
			return '';
		}

		// Every panel is drawn, not just the one being read — see
		// Dashboard_Shell::page() for why. The welcome pack belongs to the
		// overview only.
		$welcome = self::welcome_pack();
		$panels  = array();
		foreach ( $views as $view ) {
			$key            = (string) $view['key'];
			$panels[ $key ] = Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $key
				? self::overview( $welcome, $views, $home, $base )
				: self::view_body( $view, '', $home, array( 'Blueworx_Clubhouse_Profile_Form', 'panel' ) );
		}

		// An address asking for something to be done — update these details, add
		// this card, cancel this plan — takes over the panel it belongs to. The
		// screen it replaces is the read-only one the member pressed the link on,
		// so what they came from is what they go back to.
		$action = self::action_panel();
		if ( null !== $action && isset( $panels[ $action['view'] ] ) ) {
			$panels[ $action['view'] ] = $action['body'];
			$current                   = $action['view'];
		}

		$style = '' !== $welcome
			? '<style>' . Blueworx_Clubhouse_Welcome_Pack::css( ...self::accent() ) . '</style>'
			: '';

		return $style . Blueworx_Clubhouse_Dashboard_Shell::page( array(
			'views'        => $views,
			'current'      => $current,
			'panels'       => $panels,
			'home_url'     => $home,
			'club_name'    => self::club_name(),
			'logo_url'     => self::logo_url(),
			'base'         => $base,
			'logout_url'   => self::logout_url(),
			'member_name'  => self::member_name(),
			'member_email' => self::member_email(),
		) );
	}

	/**
	 * The mark for the sidebar's brand block, or '' when the club has set
	 * neither. The favicon wins: it is the square, small-size mark, which is
	 * exactly what the 44px corner box wants — a wide logo shrinks to nothing
	 * in it. Falls back to the logo, then to the club's initials in the shell.
	 *
	 * Public, beside club_name(): Commerce_Pages draws the same crest into the
	 * checkout header, and needs the same answer this class already computes.
	 */
	public static function logo_url(): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Frontend' ) || ! class_exists( 'Blueworx_Clubhouse_Options_Storage' ) ) {
			return '';
		}
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		$favicon  = Blueworx_Clubhouse_Frontend::resolve_logo( $branding->get_favicon() );
		return '' !== $favicon ? $favicon : Blueworx_Clubhouse_Frontend::resolve_logo( $branding->get_logo() );
	}

	/** The signed-in member's name, or '' off WordPress. */
	private static function member_name(): string {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return '';
		}
		$user = wp_get_current_user();
		if ( ! is_object( $user ) ) {
			return '';
		}
		$name = trim( (string) ( $user->display_name ?? '' ) );
		return '' !== $name ? $name : trim( (string) ( $user->user_login ?? '' ) );
	}

	/** The address they signed in with, which tells them which account this is. */
	private static function member_email(): string {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return '';
		}
		$user = wp_get_current_user();
		return is_object( $user ) ? trim( (string) ( $user->user_email ?? '' ) ) : '';
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

	/**
	 * The screen an action address asks for, and which panel it belongs under.
	 *
	 * Rendered by SureCart's own wrapper block, which is the piece that reads
	 * `model` and `action` and hands the request to the right controller. So the
	 * form, the save and the permission check are all SureCart's — the member
	 * area supplies the frame around them and nothing else. That is the same
	 * division as every read-only panel here.
	 *
	 * Null for an ordinary address, and for an action whose model has no panel
	 * of ours (downloads, licences) or whose block will not render — a member
	 * following a stale link sees their normal member area, not a blank frame.
	 *
	 * @return array{view:string,body:string}|null
	 */
	private static function action_panel(): ?array {
		$asked = Blueworx_Clubhouse_Dashboard_Actions::requested();
		if ( ! Blueworx_Clubhouse_Dashboard_Actions::is_action(
			$asked['model'],
			$asked['action'],
			Blueworx_Clubhouse_Dashboard_Actions::site_check()
		) ) {
			return null;
		}

		$view = Blueworx_Clubhouse_Dashboard_Actions::view_for( $asked['model'] );
		if ( '' === $view ) {
			return null;
		}

		$body = Blueworx_Clubhouse_Plugin_Slot::block( Blueworx_Clubhouse_Dashboard_Actions::BLOCK );
		if ( '' === $body ) {
			return null;
		}
		return array(
			'view' => $view,
			'body' => Blueworx_Clubhouse_Dashboard_Shell::card( '', $body ),
		);
	}

	/** Which record an action address names, '' for none. */
	private static function requested_record(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- carried across a redirect untouched; the shop's own controller checks who may act on it.
		$raw = $_GET['id'] ?? '';
		return is_string( $raw ) ? $raw : '';
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
	 * tabs and does not belong inside a card of ours. Blocks each get a card,
	 * then a view's own panel, if it declares one, gets another beneath them.
	 * A panel whose plugin says nothing shows the honest empty state rather
	 * than an empty card.
	 *
	 * $panel_renderer is injected rather than named here so this class stays
	 * testable without WordPress, like everything else on it.
	 *
	 * @param array<string,mixed>          $view
	 * @param callable(string):string|null $panel_renderer
	 */
	public static function view_body( array $view, string $welcome, string $home_url, ?callable $panel_renderer = null ): string {
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
		$own = (string) ( $view['panel'] ?? '' );
		if ( '' !== $own && null !== $panel_renderer ) {
			$drawn = (string) $panel_renderer( $own );
			if ( '' !== $drawn ) {
				$out .= Blueworx_Clubhouse_Dashboard_Shell::card( '', $drawn );
			}
		}

		if ( '' === $out ) {
			return self::not_set_up( $home_url );
		}
		// $welcome is always '' here in production — screen() only ever passes
		// the pack into overview(). Kept as a parameter so the brief's direct
		// calls to view_body() can still exercise it; not a live path.
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
	 *
	 * Public and called from the asset pass rather than from the render, so the
	 * shop's stylesheet reaches the head. Called during rendering it arrived in
	 * the footer, and a member watched their account page snap into shape after
	 * it had loaded.
	 */
	public static function enqueue_shop_assets(): void {
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
