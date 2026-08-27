<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles a full HTML document for a Base Look + branding: <head> carries the
 * self-hosted @font-face rules (injected inline), the base stylesheet link, the
 * look stylesheet, and the derived :root variables; <body> is a string of
 * rendered sections. home()
 * composes the demo Home shell, honouring per-section visibility. The same
 * output is what WordPress template_include will later echo — the preview is
 * just an earlier caller.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Renderer {

	/**
	 * Structural rules shared by every look, loaded before the look's own
	 * stylesheet. Deliberately not a Base_Look method: a look substituting its
	 * own base is the drift this file prevents. Lives on the pure render layer
	 * (not Frontend) because Frontend::enqueue_specs() consumes it — the pure
	 * layer must not depend on the WordPress-coupled class that depends on it.
	 */
	public const BASE_STYLESHEET = 'assets/looks/base.css';

	public static function font_face_css( Blueworx_Clubhouse_Base_Look $look, string $base_url ): string {
		// Normalise to exactly one trailing slash so callers may pass the base with or
		// without it; an empty base stays empty (relative paths). Guards a future caller
		// against the '…pluginassets/fonts/…' footgun.
		$base = '' === $base_url ? '' : rtrim( $base_url, '/' ) . '/';
		$css  = '';
		foreach ( $look->fonts() as $font ) {
			$stem    = $font['stem'];
			$display = $font['display'];
			foreach ( $font['weights'] as $weight ) {
				$css .= "@font-face{font-family:'" . $font['family'] . "';"
					. 'font-style:normal;'
					. 'font-weight:' . (int) $weight . ';'
					. 'font-display:' . $display . ';'
					. 'src:url(' . $base . 'assets/fonts/' . $stem . '-' . $weight . '.woff2) format(\'woff2\')}';
			}
		}
		return $css;
	}

	public static function document(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding,
		string $body,
		string $plugin_url = ''
	): string {
		$vars     = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
		$css      = Blueworx_Clubhouse_Theme_Css::to_css( $vars );
		$faces    = self::font_face_css( $look, $plugin_url );
		$base     = htmlspecialchars( $plugin_url . self::BASE_STYLESHEET, ENT_QUOTES, 'UTF-8' );
		$sheet    = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );

		return '<!doctype html><html lang="en-GB"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<style>' . $faces . '</style>'
			. '<link rel="stylesheet" href="' . $base . '">'
			. '<link rel="stylesheet" href="' . $sheet . '">'
			. '<style>' . $css . '</style>'
			. '</head><body>' . $body . self::inline_script( 'reveal.js' ) . self::inline_script( 'cookie-notice.js' )
			. self::inline_script( 'share.js' ) . '</body></html>';
	}

	/**
	 * Progressive-enhancement scroll reveal: adds .ch-reveal to each top-level block
	 * (skipping the hero, which has its own CSS load-in), then .is-in as it enters the
	 * viewport. Bails out with content fully visible when IntersectionObserver is absent
	 * or the user prefers reduced motion, so nothing is ever hidden without JS. Vanilla
	 * JS by design — no dependency; GSAP stays reserved for genuinely complex animation.
	 *
	 * Also carries the cookie notice's script, which reveals it and remembers its
	 * dismissal. WordPress enqueues both properly (Frontend::enqueue_assets); this
	 * is the preview's equivalent, so the preview shows the same page a visitor
	 * would see rather than one with a permanent bar across the bottom.
	 */
	private static function inline_script( string $file ): string {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/' . $file );
		return '<script>' . $js . '</script>';
	}

	/**
	 * A count written the way the copy writes it — "nine", "twenty-four".
	 *
	 * The default copy used to hardcode those words, so a club with six sections
	 * still read "Nine sports" in its own headline, footer and About page. The
	 * numbers now come from the collections that fill the page, so the claim and
	 * the list below it cannot disagree.
	 *
	 * Words up to ninety-nine, digits above: no club has a hundred sections, and
	 * a fallback that degrades to a numeral is better than one that runs out.
	 */
	public static function number_word( int $n ): string {
		$units = array( 'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
			'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen',
			'eighteen', 'nineteen' );
		$tens  = array( 2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety' );

		if ( $n < 0 || $n > 99 ) {
			return (string) $n;
		}
		if ( $n < 20 ) {
			return $units[ $n ];
		}
		$ten = $tens[ intdiv( $n, 10 ) ];
		$rem = $n % 10;
		return 0 === $rem ? $ten : $ten . '-' . $units[ $rem ];
	}

	/** number_word() with its first letter capitalised, for the start of a sentence. */
	public static function number_word_upper( int $n ): string {
		return ucfirst( self::number_word( $n ) );
	}

	/** Read a single content field, falling back to the hardcoded default when unset or no store. */
	private static function cget( ?Blueworx_Clubhouse_Content_Store $c, string $page, string $sec, string $field, mixed $default ): mixed {
		if ( null === $c ) {
			return $default;
		}
		$v = $c->get( $page, $sec, $field, null );
		return ( null === $v || '' === $v ) ? $default : $v;
	}

	/** Read a loop's stored items, falling back to the hardcoded default array when none saved. */
	private static function citems( ?Blueworx_Clubhouse_Content_Store $c, string $page, string $sec, array $default ): array {
		if ( null === $c ) {
			return $default;
		}
		$items = $c->get_items( $page, $sec );
		return array() === $items ? $default : $items;
	}

	/**
	 * Resolve a stored image field to a URL. Stored values are attachment IDs
	 * (Task 6 saves `absint`); '' (no override) and any non-digit string (a raw
	 * URL, as every render/preview test passes) come back unchanged.
	 */
	private static function media_src( string $val ): string {
		if ( ctype_digit( $val ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( (int) $val, 'large' );
			return is_string( $url ) ? $url : $val;
		}
		return $val;
	}

	/**
	 * Split a textarea's "one item per line" convention (how the catalogue stores
	 * lists like tier features or info-strip lines) into a trimmed, non-empty
	 * array. A value that is already an array (today's hardcoded defaults) passes
	 * through unchanged.
	 */
	private static function lines( mixed $val ): array {
		if ( is_array( $val ) ) {
			return $val;
		}
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $val ) ), static fn( string $l ): bool => '' !== $l ) );
	}

	/** Lowercase, hyphenated slug — the one place a label becomes a filter slug.
	 * Public because Link_Catalogue builds filter targets from the same labels
	 * the pill rows do; two implementations would drift. */
	public static function slugify( string $s ): string {
		$s = strtolower( trim( $s ) );
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $s ), '-' );
	}

	/**
	 * Distinct, non-empty picked values in first-seen order — the labels a page's
	 * filter pills are built from, so they never drift from the content.
	 *
	 * @param array<int,array<string,mixed>>          $rows
	 * @param callable(array<string,mixed>):string    $pick
	 * @return array<int,string>
	 */
	private static function distinct( array $rows, callable $pick ): array {
		$out = array();
		foreach ( $rows as $r ) {
			$v = trim( $pick( $r ) );
			if ( '' !== $v && ! in_array( $v, $out, true ) ) {
				$out[] = $v;
			}
		}
		return $out;
	}

	/**
	 * Keep the rows whose picked value slugifies to $current. An empty $current
	 * (the "All" pill) keeps everything, as does a slug no row matches.
	 *
	 * @param array<int,array<string,mixed>>       $rows
	 * @param callable(array<string,mixed>):string $pick
	 * @return array<int,array<string,mixed>>
	 */
	private static function filter_rows( array $rows, string $current, callable $pick ): array {
		if ( '' === $current ) {
			return $rows;
		}
		return array_values( array_filter( $rows, static fn( array $r ): bool => self::slugify( $pick( $r ) ) === $current ) );
	}

	/**
	 * Normalise the incoming filter slug: keep it only when it matches one of the
	 * page's own labels, otherwise fall back to "All" (''). Guards against stale or
	 * hand-typed filter params showing an empty page.
	 *
	 * @param array<int,string> $labels
	 */
	private static function valid_filter( string $filter, array $labels ): string {
		if ( '' === $filter ) {
			return '';
		}
		foreach ( $labels as $label ) {
			if ( self::slugify( $label ) === $filter ) {
				return $filter;
			}
		}
		return '';
	}

	/**
	 * The hero_filter pill row: "All" plus one pill per label, each linking to the
	 * page with its filter slug and the matching one marked active (default "All").
	 *
	 * @param array<int,string> $labels
	 * @return array<int,array{label:string,href:string,active:bool}>
	 */
	private static function filter_pills( string $page_key, array $labels, string $current ): array {
		$pills = array(
			array( 'label' => 'All', 'href' => Blueworx_Clubhouse_Links::url( $page_key ), 'active' => '' === $current ),
		);
		foreach ( $labels as $label ) {
			$slug    = self::slugify( $label );
			$pills[] = array(
				'label'  => $label,
				'href'   => Blueworx_Clubhouse_Links::filtered_url( $page_key, $slug ),
				'active' => $slug === $current,
			);
		}
		return $pills;
	}

	/**
	 * The membership tiers — the single source both the Membership page and the
	 * Home teaser render, so an owner's edits under Content → Membership → Tiers
	 * reach both. Returns tier_grid()-shaped rows (Home overrides the CTA to
	 * funnel to the Membership page; Membership keeps the source's own CTA).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function membership_tiers( ?Blueworx_Clubhouse_Content_Store $content ): array {
		$default = array(
			array( 'eyebrow' => 'Under 18', 'name' => 'Junior', 'price' => '£12', 'period' => '/mo',
				'features' => array( 'Any junior section', 'Coaching included', 'Holiday camp discounts' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
				'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
				'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
				'recommended' => true, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			array( 'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
				'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
		);
		$items = self::citems( $content, 'membership', 'tiers', $default );
		return array_map(
			static function ( array $t ): array {
				$tier = array(
					'eyebrow'     => (string) ( $t['eyebrow'] ?? '' ),
					'name'        => (string) ( $t['name'] ?? '' ),
					'price'       => (string) ( $t['price'] ?? '' ),
					'period'      => (string) ( $t['period'] ?? '' ),
					'features'    => self::lines( $t['features'] ?? array() ),
					'recommended' => (bool) ( $t['featured'] ?? ( $t['recommended'] ?? false ) ),
					'cta_label'   => (string) ( $t['cta_label'] ?? '' ),
					'cta_href'    => (string) ( $t['cta_href'] ?? Blueworx_Clubhouse_Links::url( 'contact' ) ),
				);

				$monthly = self::tier_cadence( $t, 'price', 'price_id', (string) ( $t['period'] ?? '/mo' ) );
				$annual  = self::tier_cadence( $t, 'price_annual', 'price_id_annual', '/yr' );

				// A tier priced in only one cadence keeps showing the price it has
				// on the other view, labelled by the renderer — a card that empties
				// out as somebody toggles reads as a broken page.
				if ( ! $annual['available'] ) {
					$annual = array_merge( $monthly, array( 'available' => false ) );
				}
				if ( ! $monthly['available'] ) {
					$monthly = array_merge( $annual, array( 'available' => false ) );
				}

				$tier['monthly'] = $monthly;
				$tier['annual']  = $annual;
				$tier['saving']  = ( $monthly['available'] && $annual['available'] )
					? self::annual_saving( $monthly['price'], $annual['price'], $monthly['period'], $annual['period'] )
					: '';

				// The flat fields stay the monthly ones, so everything that read a
				// tier before this existed — tiers_sell(), the page copy, the tests
				// — is unchanged.
				$tier['price']     = $monthly['price'];
				$tier['period']    = $monthly['period'];
				$tier['cta_label'] = $monthly['cta_label'];
				$tier['cta_href']  = $monthly['cta_href'];
				return $tier;
			},
			$items
		);
	}

	/**
	 * One cadence of one tier: what it costs, and what its button does.
	 *
	 * All-or-nothing, exactly as the single-price version was. A tier connected
	 * to a real price shows what that price charges and sells it; anything
	 * missing — no shop, no such price, no checkout page — leaves the cadence
	 * exactly as the club typed it, which is how every site behaved before any
	 * of this existed. Showing the shop's price beside a contact link would
	 * advertise a price the visitor cannot pay.
	 *
	 * `available` is the difference between "this is what it costs" and "this is
	 * the other cadence's price, shown because a card must not empty out".
	 *
	 * @param array<string,mixed> $tier
	 * @return array{price:string,period:string,cta_label:string,cta_href:string,available:bool}
	 */
	private static function tier_cadence( array $tier, string $price_field, string $price_id_field, string $fallback_period ): array {
		$typed     = trim( (string) ( $tier[ $price_field ] ?? '' ) );
		$cta_label = (string) ( $tier['cta_label'] ?? '' );
		$cta_href  = (string) ( $tier['cta_href'] ?? Blueworx_Clubhouse_Links::url( 'contact' ) );

		$price_id = (string) ( $tier[ $price_id_field ] ?? '' );
		if ( '' !== $price_id ) {
			$price    = Blueworx_Clubhouse_Products_Source::get()?->price( $price_id );
			$checkout = Blueworx_Clubhouse_Checkout::url( $price_id );
			if ( null !== $price && '' !== $checkout ) {
				return array(
					'price'     => $price['amount'],
					'period'    => $price['period'],
					'cta_label' => $cta_label,
					'cta_href'  => $checkout,
					'available' => true,
				);
			}
		}

		return array(
			'price'     => $typed,
			'period'    => '' !== $typed ? $fallback_period : '',
			'cta_label' => $cta_label,
			'cta_href'  => $cta_href,
			'available' => '' !== $typed,
		);
	}

	/**
	 * "Save £56 a year", or '' when it cannot be said safely.
	 *
	 * Deliberately narrow: both prices must be an optional currency symbol and a
	 * number, nothing else, in the same currency. "from £28" and "£28 per adult"
	 * produce no badge, because a saving that contradicts the price printed
	 * beside it is worse than no saving at all.
	 *
	 * The two periods are the same test applied to what is being compared. A
	 * club is free to connect any price to either slot, and plenty do — a yearly
	 * price in the monthly slot, or the same monthly price on both sides. This
	 * multiplies the left-hand price by twelve, so unless it really is a month
	 * and the right-hand one really is a year, the answer is a year of savings
	 * nobody is making.
	 *
	 * @param string $monthly_period '/mo' on a genuinely monthly price. Anything
	 *                               else — '/yr', or '' where the shop could not
	 *                               name the interval — means no badge.
	 * @param string $annual_period  '/yr' on a genuinely yearly price.
	 */
	public static function annual_saving( string $monthly, string $annual, string $monthly_period = '/mo', string $annual_period = '/yr' ): string {
		if ( '/mo' !== $monthly_period || '/yr' !== $annual_period ) {
			return '';
		}
		$parse = static function ( string $raw ): ?array {
			$raw = trim( $raw );
			if ( 1 !== preg_match( '/^([^\d\s.]{0,3})\s*(\d+(?:\.\d{1,2})?)$/u', $raw, $m ) ) {
				return null;
			}
			return array( 'symbol' => $m[1], 'amount' => (float) $m[2] );
		};

		$m = $parse( $monthly );
		$a = $parse( $annual );
		if ( null === $m || null === $a || $m['symbol'] !== $a['symbol'] ) {
			return '';
		}

		$saving = ( $m['amount'] * 12 ) - $a['amount'];
		if ( $saving <= 0 ) {
			return '';
		}
		$number = ( 0.0 === fmod( $saving, 1.0 ) ) ? (string) (int) $saving : number_format( $saving, 2 );
		return 'Save ' . $m['symbol'] . $number . ' a year';
	}

	/**
	 * Whether this page can actually take money: at least one tier whose button
	 * goes to the shop's checkout rather than the contact form. Pure.
	 *
	 * The Membership page used to promise "Join in five minutes" above steps
	 * that said register your interest, no payment yet, and we will be in touch
	 * within a few days (issue #90). Both halves were once true of some club, so
	 * the fix is not to pick one and hard-code it: it is for the page to say
	 * whichever is true here. Every line it decides is still only a default, so
	 * a club that has written its own copy keeps it.
	 *
	 * Read off the finished tiers rather than recomputed, so it cannot disagree
	 * with the buttons the visitor is looking at.
	 *
	 * @param array<int,array<string,mixed>> $tiers    From membership_tiers().
	 * @param string                         $checkout The checkout base URL, '' when there is none.
	 */
	public static function tiers_sell( array $tiers, string $checkout ): bool {
		if ( '' === $checkout ) {
			return false;
		}
		foreach ( $tiers as $tier ) {
			if ( str_starts_with( (string) ( $tier['cta_href'] ?? '' ), $checkout ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * membership_tiers() for tests. The tier list is the one piece of this
	 * renderer with logic worth testing on its own — everything else is markup
	 * assembly covered by the page tests.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function membership_tiers_for_test( ?Blueworx_Clubhouse_Content_Store $content ): array {
		return self::membership_tiers( $content );
	}

	/**
	 * The site header for a page this plugin does not render itself — a page
	 * another plugin owns, dressed in the Clubhouse chrome by External_Chrome.
	 * Delegates to the same shell every clubhouse page uses, so the nav cannot
	 * drift between the two. No nav item is marked active: the current page is
	 * not one of ours.
	 */
	public static function chrome_header( string $club, Blueworx_Clubhouse_Visibility $visibility, Blueworx_Clubhouse_Collections $collections, string $logo_url = '', ?Blueworx_Clubhouse_Content_Store $content = null ): string {
		return self::shell_header( $club, '', $visibility, $collections, $logo_url, $content );
	}

	/** The site footer for a page this plugin does not render itself. See chrome_header(). */
	public static function chrome_footer( string $club, Blueworx_Clubhouse_Visibility $visibility, Blueworx_Clubhouse_Branding $branding, ?Blueworx_Clubhouse_Content_Store $content = null ): string {
		return self::shell_footer( $club, $visibility, $branding, $content );
	}

	/**
	 * The header's account button: label and address.
	 *
	 * One button carries the whole account journey — the way in when nobody is
	 * signed in, and the way back to the member area when somebody is. Signing
	 * out lives inside the member area, which is where a member goes to manage
	 * everything else about their membership.
	 *
	 * A club that has switched the member area off keeps the old way out here,
	 * so a signed-in member is never left without one.
	 *
	 * @return array{0:string,1:string} label, href
	 */
	public static function header_account( bool $signed_in, bool $member_area_on, string $logout_url ): array {
		if ( ! $signed_in ) {
			return array( 'Log in', Blueworx_Clubhouse_Links::url( 'login' ) );
		}
		if ( $member_area_on ) {
			return array( 'Member area', Blueworx_Clubhouse_Links::url( 'member-dashboard' ) );
		}
		return array( 'Log out', $logout_url );
	}

	private static function shell_header(
		string $club,
		string $active,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		// The announcement bar is owner-configurable (Content → Global → Header):
		// a show/hide toggle plus editable text + link. When off — or when the text
		// is cleared — Sections::header()'s empty-string guard drops the markup.
		$banner_on   = (bool) self::cget( $content, 'global', 'header', 'banner_show', true );
		$banner_text = $banner_on
			? self::cget( $content, 'global', 'header', 'banner', 'Summer sign-ups are open — register your interest for 2026/27 →' )
			: '';
		// The way in and the way back are the same button: "Log in" to a visitor,
		// "Member area" to a member. Off WordPress the state seam is unset, so the
		// preview keeps showing "Log in".
		$auth        = Blueworx_Clubhouse_Auth_View::state();
		$signed_in   = '' !== $auth['logged_in'] && '' !== $auth['logout_url'];
		list( $account_label, $account_href ) = self::header_account(
			$signed_in,
			Blueworx_Clubhouse_Page_Map::is_available( 'member-dashboard' )
				&& $visibility->is_page_visible( 'member-dashboard' ),
			$auth['logout_url']
		);
		return Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => $club,
			'banner'      => $banner_text,
			'banner_href' => self::cget( $content, 'global', 'header', 'banner_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			'nav'         => Blueworx_Clubhouse_Menu::current()->items( $collections, $visibility ),
			'active'      => $active,
			'login'       => $account_label,
			'login_href'  => $account_href,
			'join'        => self::cget( $content, 'global', 'header', 'join', Blueworx_Clubhouse_Cta::JOIN ),
			'join_href'   => self::cget( $content, 'global', 'header', 'join_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			'logo'        => $logo_url,
		) );
	}

	private static function shell_footer( string $club, Blueworx_Clubhouse_Visibility $visibility, Blueworx_Clubhouse_Branding $branding, ?Blueworx_Clubhouse_Content_Store $content = null ): string {
		return Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => $club,
			'tagline'    => self::cget( $content, 'global', 'footer', 'tagline', 'One club, every sport. A home ground for every team, and everyone who follows them.' ),
			'socials'    => array(
				'Facebook'  => $branding->get_facebook_url(),
				'Instagram' => $branding->get_instagram_url(),
				'LinkedIn'  => $branding->get_linkedin_url(),
				'X'         => $branding->get_x_url(),
			),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => self::nav_links( array(
					array( 'label' => 'About', 'key' => 'about' ),
					array( 'label' => 'Sports', 'key' => 'sports' ),
					array( 'label' => 'Teams', 'key' => 'teams' ),
					array( 'label' => 'Events', 'key' => 'events' ),
					// News shipped with a page of its own but nothing linking to it, so
					// it could only be reached by typing the address. nav_links() drops
					// it again for a club that has switched news off.
					array( 'label' => 'News', 'key' => 'news' ),
				), $visibility ) ),
				array( 'title' => 'Get involved', 'links' => self::nav_links( array(
					array( 'label' => 'Membership', 'key' => 'membership' ),
					array( 'label' => 'Calendar', 'key' => 'calendar' ),
					array( 'label' => 'Bookings', 'key' => 'booking' ),
					array( 'label' => 'Volunteer', 'key' => 'contact' ),
					array( 'label' => 'Contact', 'key' => 'contact' ),
				), $visibility ) ),
			),
			'newsletter' => array(
				'heading'   => self::cget( $content, 'global', 'footer', 'newsletter_heading', 'Stay in the loop' ),
				'lede'      => self::cget( $content, 'global', 'footer', 'newsletter_lede', 'Fixtures, results and club news — one email a month.' ),
				'shortcode' => (string) self::cget( $content, 'global', 'footer', 'newsletter_shortcode', '' ),
			),
			// Year from the server clock rather than a stored setting: a club that
			// never touches its settings again still has a footer that is right
			// next January.
			'copyright'  => '© ' . gmdate( 'Y' ) . ' ' . $branding->get_club_name() . '. All rights reserved.',
			// The slot has always been here and has always been empty, which is
			// how a site collecting names, emails and phone numbers came to have
			// no route to a privacy policy from anywhere (issue #121). Filtered
			// like every other footer column, so a club that switches a legal
			// page off does not get a link to a page that 404s.
			'legal'      => self::nav_links( array(
				array( 'label' => 'Privacy', 'key' => 'privacy' ),
				array( 'label' => 'Terms', 'key' => 'terms' ),
			), $visibility ),
			// Off is a real choice, not an empty text box: a club running a proper
			// consent plugin needs this out of the way, and blanking the wording
			// would only put the default back (cget treats empty as unset).
			'cookie'     => ! self::cget( $content, 'global', 'cookies', 'show', true ) ? '' : Blueworx_Clubhouse_Sections::cookie_notice( array(
				'text'       => (string) self::cget( $content, 'global', 'cookies', 'text', self::COOKIE_TEXT ),
				'link_label' => (string) self::cget( $content, 'global', 'cookies', 'link_label', 'Read our privacy policy' ),
				'link_href'  => (string) self::cget( $content, 'global', 'cookies', 'link_href', Blueworx_Clubhouse_Links::url( 'privacy' ) ),
				'dismiss'    => (string) self::cget( $content, 'global', 'cookies', 'dismiss', 'Got it' ),
			) ),
		) );
	}

	/**
	 * The default cookie wording. Written to be true of a stock Clubhouse site
	 * rather than to sound thorough: this plugin sets nothing on a visitor's
	 * machine, WordPress sets a cookie when somebody signs in, and the shop and
	 * its payment provider set their own on the pages that take payment. A club
	 * that adds analytics has to say so itself, which is why this is editable.
	 */
	private const COOKIE_TEXT = 'This site uses cookies to keep you signed in and, on our shop pages, to take payment. We do not use them to advertise to you.';

	/**
	 * Drop links whose target page is hidden, then resolve each surviving page
	 * key to its real URL via the Links seam. Filtering by key (not by parsing a
	 * resolved href) keeps hidden-page omission working whether links render as
	 * the preview '?page=' form or real WordPress permalinks.
	 *
	 * @param array<int,array{label:string,key:string}> $items
	 * @return array<int,array{label:string,href:string}>
	 */
	private static function nav_links( array $items, Blueworx_Clubhouse_Visibility $visibility ): array {
		$out = array();
		foreach ( $items as $item ) {
			// Availability first, then the owner's own show/hide. A page whose
			// integration is missing is never linked, however the toggles are set —
			// this is the one place both the header nav and the footer columns pass
			// through, so neither can link a page that cannot render.
			$slug = 'home' === $item['key'] ? '' : $item['key'];
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			if ( ! $visibility->is_page_visible( $item['key'] ) ) {
				continue;
			}
			$out[] = array( 'label' => $item['label'], 'href' => Blueworx_Clubhouse_Links::url( $item['key'] ) );
		}
		return $out;
	}

	/**
	 * Stamp a section's root element with the id its anchor target points at.
	 *
	 * The id goes on the section's own root rather than a wrapper: the looks
	 * give .ch-main's children flow margins and reveal.js hides them until they
	 * scroll in, so an extra element in that child list would take styling meant
	 * for a section and shift the page. An already-identified root is left alone.
	 */
	public static function anchored( string $page, string $section, string $html ): string {
		if ( '' === $html || '<' !== $html[0] ) {
			return $html;
		}
		$id = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $page, $section );
		// Match the opening tag's name only; a root that already carries an id
		// keeps it, because something else is relying on that one.
		if ( ! (bool) preg_match( '/^<([a-z][a-z0-9]*)(\s[^>]*)?>/i', $html, $m ) ) {
			return $html;
		}
		if ( isset( $m[2] ) && (bool) preg_match( '/\sid\s*=/i', $m[2] ) ) {
			return $html;
		}
		$rest = $m[2] ?? '';
		return '<' . $m[1] . ' id="' . $id . '"' . $rest . '>' . substr( $html, strlen( $m[0] ) );
	}

	public static function home(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'home' ), $visibility, $collections, $logo_url, $content );
		}
		$out .= '<main class="ch-main" id="ch-main" tabindex="-1">';
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			// Home uses the full-bleed home_hero() (not the shared hero()); the
			// quick-links live in its foot, so no separate quick_tiles section here.
			// Its own anchor id (not 'hero's) goes on that foot via 'tiles_id', so the
			// catalogue can still offer a link straight to the tiles.
			$out .= self::anchored( 'home', 'hero', Blueworx_Clubhouse_Sections::home_hero( array(
				'eyebrow'            => self::cget( $content, 'home', 'hero', 'eyebrow', 'Est. 1974 · Marlow, UK' ),
				'title_lead'         => self::cget( $content, 'home', 'hero', 'title_lead', 'Every sport. Every age. ' ),
				'title_highlight'    => self::cget( $content, 'home', 'hero', 'title_highlight', 'One community.' ),
				'lede'               => self::cget( $content, 'home', 'hero', 'lede', self::number_word_upper( count( $collections->sports() ) ) . ' sports, ' . self::number_word( count( $collections->teams() ) ) . " teams, and a clubhouse that's always open. Come for the game — stay for the people." ),
				// Off by default — the quick-tile row below repeats these actions. Still
				// configurable: an owner who sets a label in the catalogue gets the
				// button pair back (see home_hero()).
				'cta_primary'        => self::cget( $content, 'home', 'hero', 'cta_primary', '' ),
				'cta_primary_href'   => self::cget( $content, 'home', 'hero', 'cta_primary_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
				'cta_secondary'      => self::cget( $content, 'home', 'hero', 'cta_secondary', '' ),
				'cta_secondary_href' => self::cget( $content, 'home', 'hero', 'cta_secondary_href', Blueworx_Clubhouse_Links::url( 'about' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'home', 'hero', 'image', '' ) ),
				'image_alt'          => $club . ' floodlit pitch on a Saturday',
				'tiles_id'           => Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'quick_tiles' ),
				'tiles'              => self::citems( $content, 'home', 'quick_tiles', array(
					array( 'label' => Blueworx_Clubhouse_Cta::JOIN, 'href' => Blueworx_Clubhouse_Links::url( 'membership' ), 'icon' => 'join' ),
					array( 'label' => 'Take a tour', 'href' => Blueworx_Clubhouse_Links::url( 'about' ), 'icon' => 'tour' ),
					array( 'label' => 'See fixtures', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'icon' => 'fixtures' ),
					array( 'label' => 'Get in touch', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ), 'icon' => 'contact' ),
				) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
			$default = array(
				array( 'text' => '1st XV promoted to Div 3 South' ),
				array( 'text' => 'Open Day — Sat 26 Jul, 10:00–14:00' ),
				array( 'text' => 'Clubhouse refurbishment complete' ),
				array( 'text' => 'Summer Football Camp · 4–8 Aug' ),
			);
			$items = self::citems( $content, 'home', 'ticker', $default );
			$out  .= self::anchored( 'home', 'ticker', Blueworx_Clubhouse_Sections::ticker( array_values( array_map(
				static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
				$items
			) ) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'sports' ) ) {
			// One section, two collections: the reader switches between the club's
			// sports and its teams rather than the page picking one for them. Each
			// group keeps its own "see them all" link, and a group with nothing in it
			// drops out (see Sections::card_grid_switch).
			$out .= self::anchored( 'home', 'sports', Blueworx_Clubhouse_Sections::card_grid_switch( array(
				'eyebrow' => self::cget( $content, 'home', 'sports', 'eyebrow', 'Our sports' ),
				'heading' => self::cget( $content, 'home', 'sports', 'heading', 'Pick your game.' ),
				'groups'  => array(
					'sports' => array(
						'label'      => 'Sports',
						'link_label' => 'All sections →',
						'link_href'  => Blueworx_Clubhouse_Links::url( 'sports' ),
						'cards'      => array_map(
							static function ( array $s ): array {
								return array(
									'image'     => $s['image'],
									'image_alt' => $s['title'],
									'tag'       => $s['label'],
									'title'     => $s['title'],
									'href'      => Blueworx_Clubhouse_Links::item_url( 'sports', self::slugify( (string) $s['title'] ) ),
									'subtitle'  => $s['subtitle'],
								);
							},
							array_slice( $collections->sports(), 0, 4 )
						),
					),
					'teams'  => array(
						'label'      => 'Teams',
						'link_label' => 'All teams →',
						'link_href'  => Blueworx_Clubhouse_Links::url( 'teams' ),
						'cards'      => array_map(
							static function ( array $t ): array {
								return array(
									'image'     => $t['image'],
									'image_alt' => $t['sport'] . ' ' . $t['title'],
									'tag'       => $t['sport'],
									'title'     => $t['title'],
									'href'      => Blueworx_Clubhouse_Links::item_url( 'teams', self::slugify( (string) $t['title'] ) ),
									'subtitle'  => $t['description'],
								);
							},
							array_slice( $collections->teams(), 0, 4 )
						),
					),
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'clubhouse' ) ) {
			$out .= self::anchored( 'home', 'clubhouse', Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => self::cget( $content, 'home', 'clubhouse', 'eyebrow', 'The clubhouse' ),
				'heading'   => self::cget( $content, 'home', 'clubhouse', 'heading', "Bar, kitchen and a full social calendar — the club doesn\u{2019}t stop at the final whistle" ),
				'image'     => self::media_src( (string) self::cget( $content, 'home', 'clubhouse', 'image', '' ) ), 'image_alt' => $club . ' pavilion at dusk',
				'cta_label' => self::cget( $content, 'home', 'clubhouse', 'cta_label', 'Visit us' ), 'cta_href' => self::cget( $content, 'home', 'clubhouse', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'membership' ) ) {
			$out .= self::anchored( 'home', 'membership', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'accent',
				'eyebrow'   => self::cget( $content, 'home', 'membership', 'eyebrow', 'Membership' ),
				'heading'   => self::cget( $content, 'home', 'membership', 'heading', 'Open to everyone, from £28/month.' ),
				'lede'      => self::cget( $content, 'home', 'membership', 'lede', 'From first-timers to county players — every tier includes clubhouse access, discounted events and a free trial session.' ),
				'cta_label' => self::cget( $content, 'home', 'membership', 'cta_label', Blueworx_Clubhouse_Cta::JOIN . ' →' ),
				'cta_href'  => self::cget( $content, 'home', 'membership', 'cta_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			) ) );
			// The Home tier grid mirrors the single Membership tiers source, then
			// funnels each CTA to the fuller Membership page (where conversion → contact
			// happens). Editing the Membership tiers updates both pages.
			$home_tiers = array_map(
				static function ( array $t ): array {
					// Both cadences, or the annual button would slip a checkout
					// link onto Home — the one page whose tier buttons deliberately
					// lead to the Membership page rather than to a purchase.
					$to_membership = static function ( array $cadence ): array {
						$cadence['cta_label'] = 'Join';
						$cadence['cta_href']  = Blueworx_Clubhouse_Links::url( 'membership' );
						return $cadence;
					};
					$t['cta_label'] = 'Join';
					$t['cta_href']  = Blueworx_Clubhouse_Links::url( 'membership' );
					$t['monthly']   = $to_membership( is_array( $t['monthly'] ?? null ) ? $t['monthly'] : array() );
					$t['annual']    = $to_membership( is_array( $t['annual'] ?? null ) ? $t['annual'] : array() );
					return $t;
				},
				self::membership_tiers( $content )
			);
			$out .= Blueworx_Clubhouse_Sections::tier_grid( $home_tiers );
		}
		if ( $visibility->is_section_visible( 'home', 'activity' ) ) {
			$out .= self::anchored( 'home', 'activity', Blueworx_Clubhouse_Sections::activity_tabs( array(
				'eyebrow'  => 'Club activity',
				'heading'  => "What\u{2019}s happening",
				'fixtures' => Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $collections->fixtures() ),
				'events'   => array_map(
					static function ( array $e ): array {
						return array( 'tag' => $e['tag'], 'date' => $e['date'], 'title' => $e['title'], 'detail' => $e['detail'] );
					},
					array_slice( array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) ), 0, 3 )
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'news' ) ) {
			$default = array(
				array( 'image' => '', 'image_alt' => 'Clubhouse interior', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Clubhouse refurbishment complete' ),
				array( 'image' => '', 'image_alt' => 'Junior footballers', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => 'Junior Football signs 40 new players' ),
				array( 'image' => '', 'image_alt' => 'Volunteers', 'tag' => 'Volunteering', 'date' => '24 Jun', 'title' => 'Volunteers needed for the Open Day' ),
			);
			// Real posts first. The section is the club's news, so it should show the
			// club's actual news: the three most recent posts, each linking to the
			// story. The editable items stay as the fallback for a site that has not
			// published yet — better three written headlines than an empty band —
			// and for one that has switched its news section off, where the articles
			// are not clubhouse-dressed and a link would lead somewhere bare.
			$source = $visibility->is_page_visible( 'news' ) ? Blueworx_Clubhouse_News::source() : null;
			$posts  = null !== $source ? $source->recent( 3 ) : array();
			$items  = array() !== $posts
				? array_map(
					static function ( array $p ): array {
						return array(
							'image'     => (string) ( $p['image'] ?? '' ),
							'image_alt' => (string) ( $p['image_alt'] ?? '' ),
							'tag'       => (string) ( $p['category'] ?? '' ),
							'date'      => (string) ( $p['date'] ?? '' ),
							'title'     => (string) ( $p['title'] ?? '' ),
							'href'      => (string) ( $p['href'] ?? '' ),
						);
					},
					$posts
				)
				: self::citems( $content, 'home', 'news', $default );
			$out .= self::anchored( 'home', 'news', Blueworx_Clubhouse_Sections::news_cards( array(
				'eyebrow'    => self::cget( $content, 'home', 'news', 'eyebrow', 'Latest news' ),
				'heading'    => self::cget( $content, 'home', 'news', 'heading', 'From the clubhouse' ),
				// Only offered when the club actually has a news section to send people to.
				'link_label' => 'All news →',
				'link_href'  => $visibility->is_page_visible( 'news' ) ? Blueworx_Clubhouse_Links::url( 'news' ) : '',
				'cards'      => array_map(
					static function ( array $i ): array {
						return array(
							'image'     => self::media_src( (string) ( $i['image'] ?? '' ) ),
							'image_alt' => (string) ( $i['image_alt'] ?? '' ),
							'tag'       => (string) ( $i['tag'] ?? '' ),
							'date'      => (string) ( $i['date'] ?? '' ),
							'title'     => (string) ( $i['title'] ?? '' ),
							// Empty for the editable fallback, which has no story behind it.
							'href'      => (string) ( $i['href'] ?? '' ),
						);
					},
					$items
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'social_feed' ) ) {
			// Stage one's source is the links the club pasted; stage two swaps a
			// Meta connection in here and nothing below this line changes. With no
			// content store there is nothing pasted, so there is nothing to show.
			$posts = null !== $content
				? ( new Blueworx_Clubhouse_Social_Feed( new Blueworx_Clubhouse_Manual_Feed_Source( $content ) ) )->posts()
				: array();
			$count = (int) self::cget( $content, 'home', 'social_feed', 'count', '3' );
			$out  .= self::anchored( 'home', 'social_feed', Blueworx_Clubhouse_Sections::social_feed( array(
				'platform' => self::cget( $content, 'home', 'social_feed', 'platform', 'facebook' ),
				'heading'  => self::cget( $content, 'home', 'social_feed', 'heading', 'Latest from the club' ),
				'lede'     => self::cget( $content, 'home', 'social_feed', 'lede', '' ),
				'posts'    => array_slice( $posts, 0, $count > 0 ? $count : 3 ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'sponsors' ) ) {
			$out .= self::anchored( 'home', 'sponsors', Blueworx_Clubhouse_Sections::sponsors( array(
				'eyebrow' => 'Our partners', 'heading' => 'Our sponsors & partners', 'link_label' => 'Become a sponsor',
				'link_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
				'names'   => array_map( static fn( array $s ): string => $s['name'], $collections->sponsors() ),
			) ) );
		}
		// Socials and the find-us details close the page as ONE light band flush
		// against the footer: address, hours and the map link belong at the foot,
		// nearest the footer, not mid-scroll between content sections. Either half
		// disappears on its own toggle; the band only renders if something is left.
		$social_on = $visibility->is_section_visible( 'home', 'social' );
		$info_on   = $visibility->is_section_visible( 'home', 'info' );
		if ( $social_on || $info_on ) {
			$default = array(
				array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Opening hours', 'lines' => array( 'Mon–Sun', '7:00am – 10:00pm' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Contact', 'lines' => array( 'hello@clubhouse.example', '01628 000 000' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => Blueworx_Clubhouse_Sections::maps_url( array( '12 Riverside Lane', 'Marlow, SL7 1AA' ) ) ),
			);
			$items   = $info_on ? self::citems( $content, 'home', 'info', $default ) : array();
			$columns = array_map(
				static function ( array $i ): array {
					return array(
						'label'      => (string) ( $i['label'] ?? '' ),
						'lines'      => self::lines( $i['lines'] ?? array() ),
						'link_label' => (string) ( $i['link_label'] ?? '' ),
						'link_href'  => (string) ( $i['link_href'] ?? '' ),
					);
				},
				$items
			);
			// One shared root serves both toggles; the root itself carries 'social's
			// anchor, and 'info' gets its own id on the columns element via 'cols_id'
			// (Sections::closing_band()), so both remain independently linkable.
			$out .= self::anchored( 'home', 'social', Blueworx_Clubhouse_Sections::closing_band( array(
				'heading'       => $social_on ? self::cget( $content, 'home', 'social', 'heading', 'Follow the club' ) : '',
				'lede'          => $social_on ? self::cget( $content, 'home', 'social', 'lede', 'Match-day photos, results and behind-the-scenes — join us on socials.' ) : '',
				'facebook_url'  => $social_on ? $branding->get_facebook_url() : '',
				'instagram_url' => $social_on ? $branding->get_instagram_url() : '',
				'linkedin_url'  => $social_on ? $branding->get_linkedin_url() : '',
				'x_url'         => $social_on ? $branding->get_x_url() : '',
				'columns'       => $columns,
				'cols_id'       => Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'info' ),
			) ) );
		}
		$out .= '</main>';
		if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
			$out .= self::shell_footer( $club, $visibility, $branding, $content );
		}
		return $out;
	}

	public static function about(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'about' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'about', 'hero' ) ) {
			$out .= self::anchored( 'about', 'hero', Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'about', 'hero', 'eyebrow', 'About the club' ),
				'title_lead'         => self::cget( $content, 'about', 'hero', 'title_lead', 'Fifty-two years of ' ),
				'title_highlight'    => self::cget( $content, 'about', 'hero', 'title_highlight', 'community sport.' ),
				'lede'               => self::cget( $content, 'about', 'hero', 'lede', 'From one rugby pitch in 1974 to ' . self::number_word( count( $collections->sports() ) ) . ' sports and ' . self::number_word( count( $collections->teams() ) ) . ' teams — ' . $club . ' has always been about more than the game.' ),
				'cta_primary'        => self::cget( $content, 'about', 'hero', 'cta_primary', Blueworx_Clubhouse_Cta::JOIN ),
				'cta_primary_href'   => self::cget( $content, 'about', 'hero', 'cta_primary_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
				'cta_secondary'      => self::cget( $content, 'about', 'hero', 'cta_secondary', 'Meet the committee' ),
				// The committee is further down this same page, so the button that
				// offers to introduce them now goes there instead of to the contact
				// form (issue #164). A club that has switched that section off gets
				// the contact page back, because the anchor would point at nothing.
				'cta_secondary_href' => self::cget( $content, 'about', 'hero', 'cta_secondary_href', $visibility->is_section_visible( 'about', 'committee' )
					? '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'committee' )
					: Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'about', 'hero', 'image', '' ) ),
				'image_alt'          => $club . ' members on the terrace',
				'image_caption'      => '',
			) ) );
		}
		if ( $visibility->is_section_visible( 'about', 'history' ) ) {
			$out .= self::anchored( 'about', 'history', Blueworx_Clubhouse_Sections::timeline( array(
				'eyebrow'    => 'Our story',
				'heading'    => self::cget( $content, 'about', 'history', 'heading', 'From one pitch to ' . self::number_word( count( $collections->sports() ) ) . ' sports' ),
				'milestones' => array_map(
					static function ( array $m ): array {
						return array(
							'year'  => (string) ( $m['year'] ?? '' ),
							'title' => (string) ( $m['title'] ?? '' ),
							'desc'  => (string) ( $m['desc'] ?? '' ),
						);
					},
					self::citems( $content, 'about', 'history', array(
						array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of rugby players lease a field by the river.' ),
						array( 'year' => '1982', 'title' => 'Cricket joins', 'desc' => 'Summer cricket takes over the square; the first pavilion goes up.' ),
						array( 'year' => '1991', 'title' => 'Juniors take root', 'desc' => 'Minis and colts sections launch across rugby and cricket.' ),
						array( 'year' => '2003', 'title' => 'Courts & clubhouse', 'desc' => 'Four tennis courts and the current clubhouse open.' ),
						array( 'year' => '2015', 'title' => 'A multi-sport club', 'desc' => 'Hockey, netball and squash complete the multi-sport club.' ),
						array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment for the next generation.' ),
					) )
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'about', 'values' ) ) {
			$out .= self::anchored( 'about', 'values', Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => 'What we stand for',
				'heading' => 'Our values',
				'cards'   => self::citems( $content, 'about', 'values', array(
					array( 'title' => 'Everyone plays', 'description' => 'Beginners and county players train side by side, every age welcome.' ),
					array( 'title' => 'Volunteer-run', 'description' => 'Coaches, committee and bar staff give their time so the club thrives.' ),
					array( 'title' => 'Community first', 'description' => 'The clubhouse is a place to belong, on and off the pitch.' ),
					array( 'title' => 'Play for life', 'description' => 'Pathways from minis to vets — a home for the whole journey.' ),
				) ),
			) ) );
		}
		// Facilities — the tangible "what we've got" — moves up above the committee,
		// so it lands right after the club's values.
		if ( $visibility->is_section_visible( 'about', 'facilities' ) ) {
			$out .= self::anchored( 'about', 'facilities', Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => self::cget( $content, 'about', 'facilities', 'eyebrow', 'The facilities' ),
				'heading'   => self::cget( $content, 'about', 'facilities', 'heading', 'Five pitches, four courts, one clubhouse' ),
				'image'     => self::media_src( (string) self::cget( $content, 'about', 'facilities', 'image', '' ) ), 'image_alt' => $club . ' grounds from the air',
				// "Book a visit" over a contact form promised a booking nothing here
				// can take. The destination is right — arranging a look round is a
				// conversation — so the label is what changes.
				'cta_label' => self::cget( $content, 'about', 'facilities', 'cta_label', 'Arrange a visit' ), 'cta_href' => self::cget( $content, 'about', 'facilities', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'about', 'committee' ) ) {
			$out .= self::anchored( 'about', 'committee', Blueworx_Clubhouse_Sections::people_grid( array(
				'eyebrow' => 'Who runs the club',
				'heading' => 'The committee',
				'people'  => array_map(
					static function ( array $p ): array {
						return array(
							'name'  => $p['name'],
							'role'  => $p['committee_role'],
							'email' => '',
							'photo' => self::media_src( (string) ( $p['photo'] ?? '' ) ),
						);
					},
					array_values( array_filter( $collections->people(), static fn( $p ) => '' !== $p['committee_role'] ) )
				),
			) ) );
		}
		// "Get involved" — non-playing ways to support the club, distinct from the
		// membership Join CTA that closes the page.
		if ( $visibility->is_section_visible( 'about', 'get_involved' ) ) {
			$out .= self::anchored( 'about', 'get_involved', Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => self::cget( $content, 'about', 'get_involved', 'eyebrow', 'Beyond the pitch' ),
				'heading' => self::cget( $content, 'about', 'get_involved', 'heading', 'Get involved' ),
				'cards'   => self::citems( $content, 'about', 'get_involved', array(
					array( 'title' => 'Volunteer', 'description' => 'Help on match days, run the bar, or join the committee — every hand counts.' ),
					array( 'title' => 'Coach & officiate', 'description' => 'Gain qualifications and give the next generation their start.' ),
					array( 'title' => 'Sponsor & partner', 'description' => 'Back a team or the clubhouse and reach the whole community.' ),
				) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'about', 'cta' ) ) {
			$out .= self::anchored( 'about', 'cta', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Membership',
				'heading'   => self::cget( $content, 'about', 'cta', 'heading', 'Want to be part of it?' ),
				'lede'      => self::cget( $content, 'about', 'cta', 'lede', 'Play, volunteer, or just come for the atmosphere.' ),
				'cta_label' => self::cget( $content, 'about', 'cta', 'cta_label', Blueworx_Clubhouse_Cta::JOIN . ' →' ),
				'cta_href'  => self::cget( $content, 'about', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function membership(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'membership' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		// What this page may promise. A club whose tiers reach a real checkout
		// can say "join and pay"; one whose tiers still point at the contact
		// form must say "register your interest" — see tiers_sell().
		$tiers     = self::membership_tiers( $content );
		$sells     = self::tiers_sell( $tiers, Blueworx_Clubhouse_Checkout::base_url() );
		$tiers_url = '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'membership', 'tiers' );

		if ( $visibility->is_section_visible( 'membership', 'hero' ) ) {
			$out .= self::anchored( 'membership', 'hero', Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'membership', 'hero', 'eyebrow', 'Membership' ),
				'title_lead'         => self::cget( $content, 'membership', 'hero', 'title_lead', $sells ? 'Join in five minutes. ' : 'Find your membership. ' ),
				'title_highlight'    => self::cget( $content, 'membership', 'hero', 'title_highlight', 'Play for years.' ),
				'lede'               => self::cget( $content, 'membership', 'hero', 'lede', 'From first-timers to county players, there is a category for you — every membership includes clubhouse access, discounted events and a free trial.' ),
				'cta_primary'        => self::cget( $content, 'membership', 'hero', 'cta_primary', $sells ? 'Choose your membership' : 'Register interest' ),
				'cta_primary_href'   => self::cget( $content, 'membership', 'hero', 'cta_primary_href', $sells ? $tiers_url : Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'cta_secondary'      => self::cget( $content, 'membership', 'hero', 'cta_secondary', 'Ask a question' ),
				'cta_secondary_href' => self::cget( $content, 'membership', 'hero', 'cta_secondary_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'membership', 'hero', 'image', '' ) ),
				'image_alt'          => $club . ' members warming up',
				'image_caption'      => '',
			) ) );
		}
		// Tiers sit above the fold — the pricing is the primary intent, so it comes
		// straight after the hero, before the supporting "Why join" benefits.
		if ( $visibility->is_section_visible( 'membership', 'tiers' ) ) {
			// h2 here: on Membership the grid follows the page h1 directly, with no
			// section heading between them.
			$out .= self::anchored( 'membership', 'tiers', Blueworx_Clubhouse_Sections::tier_grid( $tiers, 2 ) );
		}
		if ( $visibility->is_section_visible( 'membership', 'why' ) ) {
			$out .= self::anchored( 'membership', 'why', Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => self::cget( $content, 'membership', 'why', 'eyebrow', 'Why join' ),
				'heading' => self::cget( $content, 'membership', 'why', 'heading', 'More than a membership' ),
				'cards'   => self::citems( $content, 'membership', 'why', array(
					array( 'title' => 'All training included', 'description' => 'Access every session for your section, all season.' ),
					array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments, socials and camps.' ),
					array( 'title' => 'Clubhouse & socials', 'description' => 'The bar, the terrace, and a calendar of member events.' ),
					array( 'title' => 'Kit discounts', 'description' => 'Save on team kit at our partner suppliers.' ),
				) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'membership', 'detail' ) ) {
			$default = array_merge(
				array_map(
					static fn( string $t ): array => array( 'text' => $t, 'included' => true ),
					array( "Access to all your section's training", 'League match fees', 'Clubhouse & bar membership', 'Member events & socials' )
				),
				array_map(
					static fn( string $t ): array => array( 'text' => $t, 'included' => false ),
					array( 'Individual coaching (available separately)', 'Tournament entry fees', 'Club kit (discounted, not free)' )
				)
			);
			$items = self::citems( $content, 'membership', 'detail', $default );
			$out .= self::anchored( 'membership', 'detail', Blueworx_Clubhouse_Sections::list_split( array(
				'eyebrow'            => 'The detail',
				'heading'            => 'What is included',
				'included_label'     => 'Included',
				'not_included_label' => 'Not included',
				'policies_label'     => 'Good to know',
				'included'     => array_values( array_map(
					static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
					array_filter( $items, static fn( array $i ): bool => (bool) ( $i['included'] ?? false ) )
				) ),
				'not_included' => array_values( array_map(
					static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
					array_filter( $items, static fn( array $i ): bool => ! ( $i['included'] ?? false ) )
				) ),
				'policies'     => array(
					array( 'title' => 'Free trial', 'desc' => 'Your first session is on us — try before you join.' ),
					array( 'title' => 'Juniors', 'desc' => 'Under-18s pay a reduced rate; safeguarding applies to all youth sections.' ),
					array( 'title' => 'Family cap', 'desc' => 'Family membership covers up to five people at one address.' ),
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'membership', 'steps' ) ) {
			$default = array(
				array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Browse sports and find where you fit.' ),
				array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family, junior or social.' ),
				$sells
					? array( 'number' => '03', 'title' => 'Join and pay', 'description' => 'Pay securely online — it takes a couple of minutes.' )
					: array( 'number' => '03', 'title' => 'Register interest', 'description' => 'Fill in a short form — no payment yet.' ),
				$sells
					? array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will match you to a coach and a session.' )
					: array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will be in touch, then match you to a coach and a session.' ),
			);
			$items = array_values( self::citems( $content, 'membership', 'steps', $default ) );
			$out .= self::anchored( 'membership', 'steps', Blueworx_Clubhouse_Sections::step_grid( array(
				'eyebrow' => 'How to join',
				'heading' => 'Four steps to playing',
				'steps'   => array_map(
					static function ( array $s, int $i ): array {
						return array(
							'number'      => sprintf( '%02d', $i + 1 ),
							'title'       => (string) ( $s['title'] ?? '' ),
							'description' => (string) ( $s['description'] ?? '' ),
						);
					},
					$items,
					array_keys( $items )
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'membership', 'faq' ) ) {
			$default = array(
				array( 'question' => 'Do I have to commit for a season?', 'answer' => 'No — you can join any time and pay monthly.', 'open' => true ),
				array( 'question' => 'Can I try before I join?', 'answer' => 'Yes, your first session is a free trial.', 'open' => false ),
				array( 'question' => 'Do you have junior sections?', 'answer' => 'Every sport runs junior pathways from age 5 upward.', 'open' => false ),
				array( 'question' => 'Is there a family rate?', 'answer' => 'Family membership covers up to five people at one address.', 'open' => false ),
				$sells
					? array( 'question' => 'How do I pay?', 'answer' => 'By card, at checkout, when you join.', 'open' => false )
					: array( 'question' => 'How do I pay?', 'answer' => 'Payment details are arranged once your interest is confirmed.', 'open' => false ),
			);
			$items = self::citems( $content, 'membership', 'faq', $default );
			$out .= self::anchored( 'membership', 'faq', Blueworx_Clubhouse_Sections::faq( array(
				'eyebrow' => 'Questions',
				'heading' => 'Frequently asked',
				'items'   => array_map(
					static function ( array $i ): array {
						return array(
							'question' => (string) ( $i['question'] ?? '' ),
							'answer'   => (string) ( $i['answer'] ?? '' ),
							'open'     => (bool) ( $i['open'] ?? false ),
						);
					},
					$items
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'membership', 'cta' ) ) {
			$out .= self::anchored( 'membership', 'cta', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Ready?',
				'heading'   => self::cget( $content, 'membership', 'cta', 'heading', $sells ? 'Join the club' : 'Register your interest' ),
				'lede'      => self::cget( $content, 'membership', 'cta', 'lede', $sells
					? 'Pick the membership that fits and pay online — you can play this week.'
					: 'Tell us a little about you and we will be in touch within a few days.' ),
				'cta_label' => self::cget( $content, 'membership', 'cta', 'cta_label', $sells ? 'See memberships →' : 'Register interest →' ),
				'cta_href'  => self::cget( $content, 'membership', 'cta', 'cta_href', $sells ? $tiers_url : Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/**
	 * The privacy policy and the terms, which are the same page with different
	 * words: a heading, an intro, and a run of headed clauses a club edits.
	 *
	 * The starter text is deliberately narrow. It says only what is true of a
	 * stock Clubhouse site — what the forms on it collect, what sets cookies,
	 * and the rights a UK visitor has — and it names the things only the club
	 * can answer rather than inventing them. A generated policy that confidently
	 * describes data sharing nobody does is worse than an obviously unfinished
	 * one, because only the second gets corrected.
	 */
	public static function privacy(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		return self::legal_page( 'privacy', $branding, $visibility, $collections, $logo_url, $content );
	}

	public static function terms(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		return self::legal_page( 'terms', $branding, $visibility, $collections, $logo_url, $content );
	}

	private static function legal_page(
		string $page,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url,
		?Blueworx_Clubhouse_Content_Store $content
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( $page ), $visibility, $collections, $logo_url, $content )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		$defaults = 'privacy' === $page ? self::privacy_defaults( $club ) : self::terms_defaults( $club );

		if ( $visibility->is_section_visible( $page, 'hero' ) ) {
			$out .= self::anchored( $page, 'hero', Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, $page, 'hero', 'eyebrow', $defaults['eyebrow'] ),
				'title_lead'         => self::cget( $content, $page, 'hero', 'title_lead', $defaults['title_lead'] ),
				'title_highlight'    => self::cget( $content, $page, 'hero', 'title_highlight', $defaults['title_highlight'] ),
				'lede'               => self::cget( $content, $page, 'hero', 'lede', $defaults['lede'] ),
				// A legal page is a destination, not a journey — nothing to click
				// on to, and no photograph.
				'cta_primary'        => '',
				'cta_primary_href'   => '',
				'cta_secondary'      => '',
				'cta_secondary_href' => '',
				'image'              => '',
				'image_alt'          => '',
				'image_caption'      => '',
			) ) );
		}

		if ( $visibility->is_section_visible( $page, 'body' ) ) {
			$out .= self::anchored( $page, 'body', Blueworx_Clubhouse_Sections::prose( array(
				'heading' => '',
				'blocks'  => array_map(
					static fn ( array $b ): array => array(
						'heading' => (string) ( $b['heading'] ?? '' ),
						'body'    => (string) ( $b['body'] ?? '' ),
					),
					self::citems( $content, $page, 'body', $defaults['blocks'] )
				),
			) ) );
		}

		return $out . '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
	}

	/**
	 * How every worked example opens. One string, so anything that needs to
	 * count what is left to write can do so without carrying its own copy of
	 * the wording.
	 */
	public const EXAMPLE_LEAD = 'Example wording — replace this with your club’s own.';

	/** An example paragraph, labelled as one. */
	private static function eg( string $body ): string {
		return self::EXAMPLE_LEAD . "\n\n" . $body;
	}

	/**
	 * The starter policy wording.
	 *
	 * Two kinds of paragraph. Most say only what is true of a stock Clubhouse
	 * site — those are safe as they stand. The rest answer questions only the
	 * club can answer, and those are worked examples, each opening with the
	 * sentence EXAMPLE_LEAD holds so nobody mistakes one for a decision their
	 * club has made.
	 *
	 * Examples rather than blanks because a club reads a whole page and edits
	 * it; a page of instructions is one nobody starts. They are still not a
	 * policy: a site must not take real sign-ups until a person has been
	 * through and replaced every one. Guarded by a test that counts them.
	 *
	 * @return array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,blocks:array<int,array{heading:string,body:string}>}
	 */
	private static function privacy_defaults( string $club ): array {
		return array(
			'eyebrow'         => 'Privacy',
			'title_lead'      => 'How we look after ',
			'title_highlight' => 'your details.',
			'lede'            => 'What ' . $club . ' collects when you get in touch or join, why we hold it, and how to ask us to change or delete it.',
			'blocks'          => array(
				array(
					'heading' => 'Who we are',
					'body'    => $club . ' runs this website. If you have a question about anything on this page, or you want to see, correct or delete what we hold about you, contact us through the contact page and say so — we will answer.'
						. "\n\n" . self::eg( 'You can write to us at The Clubhouse, Riverside Lane, Anytown AT1 2CD. Data questions go to our Membership Secretary, who is the person responsible for how we handle your details. We are registered with the Information Commissioner’s Office under registration number ZA000000.' ),
				),
				array(
					'heading' => 'What we collect, and when',
					'body'    => 'We only collect what you type into a form on this site, and only when you choose to send it.'
						. "\n\n" . 'Getting in touch: your name, email address and whatever you write in the message. Joining or paying: your name, email address and billing details, which go to our payment provider — we never see or store your card number. Signing in: your email address and password, held by the website itself. Booking a session: your name, email address and phone number.'
						. "\n\n" . self::eg( 'Away from the website, our membership form asks for your date of birth and an emergency contact. For junior members we also ask about medical conditions and allergies, and whether you are happy for your child to appear in club photographs. You can say no to the photographs and still be a member.' ),
				),
				array(
					'heading' => 'Why we hold it',
					'body'    => 'To reply to you, to run your membership, to take a payment you have asked to make, and to organise the sessions you have booked. We do not sell it, and we do not use it to advertise to you.',
				),
				array(
					'heading' => 'Who else sees it',
					'body'    => 'Our website host, and our payment provider when you pay. Both handle it on our behalf and are not allowed to use it for anything else.'
						. "\n\n" . self::eg( 'We also send member names and dates of birth to our county association and our national governing body, because a registered player has to be registered with them too. Our newsletter is sent through an email service that holds the address you gave us, and nothing else.' ),
				),
				array(
					'heading' => 'Cookies',
					'body'    => 'A cookie is a small file a website leaves on your device. This site sets one when you sign in, so it can remember you between pages. Our shop and payment pages set their own when you use them, which is what makes paying work.'
						. "\n\n" . self::eg( 'We do not use analytics or advertising cookies, so there is nothing here that follows you to other websites. If that changes we will say so on this page before it does.' ),
				),
				array(
					'heading' => 'How long we keep it',
					'body'    => self::eg( 'We delete an enquiry once it has been answered and there is nothing left to follow up. Membership records are kept while you are a member and for a year after you leave, in case you come back. Payment records are kept for six years, because we have to.' ),
				),
				array(
					'heading' => 'Your rights',
					'body'    => 'You can ask us what we hold about you, ask us to correct it, and ask us to delete it. You can withdraw consent at any time, and you can complain to the Information Commissioner’s Office at ico.org.uk if you think we have got it wrong. Ask us first if you can — it is usually quicker.',
				),
				array(
					'heading' => 'Children',
					'body'    => self::eg( 'A member under 16 is signed up by a parent or guardian, who gives consent on their behalf and can withdraw it at any time. We hold a junior’s details only for as long as they play with us, and we do not send them email — anything we have to say goes to the parent or guardian.' ),
				),
			),
		);
	}

	/** @return array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,blocks:array<int,array{heading:string,body:string}>} */
	private static function terms_defaults( string $club ): array {
		return array(
			'eyebrow'         => 'Terms',
			'title_lead'      => 'The rules of ',
			'title_highlight' => 'using this site.',
			'lede'            => 'What you can expect from ' . $club . ', and what we expect from you.',
			'blocks'          => array(
				array(
					'heading' => 'Using this website',
					'body'    => 'You are welcome to use this site to find out about the club, get in touch, and manage a membership. Please do not try to break it, take it offline, or use it to send anyone anything they did not ask for.',
				),
				array(
					'heading' => 'Membership and payments',
					'body'    => self::eg( 'A membership runs for twelve months from the day you join and includes use of the club facilities, entry to club sessions, and a vote at the AGM. Payment is taken in full when you join. It does not renew on its own — we will email you before it runs out and you decide. You can cancel at any time by telling us; the membership then runs to the end of the year you have paid for.' ),
				),
				array(
					'heading' => 'Refunds',
					'body'    => self::eg( 'Change your mind about a new membership within fourteen days and we will refund it in full, as long as you have not used the facilities. After that we do not refund part of a year. A session you cancel in time costs you nothing; a session the club cancels is always refunded or moved, whichever you prefer.' ),
				),
				array(
					'heading' => 'Bookings',
					'body'    => self::eg( 'Sessions open for booking two weeks ahead. Cancel at least 24 hours before and there is nothing to pay. Miss a session without telling us and we count it as taken — three of those in a season and we may hold off taking your bookings for a while, because an empty court is one nobody else could use.' ),
				),
				array(
					'heading' => 'Behaviour at the club',
					'body'    => self::eg( 'The short version: treat people, the staff and the place the way you would want them treated. Our code of conduct and safeguarding policy set out the rest, and every member, coach and volunteer agrees to both on joining — ask us for a copy. Serious or repeated breaches can end a membership.' ),
				),
				array(
					'heading' => 'What we can and cannot promise',
					'body'    => 'We keep this site accurate and available as best we can, but we cannot promise it is never wrong or never offline. Fixtures, times and prices can change.',
				),
				array(
					'heading' => 'Changes to these terms',
					'body'    => 'We may update this page. The version here is the one that applies.',
				),
			),
		);
	}

	public static function contact(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'contact' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'contact', 'hero' ) ) {
			$out .= self::anchored( 'contact', 'hero', Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'contact', 'hero', 'eyebrow', 'Contact' ),
				'title_lead'         => self::cget( $content, 'contact', 'hero', 'title_lead', 'We will point you to ' ),
				'title_highlight'    => self::cget( $content, 'contact', 'hero', 'title_highlight', 'the right person.' ),
				'lede'               => self::cget( $content, 'contact', 'hero', 'lede', 'Questions about joining, playing, or hiring the clubhouse? Start here.' ),
				'cta_primary'        => self::cget( $content, 'contact', 'hero', 'cta_primary', 'Email the club' ),
				'cta_primary_href'   => self::cget( $content, 'contact', 'hero', 'cta_primary_href', 'mailto:hello@clubhouse.example' ),
				'cta_secondary'      => self::cget( $content, 'contact', 'hero', 'cta_secondary', 'Call 01628 000 000' ),
				'cta_secondary_href' => self::cget( $content, 'contact', 'hero', 'cta_secondary_href', 'tel:01628000000' ),
				'image'              => self::media_src( (string) self::cget( $content, 'contact', 'hero', 'image', '' ) ), 'image_alt' => '', 'image_caption' => '',
			) ) );
		}
		if ( $visibility->is_section_visible( 'contact', 'form' ) ) {
			$out .= self::anchored( 'contact', 'form', Blueworx_Clubhouse_Sections::contact_form( array(
				'eyebrow'         => self::cget( $content, 'contact', 'form', 'eyebrow', 'Get in touch' ),
				'heading'         => self::cget( $content, 'contact', 'form', 'heading', 'Send us a message' ),
				'club_name'       => $branding->get_club_name(),
				'shortcode'       => (string) self::cget( $content, 'contact', 'form', 'shortcode', '' ),
				'offline_note'    => self::cget( $content, 'contact', 'form', 'offline_note', 'Drop us an email and someone from the committee will come back to you.' ),
				'submit_label'    => self::cget( $content, 'contact', 'form', 'submit_label', 'Send message' ),
				'info'            => array(
					'heading' => self::cget( $content, 'contact', 'form', 'info_heading', 'Find us' ),
					'address' => self::lines( self::cget( $content, 'contact', 'form', 'address', "12 Riverside Lane\nMarlow, SL7 1AA" ) ),
					'email'   => self::cget( $content, 'contact', 'form', 'email', 'hello@clubhouse.example' ),
					'phone'   => self::cget( $content, 'contact', 'form', 'phone', '01628 000 000' ),
					'map'     => self::media_src( (string) self::cget( $content, 'contact', 'form', 'map_image', '' ) ),
					'socials' => array(
						'Facebook'  => $branding->get_facebook_url(),
						'Instagram' => $branding->get_instagram_url(),
						'LinkedIn'  => $branding->get_linkedin_url(),
						'X'         => $branding->get_x_url(),
					),
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'contact', 'directory' ) ) {
			$out .= self::anchored( 'contact', 'directory', Blueworx_Clubhouse_Sections::people_grid( array(
				'eyebrow' => 'Who to contact',
				'heading' => 'The directory',
				'people'  => array_map(
					static function ( array $p ): array {
						return array(
							'name'  => $p['name'],
							'role'  => $p['directory_role'],
							'email' => $p['email'],
							'photo' => self::media_src( (string) ( $p['photo'] ?? '' ) ),
						);
					},
					array_values( array_filter( $collections->people(), static fn( $p ) => '' !== $p['directory_role'] ) )
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'contact', 'social' ) ) {
			$out .= self::anchored( 'contact', 'social', Blueworx_Clubhouse_Sections::closing_band( array(
				'heading'       => self::cget( $content, 'contact', 'social', 'heading', 'Stay connected' ),
				'lede'          => 'Follow the club for match-day updates, results and event announcements.',
				'facebook_url'  => $branding->get_facebook_url(),
				'instagram_url' => $branding->get_instagram_url(),
				'linkedin_url'  => $branding->get_linkedin_url(),
				'x_url'         => $branding->get_x_url(),
				'columns'       => array(),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function login(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'login' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'login', 'form' ) ) {
			// The card draws whichever step of the account journey this request is
			// on. Off WordPress — the preview, the unit tests — the state seam is
			// unset and returns the plain sign-in form a first-time visitor sees.
			$state = Blueworx_Clubhouse_Auth_View::state();
			$out  .= self::anchored( 'login', 'form', Blueworx_Clubhouse_Sections::auth( array(
				'eyebrow'        => 'Members',
				'heading'        => self::cget( $content, 'login', 'form', 'heading', 'Log in to your account' ),
				'lede'           => self::cget( $content, 'login', 'form', 'lede', 'Access your membership, bookings and club events.' ),
				'email_label'    => 'Email or username',
				'password_label' => 'Password',
				'remember_label' => 'Remember me',
				'forgot_label'   => 'Forgot password?',
				'forgot_href'    => Blueworx_Clubhouse_Links::auth_url( Blueworx_Clubhouse_Auth_View::FORGOT ),
				'signin_href'    => Blueworx_Clubhouse_Links::url( 'login' ),
				'submit_label'   => 'Log in',
				'join_prompt'    => 'Not a member yet?',
				'join_label'     => Blueworx_Clubhouse_Cta::JOIN,
				'join_href'      => Blueworx_Clubhouse_Links::url( 'membership' ),
				'state'          => $state,
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/**
	 * The member's account area.
	 *
	 * The one page this plugin serves with no club header and no club footer.
	 * It is a BlueWorx admin screen: the shell it returns is already a whole
	 * page, so unlike every other method here it wraps nothing around it.
	 *
	 * The trailing arguments are the shared page-method signature Page_Map
	 * dispatches with; this page has no branding-driven chrome and no filter.
	 *
	 * @param string $logo_url Unused — the member area carries no club logo.
	 * @param string $filter   Unused — the member area has no filter pills.
	 */
	public static function member_dashboard(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		return Blueworx_Clubhouse_Member_Dashboard::screen(
			Blueworx_Clubhouse_Links::url( 'member-dashboard' ),
			Blueworx_Clubhouse_Links::url( 'home' )
		);
	}

	public static function sports(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club   = $branding->get_club_name();
		$out    = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'sports' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';
		$sports = $collections->sports();
		$pick   = static fn( array $s ): string => (string) $s['title'];
		$labels = self::distinct( $sports, $pick );
		$filter = self::valid_filter( $filter, $labels );

		if ( $visibility->is_section_visible( 'sports', 'hero' ) ) {
			$out .= self::anchored( 'sports', 'hero', Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'sports', 'hero', 'eyebrow', 'Our sports' ),
				'title_lead'      => self::cget( $content, 'sports', 'hero', 'title_lead', self::number_word_upper( count( $sports ) ) . ' sports, ' ),
				'title_highlight' => self::cget( $content, 'sports', 'hero', 'title_highlight', 'one club.' ),
				'lede'            => self::cget( $content, 'sports', 'hero', 'lede', 'From first session to first team — find your section and get playing.' ),
				'filter_label'    => 'Filter by sport',
				'filters'         => self::filter_pills( 'sports', $labels, $filter ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'sports', 'directory' ) ) {
			$out .= self::anchored( 'sports', 'directory', Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'All sections',
				'heading'    => 'Pick your sport.',
				'empty_text' => '' !== $filter ? 'No sections match that filter.' : '',
				'link_label' => Blueworx_Clubhouse_Cta::JOIN . ' →',
				'link_href'  => Blueworx_Clubhouse_Links::url( 'membership' ),
				'cards'      => array_map(
					static function ( array $s ): array {
						return array(
							'image'       => $s['image'],
							'image_alt'   => $s['title'],
							'chip'        => $s['label'],
							'title'       => $s['title'],
							'href'        => Blueworx_Clubhouse_Links::item_url( 'sports', self::slugify( (string) $s['title'] ) ),
							'description' => $s['description'],
							'stats'       => array(
								array( 'value' => $s['stat1_value'], 'label' => $s['stat1_label'] ),
								array( 'value' => $s['stat2_value'], 'label' => $s['stat2_label'] ),
							),
						);
					},
					self::filter_rows( $sports, $filter, $pick )
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'sports', 'cta' ) ) {
			$out .= self::anchored( 'sports', 'cta', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'New to the club?',
				'heading'   => self::cget( $content, 'sports', 'cta', 'heading', 'Try any sport with a free session' ),
				'lede'      => self::cget( $content, 'sports', 'cta', 'lede', 'Not sure which section fits? Come down and try before you join.' ),
				'cta_label' => self::cget( $content, 'sports', 'cta', 'cta_label', 'Register interest →' ),
				'cta_href'  => self::cget( $content, 'sports', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function teams(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club  = $branding->get_club_name();
		$out   = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'teams' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';
		$teams  = $collections->teams();
		$pick   = static fn( array $t ): string => (string) $t['sport'];
		$labels = self::distinct( $teams, $pick );
		$filter = self::valid_filter( $filter, $labels );

		if ( $visibility->is_section_visible( 'teams', 'hero' ) ) {
			$out .= self::anchored( 'teams', 'hero', Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'teams', 'hero', 'eyebrow', 'Our teams' ),
				'title_lead'      => self::cget( $content, 'teams', 'hero', 'title_lead', self::number_word_upper( count( $teams ) ) . ' teams, ' ),
				'title_highlight' => self::cget( $content, 'teams', 'hero', 'title_highlight', 'every level.' ),
				'lede'            => self::cget( $content, 'teams', 'hero', 'lede', 'League sides, development squads and junior pathways across all ' . self::number_word( count( $collections->sports() ) ) . ' sports.' ),
				'filter_label'    => 'Filter teams by sport',
				'filters'         => self::filter_pills( 'teams', $labels, $filter ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'teams', 'directory' ) ) {
			$out .= self::anchored( 'teams', 'directory', Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'Squads',
				'heading'    => 'Find your team.',
				'empty_text' => '' !== $filter ? 'No teams match that filter.' : '',
				'link_label' => '',
				'link_href'  => '',
				'cards'      => array_map(
					static function ( array $t ): array {
						return array(
							'image'       => $t['image'],
							'image_alt'   => $t['sport'] . ' ' . $t['title'],
							'chip'        => $t['sport'],
							'title'       => $t['title'],
							'href'        => Blueworx_Clubhouse_Links::item_url( 'teams', self::slugify( (string) $t['title'] ) ),
							'description' => $t['description'],
							'stats'       => array(
								array( 'value' => $t['match_day'], 'label' => 'Match day' ),
								array( 'value' => $t['league'], 'label' => 'League' ),
							),
							// The club's own page for the team is the card title. This is the
							// team's page somewhere else, and most teams will not have one.
							'cta_label'   => '' !== trim( (string) ( $t['link'] ?? '' ) ) ? 'Team page' : '',
							'cta_href'    => trim( (string) ( $t['link'] ?? '' ) ),
						);
					},
					self::filter_rows( $teams, $filter, $pick )
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'teams', 'cta' ) ) {
			$out .= self::anchored( 'teams', 'cta', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Want to play?',
				'heading'   => self::cget( $content, 'teams', 'cta', 'heading', 'Trials run all season' ),
				'lede'      => self::cget( $content, 'teams', 'cta', 'lede', 'Every squad welcomes new players — get in touch and we will match you to a session.' ),
				'cta_label' => self::cget( $content, 'teams', 'cta', 'cta_label', 'Get in touch →' ),
				'cta_href'  => self::cget( $content, 'teams', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function events(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club     = $branding->get_club_name();
		$out      = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'events' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';
		$pick     = static fn( array $e ): string => (string) $e['tag'];
		// Pills derive from every event's tag (stable across filters); the events
		// shown are narrowed to the current tag, then split into upcoming/past.
		$all      = $collections->events();
		$labels   = self::distinct( $all, $pick );
		$filter   = self::valid_filter( $filter, $labels );
		$filtered = self::filter_rows( $all, $filter, $pick );

		if ( $visibility->is_section_visible( 'events', 'hero' ) ) {
			$out .= self::anchored( 'events', 'hero', Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'events', 'hero', 'eyebrow', "What's on" ),
				'title_lead'      => self::cget( $content, 'events', 'hero', 'title_lead', 'Socials, camps and ' ),
				'title_highlight' => self::cget( $content, 'events', 'hero', 'title_highlight', 'open days.' ),
				'lede'            => self::cget( $content, 'events', 'hero', 'lede', "There's always something happening at the club — on the pitch and off it." ),
				'filter_label'    => 'Filter events by type',
				'filters'         => self::filter_pills( 'events', $labels, $filter ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'events', 'upcoming' ) ) {
			$upcoming = array_values( array_filter( $filtered, static fn( $e ) => 'upcoming' === $e['status'] ) );
			$out .= self::anchored( 'events', 'upcoming', Blueworx_Clubhouse_Sections::event_grid( array(
				'eyebrow'    => 'Coming up',
				'heading'    => 'Upcoming events',
				'empty_text' => '' !== $filter ? 'No events match that filter.' : '',
				'cards'   => array_map(
					static function ( array $e ): array {
						return array(
							'tag'       => $e['tag'],
							'date'      => $e['date'],
							'title'     => $e['title'],
							'detail'    => $e['detail'],
							'cta_label' => $e['cta_label'],
							'cta_href'  => $e['cta_href'],
						);
					},
					$upcoming
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'events', 'past' ) ) {
			$past = array_values( array_filter( $filtered, static fn( $e ) => 'past' === $e['status'] ) );
			$out .= self::anchored( 'events', 'past', Blueworx_Clubhouse_Sections::event_archive( array(
				'heading' => 'Recently at the club',
				'rows'    => array_map(
					static function ( array $e ): array {
						return array( 'date' => $e['date'], 'tag' => $e['tag'], 'title' => $e['title'] );
					},
					$past
				),
			) ) );
		}
		if ( $visibility->is_section_visible( 'events', 'cta' ) ) {
			$out .= self::anchored( 'events', 'cta', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Hosting something?',
				'heading'   => self::cget( $content, 'events', 'cta', 'heading', 'Hire the clubhouse' ),
				'lede'      => self::cget( $content, 'events', 'cta', 'lede', 'Function room and bar available for members and the community.' ),
				'cta_label' => self::cget( $content, 'events', 'cta', 'cta_label', 'Enquire about hire →' ),
				'cta_href'  => self::cget( $content, 'events', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function calendar(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club     = $branding->get_club_name();
		$out      = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'calendar' ), $visibility, $collections, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';
		$fixtures = $collections->fixtures();
		// Fixture 'sport' is a compound label like "Rugby · 1st XV"; the pill filters
		// on the sport prefix before the middot.
		$pick     = static fn( array $f ): string => trim( explode( '·', (string) $f['sport'] )[0] );
		$labels   = self::distinct( $fixtures, $pick );
		$filter   = self::valid_filter( $filter, $labels );

		if ( $visibility->is_section_visible( 'calendar', 'hero' ) ) {
			$out .= self::anchored( 'calendar', 'hero', Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'calendar', 'hero', 'eyebrow', 'Fixtures & results' ),
				'title_lead'      => self::cget( $content, 'calendar', 'hero', 'title_lead', 'Every game, ' ),
				'title_highlight' => self::cget( $content, 'calendar', 'hero', 'title_highlight', 'all season.' ),
				'lede'            => self::cget( $content, 'calendar', 'hero', 'lede', 'Match days across all ' . self::number_word( count( $collections->sports() ) ) . ' sports, with results as they come in.' ),
				// No pills here: on this page they filter the fixtures list far
				// below, not the booking calendar that follows the hero. They
				// render with the schedule instead (issue #147).
				'filter_label'    => '',
				'filters'         => array(),
			) ) );
		}
		// LatePoint's booking calendar, above the fixtures: a member looking at the
		// Calendar page is deciding when to play, and booking is the action that
		// follows. Gated on the integration as well as the toggle, because the
		// Calendar page itself is served whether or not LatePoint is installed.
		if ( Blueworx_Clubhouse_Integrations::section_available( 'calendar', 'booking' )
			&& $visibility->is_section_visible( 'calendar', 'booking' ) ) {
			$out .= self::anchored( 'calendar', 'booking', Blueworx_Clubhouse_Sections::shortcode_block( array(
				'eyebrow'    => self::cget( $content, 'calendar', 'booking', 'eyebrow', 'Court bookings' ),
				'heading'    => self::cget( $content, 'calendar', 'booking', 'heading', 'Book a court' ),
				'shortcode'  => self::cget( $content, 'calendar', 'booking', 'shortcode', '[latepoint_calendar view="month"]' ),
				// The other half of the same journey. This grid answers "when";
				// the Bookings page answers what, where and who, and a member
				// looking at a month of free slots has no way to find out what
				// any of them are without it (issue #136).
				'link_label' => self::cget( $content, 'calendar', 'booking', 'link_label', 'Sessions, courts and coaches' ),
				'link_href'  => self::cget( $content, 'calendar', 'booking', 'link_href', Blueworx_Clubhouse_Links::url( 'booking' ) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'schedule' ) ) {
			$out .= self::anchored( 'calendar', 'schedule', Blueworx_Clubhouse_Sections::calendar_months( array(
				'eyebrow'    => self::cget( $content, 'calendar', 'schedule', 'eyebrow', 'The schedule' ),
				'heading'    => self::cget( $content, 'calendar', 'schedule', 'heading', 'Fixtures & results' ),
				'filter_label' => 'Filter fixtures by sport',
				'filters'      => self::filter_pills( 'calendar', $labels, $filter ),
				// Unlike the other filtered lists, this one says so when the club
				// has entered no fixtures at all. The page is titled "Fixtures &
				// results" and, with the schedule silently absent, the only thing
				// left on it was the court-booking grid — which read as the club
				// having no fixtures ever, or worse, as the bookings BEING the
				// fixtures (issue #99).
				'empty_text' => '' !== $filter
					? 'No fixtures match that filter.'
					: 'No fixtures listed yet — check back soon.',
				'months'     => Blueworx_Clubhouse_Fixture_Projection::calendar_months( self::filter_rows( $fixtures, $filter, $pick ) ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'cta' ) ) {
			$out .= self::anchored( 'calendar', 'cta', Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Follow the club',
				'heading'   => self::cget( $content, 'calendar', 'cta', 'heading', 'Never miss a result' ),
				'lede'      => self::cget( $content, 'calendar', 'cta', 'lede', 'Fixtures, results and club news — one email a month.' ),
				'cta_label' => self::cget( $content, 'calendar', 'cta', 'cta_label', 'Join the mailing list →' ),
				'cta_href'  => self::cget( $content, 'calendar', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/**
	 * One section's own page: the thing the Sports cards used to promise and not
	 * deliver. Assembled entirely from sections that already exist, so it inherits
	 * every look without a new visual language to maintain.
	 *
	 * Returns '' when no sport matches the slug, which is the caller's signal to
	 * fall back to the listing rather than render an empty page.
	 */
	public static function sport_page(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$sport = self::find_by_slug( $collections->sports(), $slug );
		if ( null === $sport ) {
			return '';
		}
		$club  = $branding->get_club_name();
		$title = (string) $sport['title'];
		$out   = self::shell_header( $club, Blueworx_Clubhouse_Links::item_url( 'sports', $slug ), $visibility, $collections, $logo_url, $content )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		$out .= Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => '' !== (string) $sport['label'] ? (string) $sport['label'] : 'Our sports',
			'title_lead'         => $title . ' ',
			'title_highlight'    => 'at ' . $club . '.',
			'lede'               => (string) $sport['subtitle'],
			'cta_primary'        => Blueworx_Clubhouse_Cta::JOIN,
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
			'cta_secondary'      => 'All sports',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'sports' ),
			'image'              => self::media_src( (string) $sport['image'] ),
			'image_alt'          => $title . ' at ' . $club,
			'image_caption'      => '',
		) );

		$out .= self::section_detail_blocks( $sport, $title, $collections );
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/**
	 * One team's own page. Same shape as sport_page(): the team's own words, when
	 * and where it trains, who to ask, and its fixtures.
	 */
	public static function team_page(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$team = self::find_by_slug( $collections->teams(), $slug );
		if ( null === $team ) {
			return '';
		}
		$club  = $branding->get_club_name();
		$title = (string) $team['title'];
		$out   = self::shell_header( $club, Blueworx_Clubhouse_Links::item_url( 'teams', $slug ), $visibility, $collections, $logo_url, $content )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		$out .= Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => (string) $team['sport'],
			'title_lead'         => $title . ' ',
			'title_highlight'    => '' !== (string) $team['league'] ? (string) $team['league'] . '.' : 'squad.',
			'lede'               => (string) $team['description'],
			'cta_primary'        => Blueworx_Clubhouse_Cta::JOIN,
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
			'cta_secondary'      => 'All teams',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'teams' ),
			'image'              => self::media_src( (string) $team['image'] ),
			'image_alt'          => $title . ' at ' . $club,
			'image_caption'      => '',
		) );

		// A team's fixtures are its sport's, narrowed to the ones it plays in.
		$out .= self::section_detail_blocks( $team, (string) $team['sport'], $collections, $title );
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/**
	 * The blocks a sport page and a team page share: what the club says about it,
	 * when it trains, who to ask, and its fixtures. Kept in one place because the
	 * two pages differ only in what fills them.
	 *
	 * @param array<string,mixed> $row        The sport or team.
	 * @param string              $sport_name The sport whose fixtures apply.
	 * @param string              $team_name  Narrow fixtures to this side, when given.
	 */
	private static function section_detail_blocks(
		array $row,
		string $sport_name,
		Blueworx_Clubhouse_Collections $collections,
		string $team_name = ''
	): string {
		$out = '';

		$description = trim( (string) ( $row['description'] ?? '' ) );
		if ( '' !== $description ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'paper',
				'eyebrow'   => 'About the section',
				'heading'   => (string) $row['title'],
				'lede'      => $description,
				// A team can point at its own page elsewhere. A sport has no such
				// field, so this is empty there and the band stays a plain statement.
				'cta_label' => '' !== trim( (string) ( $row['link'] ?? '' ) ) ? 'Visit the team page' : '',
				'cta_href'  => trim( (string) ( $row['link'] ?? '' ) ),
				'cta_external' => true,
			) );
		}

		// Training times and a name to ask for. Rendered only when the club has
		// filled them in — an empty "Training" heading answers nothing.
		$training = self::lines( (string) ( $row['training'] ?? '' ) );
		$contact  = trim( (string) ( $row['contact_name'] ?? '' ) );
		$email    = trim( (string) ( $row['contact_email'] ?? '' ) );
		if ( array() !== $training || '' !== $contact || '' !== $email ) {
			$out .= Blueworx_Clubhouse_Sections::info_panel( array(
				'eyebrow'       => 'Getting involved',
				'heading'       => 'Training and contacts',
				'training'      => $training,
				'contact_name'  => $contact,
				'contact_email' => $email,
			) );
		}

		$fixtures = array_values( array_filter(
			$collections->fixtures(),
			static function ( array $f ) use ( $sport_name, $team_name ): bool {
				// A fixture names its sport as "Rugby · 1st XV" — the sport, then the
				// side. Matching the whole string against "Rugby" found nothing, so
				// the sport is taken from the part before the separator and the side
				// from what follows.
				$parts = array_map( 'trim', explode( '·', (string) $f['sport'] ) );
				$sport = self::slugify( $parts[0] ?? '' );
				$side  = self::slugify( $parts[1] ?? '' );

				if ( '' !== $sport_name && $sport !== self::slugify( $sport_name ) ) {
					return false;
				}
				if ( '' === $team_name ) {
					return true;
				}
				$wanted = self::slugify( $team_name );
				return $side === $wanted
					|| self::slugify( (string) $f['home'] ) === $wanted
					|| self::slugify( (string) $f['away'] ) === $wanted;
			}
		) );
		if ( array() !== $fixtures ) {
			$out .= Blueworx_Clubhouse_Sections::calendar_months( array(
				'eyebrow'    => 'On the calendar',
				'heading'    => 'Fixtures & results',
				'empty_text' => '',
				'months'     => Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures ),
			) );
		}

		return $out;
	}

	/**
	 * The row whose title slugifies to $slug, or null. Matched on the derived slug
	 * rather than a stored one so a club renaming a section does not have to think
	 * about URLs — the same derivation the filter pills already use.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,mixed>|null
	 */
	public static function find_by_slug( array $rows, string $slug ): ?array {
		$slug = self::slugify( $slug );
		if ( '' === $slug ) {
			return null;
		}
		foreach ( $rows as $row ) {
			if ( self::slugify( (string) $row['title'] ) === $slug ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * The booking journey, rendered entirely from LatePoint's own shortcodes:
	 * what you can book, where, who with, and when. This page is only reachable
	 * when LatePoint is live (Page_Map::available), so the shortcodes here always
	 * have something to expand against.
	 *
	 * Each slot's shortcode is ordinary editable content with the standard
	 * LatePoint call as its default, so a club can retune columns or the calendar
	 * view without a release.
	 *
	 * Clearing the field does NOT drop the slot: '' is the unset sentinel every
	 * content field uses, so cget() reads it as "no override" and the default
	 * comes back. Dropping a slot is the visibility toggle's job, exactly as for
	 * every other section — one mechanism, not two that look alike and differ.
	 */
	public static function booking(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'booking' ), $visibility, $collections, $logo_url, $content )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'booking', 'hero' ) ) {
			$out .= self::anchored( 'booking', 'hero', Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'booking', 'hero', 'eyebrow', 'Court bookings' ),
				'title_lead'         => self::cget( $content, 'booking', 'hero', 'title_lead', 'Book your ' ),
				'title_highlight'    => self::cget( $content, 'booking', 'hero', 'title_highlight', 'time on court.' ),
				// It used to say "pick a session, a court and a time" above three
				// lists that offer no times at all — the times are on the
				// Calendar, which the button below now goes to.
				'lede'               => self::cget( $content, 'booking', 'hero', 'lede', 'See what the club runs, where you play and who you book with. Times and free slots are on the calendar.' ),
				'cta_primary'        => self::cget( $content, 'booking', 'hero', 'cta_primary', 'See what is free' ),
				'cta_primary_href'   => self::cget( $content, 'booking', 'hero', 'cta_primary_href', Blueworx_Clubhouse_Links::url( 'calendar' ) . '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'calendar', 'booking' ) ),
				'cta_secondary'      => self::cget( $content, 'booking', 'hero', 'cta_secondary', 'Contact the club' ),
				'cta_secondary_href' => self::cget( $content, 'booking', 'hero', 'cta_secondary_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'booking', 'hero', 'image', '' ) ),
				'image_alt'          => $club,
				'image_caption'      => '',
			) ) );
		}
		// What, where, who. The "when" — LatePoint's booking calendar — lives on the
		// Calendar page instead, beside the fixtures, which is where a member
		// already goes to work out what is happening on court.
		$slots = array(
			'services'  => array(
				'eyebrow'   => 'What you can book',
				'heading'   => 'Sessions and services',
				'shortcode' => '[latepoint_resources items="services" columns="3"]',
			),
			'locations' => array(
				'eyebrow'   => 'Where you play',
				'heading'   => 'Courts and locations',
				'shortcode' => '[latepoint_resources items="locations" columns="3"]',
			),
			'agents'    => array(
				'eyebrow'   => 'Who you book with',
				'heading'   => 'Coaches and staff',
				'shortcode' => '[latepoint_resources items="agents" columns="3"]',
			),
		);
		foreach ( $slots as $key => $slot ) {
			if ( ! $visibility->is_section_visible( 'booking', $key ) ) {
				continue;
			}
			$out .= self::anchored( 'booking', $key, Blueworx_Clubhouse_Sections::shortcode_block( array(
				'eyebrow'   => self::cget( $content, 'booking', $key, 'eyebrow', $slot['eyebrow'] ),
				'heading'   => self::cget( $content, 'booking', $key, 'heading', $slot['heading'] ),
				'shortcode' => self::cget( $content, 'booking', $key, 'shortcode', $slot['shortcode'] ),
			) ) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/**
	 * Club news — the index.
	 *
	 * The posts come through the News seam rather than being passed in: the page
	 * map's render signature is shared by every page, and eight pages that never
	 * touch a post would all have had to carry a post source they ignore. A caller
	 * that installs no source (the SEO report, which renders every page in-process)
	 * gets the empty state, which is the truthful answer for a site with no news.
	 *
	 * $filter carries the category slug, so the category pills work through the
	 * same query param and the same swap-in-place script as the sports and events
	 * filters.
	 */
	public static function blog(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'news' ), $visibility, $collections, $logo_url, $content )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		$source     = Blueworx_Clubhouse_News::source();
		$categories = null !== $source ? $source->categories() : array();
		// An unknown category is not an error — it is a stale bookmark or a
		// category that has been renamed. Fall back to everything.
		$known  = array_column( $categories, 'slug' );
		$filter = in_array( $filter, $known, true ) ? $filter : '';

		if ( $visibility->is_section_visible( 'news', 'head' ) ) {
			$out .= self::anchored( 'news', 'head', Blueworx_Clubhouse_Sections::news_head( array(
				'eyebrow'         => self::cget( $content, 'news', 'head', 'eyebrow', 'The clubhouse journal' ),
				'title_lead'      => self::cget( $content, 'news', 'head', 'title_lead', 'News from ' ),
				'title_highlight' => self::cget( $content, 'news', 'head', 'title_highlight', 'the club.' ),
				'lede'            => self::cget( $content, 'news', 'head', 'lede', 'Match reports, section updates, coaching notes and everything else happening on and off the pitch.' ),
			) ) );
		}

		$total  = null !== $source ? $source->count( $filter ) : 0;
		$paging = Blueworx_Clubhouse_News::paging( $total, Blueworx_Clubhouse_News::requested_page() );
		$posts  = null !== $source ? $source->recent( Blueworx_Clubhouse_News::PER_PAGE, $paging['offset'], $filter ) : array();

		// The lead story is only lifted out on the unfiltered first page. On page
		// two, or inside a category, "featured" would just mean "whichever post
		// happens to be first", and the same post would appear twice.
		$featured = null;
		if ( '' === $filter && 1 === $paging['page'] && array() !== $posts
			&& $visibility->is_section_visible( 'news', 'featured' ) ) {
			$featured = array_shift( $posts );
		}

		if ( null !== $featured ) {
			$out .= self::anchored( 'news', 'featured', Blueworx_Clubhouse_Sections::news_featured( array(
				'post'  => $featured,
				'label' => 'Featured',
				'cta'   => 'Read the story',
			) ) );
		}

		if ( $visibility->is_section_visible( 'news', 'posts' ) ) {
			$out .= self::anchored( 'news', 'posts', Blueworx_Clubhouse_Sections::news_grid( array(
				'filter_label' => 'Filter news by category',
				'filters'      => self::news_filters( $categories, $filter ),
				'count_label'  => self::count_label( $total ),
				'posts'        => $posts,
				'empty_text'   => '' === $filter
					? 'There is no club news yet. Anything the club publishes will appear here.'
					: 'No news in this category yet.',
				'pager'        => self::pager_model( $paging, $filter ),
			) ) );
		}

		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	/** "12 stories" — the count beside the category pills. */
	private static function count_label( int $total ): string {
		return 1 === $total ? '1 story' : $total . ' stories';
	}

	/**
	 * The category pills, "All" first and always present — without it a reader who
	 * filters has no way back to everything short of editing the address.
	 *
	 * @param array<int,array{label:string,slug:string}> $categories
	 * @return array<int,array{label:string,href:string,active:bool}>
	 */
	private static function news_filters( array $categories, string $current ): array {
		if ( array() === $categories ) {
			return array();
		}
		$pills = array(
			array( 'label' => 'All', 'href' => Blueworx_Clubhouse_News::url(), 'active' => '' === $current ),
		);
		foreach ( $categories as $category ) {
			$pills[] = array(
				'label'  => (string) $category['label'],
				'href'   => Blueworx_Clubhouse_News::url( (string) $category['slug'] ),
				'active' => $current === (string) $category['slug'],
			);
		}
		return $pills;
	}

	/**
	 * @param array{page:int,pages:int,offset:int} $paging
	 * @return array{page:int,pages:int,prev_href:string,next_href:string,pages_list:array<int,array{label:string,href:string,active:bool}>}
	 */
	private static function pager_model( array $paging, string $filter ): array {
		$list = array();
		for ( $i = 1; $i <= $paging['pages']; $i++ ) {
			$list[] = array(
				'label'  => (string) $i,
				'href'   => Blueworx_Clubhouse_News::url( $filter, $i ),
				'active' => $i === $paging['page'],
			);
		}
		return array(
			'page'       => $paging['page'],
			'pages'      => $paging['pages'],
			'prev_href'  => $paging['page'] > 1 ? Blueworx_Clubhouse_News::url( $filter, $paging['page'] - 1 ) : '',
			'next_href'  => $paging['page'] < $paging['pages'] ? Blueworx_Clubhouse_News::url( $filter, $paging['page'] + 1 ) : '',
			'pages_list' => $list,
		);
	}

	/**
	 * A single article.
	 *
	 * Not reached through the page map: an article lives at whatever permalink
	 * WordPress gives it, so the front end routes to this directly. It takes the
	 * same arguments as every other page renderer so the shell either side of it
	 * is composed identically — the header and footer here are the same header and
	 * footer as everywhere else on the site.
	 */
	public static function post(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		$club   = $branding->get_club_name();
		$source = Blueworx_Clubhouse_News::source();
		$post   = null !== $source ? $source->current() : null;

		$out = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'news' ), $visibility, $collections, $logo_url, $content )
			. '<main class="ch-main ch-main--article" id="ch-main" tabindex="-1">';

		if ( null === $post ) {
			// Routed here without a post to show. Say so in the club's own look
			// rather than falling through to a blank article shell.
			$out .= '<section class="ch-sec"><div class="ch-wrap"><p class="ch-empty">'
				. 'That story is no longer here. Everything the club has published is on the news page.'
				. '</p></div></section>';
			$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
			return $out;
		}

		// One <article> element holding the whole story, rather than five sections
		// sitting directly in <main>. The looks put a 96px flow margin between
		// main's children, which is the right rhythm between the bands of a landing
		// page and far too much between a byline and the first paragraph of the
		// piece it belongs to. Nesting takes the article out of that flow and lets
		// its own spacing govern, without a look having to know about news.
		$out .= '<article class="ch-article">';
		$out .= Blueworx_Clubhouse_Sections::post_head( array(
			'back_label' => 'All news',
			'back_href'  => Blueworx_Clubhouse_Links::url( 'news' ),
			'post'       => $post,
		) );
		$out .= Blueworx_Clubhouse_Sections::post_media( array(
			'image'     => (string) $post['image'],
			'image_alt' => (string) $post['image_alt'],
			'caption'   => (string) $post['image_caption'],
		) );
		$out .= Blueworx_Clubhouse_Sections::post_body( array(
			'html' => (string) $post['html'],
			'tags' => (array) $post['tags'],
		) );
		$out .= Blueworx_Clubhouse_Sections::post_author( array(
			'label'  => 'Written by',
			'author' => (array) $post['author'],
		) );
		$out .= Blueworx_Clubhouse_Sections::post_share( array(
			'title' => (string) $post['title'],
			'url'   => (string) $post['href'],
		) );
		$out .= '</article>';
		// Outside the article: what to read next is not part of this story.
		$out .= Blueworx_Clubhouse_Sections::post_steps(
			null !== $source ? $source->adjacent() : array( 'previous' => null, 'next' => null )
		);
		$out .= Blueworx_Clubhouse_Sections::post_related( array(
			'heading'    => 'Keep reading',
			'link_label' => 'All news',
			'link_href'  => Blueworx_Clubhouse_Links::url( 'news' ),
			'posts'      => null !== $source ? $source->related( Blueworx_Clubhouse_News::RELATED ) : array(),
		) );

		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}
}
