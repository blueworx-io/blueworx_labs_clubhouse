<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a page out of the blocks it is composed of.
 *
 * This is the loop that replaces eleven hand-written page methods. It reads the
 * page's block ids, resolves each against the library, drops the ones it cannot
 * render, sorts what is left, and renders each in turn between the header and
 * the footer — which are not on any page's list, because they are on every one.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Composer {

	private Blueworx_Clubhouse_Block_Library $library;
	private Blueworx_Clubhouse_Page_Composition $composition;

	public function __construct(
		Blueworx_Clubhouse_Block_Library $library,
		Blueworx_Clubhouse_Page_Composition $composition
	) {
		$this->library     = $library;
		$this->composition = $composition;
	}

	/**
	 * Whether this site has been composed yet.
	 *
	 * False on a site that has not been seeded or migrated, which is every site
	 * for the moment between the plugin updating and its upgrade routine
	 * running. Callers fall back to the old page methods, so that moment shows
	 * the club its site rather than a stack of empty pages.
	 */
	public function is_ready(): bool {
		return $this->composition->is_configured();
	}

	/**
	 * A whole page's body — header, main, footer — for the page whose key is
	 * given. Takes the same arguments the page methods took, so Page_Map can
	 * call it in their place.
	 */
	public function page(
		string $page,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		string $filter = ''
	): string {
		$blocks = $this->blocks_for( $page );
		$state  = new Blueworx_Clubhouse_Page_State( $page, $branding, $visibility, $collections, $this->library, $blocks, $filter );
		$club   = $branding->get_club_name();

		$out = '';
		if ( $visibility->is_section_visible( $page, 'header' ) ) {
			$out .= $this->header( $page, $state, $collections, $logo_url );
		}
		$out .= '<main class="ch-main" id="ch-main" tabindex="-1">';
		foreach ( $blocks as $block ) {
			$out .= $this->anchored( $block, Blueworx_Clubhouse_Block_Renderers::render( $block, $state ) );
		}
		$out .= '</main>';
		if ( $visibility->is_section_visible( $page, 'footer' ) ) {
			$out .= $this->footer( $state, $visibility, $branding );
		}
		return $out;
	}

	/**
	 * The blocks this page shows, in render order: each block's own position,
	 * with the stored list order breaking ties.
	 *
	 * Two kinds of id are dropped rather than rendered: one naming a block that
	 * no longer exists, and one whose type needs a plugin this site has not got.
	 * Both are ordinary states — a deleted block, an uninstalled integration —
	 * not errors, and a page missing a section is better than a fatal one.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function blocks_for( string $page ): array {
		$rows = array();
		foreach ( $this->composition->blocks( $page ) as $index => $id ) {
			$block = $this->library->get( $id );
			if ( null === $block ) {
				continue;
			}
			if ( ! self::available( $block ) ) {
				continue;
			}
			$rows[] = array( (int) $block['position'], $index, $block );
		}
		usort(
			$rows,
			static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] )
		);
		return array_column( $rows, 2 );
	}

	/**
	 * Whether this site can render this block at all.
	 *
	 * A block that came from one of the plugin's own addresses is judged on
	 * that address, because that is the judgement the site has always made:
	 * only the Calendar's booking grid needs a third party present. A block an
	 * owner created fresh has no address, so its type answers instead — which
	 * is what keeps a booking block off a site with no booking plugin.
	 *
	 * @param array<string,mixed> $block
	 */
	private static function available( array $block ): bool {
		$key = (string) ( $block['defaults_key'] ?? '' );
		if ( '' !== $key && str_contains( $key, '/' ) ) {
			[ $page, $section ] = explode( '/', $key, 2 );
			return Blueworx_Clubhouse_Integrations::section_available( $page, $section );
		}
		$type = Blueworx_Clubhouse_Block_Types::get( (string) ( $block['type'] ?? '' ) );
		if ( null === $type ) {
			return false;
		}
		return '' === $type['requires'] || Blueworx_Clubhouse_Integrations::provides( $type['requires'] );
	}

	/**
	 * Stamp a block's markup with the id its anchor target points at.
	 *
	 * A seeded or migrated block takes the anchor of the address it came from,
	 * so every link an owner has already made keeps working. A block created
	 * fresh has no such address and takes its type's name instead.
	 *
	 * @param array<string,mixed> $block
	 */
	private function anchored( array $block, string $html ): string {
		if ( '' === $html ) {
			return '';
		}
		$key = (string) ( $block['defaults_key'] ?? '' );
		if ( in_array( $key, Blueworx_Clubhouse_Block_Addresses::unanchored(), true ) ) {
			return $html;
		}
		if ( '' !== $key && str_contains( $key, '/' ) ) {
			[ $page, $section ] = explode( '/', $key, 2 );
			return Blueworx_Clubhouse_Page_Renderer::anchored( $page, $section, $html );
		}
		return $html;
	}

	// -- The shell ------------------------------------------------------------

	/**
	 * The site header. Its content is a singleton block — one header, edited
	 * once, shown on every page — so it is looked up by type rather than read
	 * off the page's own list.
	 */
	private function header(
		string $page,
		Blueworx_Clubhouse_Page_State $state,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url
	): string {
		$block    = $this->singleton( 'header' );
		$content  = (array) ( $block['content'] ?? array() );
		$defaults = Blueworx_Clubhouse_Block_Defaults::for_key( 'global/header', $state );

		// The announcement bar is owner-configurable: a show/hide switch plus
		// editable text and link. When off — or when the text is cleared —
		// Sections::header()'s empty-string guard drops the markup.
		$banner_on = (bool) Blueworx_Clubhouse_Block_Content::field( $content, $defaults, 'banner_show', true );

		// A signed-in member is offered the way out where they found the way in,
		// rather than being sent to wp-admin to find it. Off WordPress the state
		// seam is unset, so the preview keeps showing "Log in".
		$auth      = Blueworx_Clubhouse_Auth_View::state();
		$signed_in = '' !== $auth['logged_in'] && '' !== $auth['logout_url'];

		return Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => $state->club(),
			'banner'      => $banner_on ? Blueworx_Clubhouse_Block_Content::text( $content, $defaults, 'banner' ) : '',
			'banner_href' => Blueworx_Clubhouse_Block_Content::text( $content, $defaults, 'banner_href' ),
			'nav'         => Blueworx_Clubhouse_Menu::current()->items( $collections, $state->visibility() ),
			'active'      => Blueworx_Clubhouse_Links::url( $page ),
			'login'       => $signed_in ? 'Log out' : 'Log in',
			'login_href'  => $signed_in ? $auth['logout_url'] : Blueworx_Clubhouse_Links::url( 'login' ),
			'join'        => Blueworx_Clubhouse_Block_Content::text( $content, $defaults, 'join' ),
			'join_href'   => Blueworx_Clubhouse_Block_Content::text( $content, $defaults, 'join_href' ),
			'logo'        => $logo_url,
		) );
	}

	private function footer(
		Blueworx_Clubhouse_Page_State $state,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Branding $branding
	): string {
		$block    = $this->singleton( 'footer' );
		$content  = (array) ( $block['content'] ?? array() );
		$defaults = Blueworx_Clubhouse_Block_Defaults::for_key( 'global/footer', $state );
		$field    = static fn( string $key, mixed $fallback = '' ): string => Blueworx_Clubhouse_Block_Content::text( $content, $defaults, $key, $fallback );

		// Off is a real choice, not an empty text box: a club running a proper
		// consent plugin needs this out of the way, and blanking the wording
		// would only put the default back.
		$cookies_on = (bool) Blueworx_Clubhouse_Block_Content::field( $content, $defaults, 'cookie_show', true );

		return Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => $state->club(),
			'tagline'    => $field( 'tagline' ),
			'socials'    => array(
				'Facebook'  => $branding->get_facebook_url(),
				'Instagram' => $branding->get_instagram_url(),
				'LinkedIn'  => $branding->get_linkedin_url(),
				'X'         => $branding->get_x_url(),
			),
			'columns'    => array(
				array(
					'title' => 'Club',
					'links' => self::nav_links( array(
						array( 'label' => 'About', 'key' => 'about' ),
						array( 'label' => 'Sports', 'key' => 'sports' ),
						array( 'label' => 'Teams', 'key' => 'teams' ),
						array( 'label' => 'Events', 'key' => 'events' ),
						array( 'label' => 'News', 'key' => 'news' ),
					), $visibility ),
				),
				array(
					'title' => 'Get involved',
					'links' => self::nav_links( array(
						array( 'label' => 'Membership', 'key' => 'membership' ),
						array( 'label' => 'Calendar', 'key' => 'calendar' ),
						array( 'label' => 'Bookings', 'key' => 'booking' ),
						array( 'label' => 'Volunteer', 'key' => 'contact' ),
						array( 'label' => 'Contact', 'key' => 'contact' ),
					), $visibility ),
				),
			),
			'newsletter' => array(
				'heading'   => $field( 'newsletter_heading' ),
				'lede'      => $field( 'newsletter_lede' ),
				'shortcode' => $field( 'newsletter_shortcode' ),
			),
			// Year from the server clock rather than a stored setting: a club that
			// never touches its settings again still has a footer that is right
			// next January.
			'copyright'  => '© ' . gmdate( 'Y' ) . ' ' . $branding->get_club_name() . '. All rights reserved.',
			'legal'      => self::nav_links( array(
				array( 'label' => 'Privacy', 'key' => 'privacy' ),
				array( 'label' => 'Terms', 'key' => 'terms' ),
			), $visibility ),
			'cookie'     => ! $cookies_on ? '' : Blueworx_Clubhouse_Sections::cookie_notice( array(
				'text'       => $field( 'cookie_text' ),
				'link_label' => $field( 'cookie_link_label' ),
				'link_href'  => $field( 'cookie_link_href' ),
				'dismiss'    => $field( 'cookie_dismiss' ),
			) ),
		) );
	}

	/**
	 * The one block of a singleton type, or an empty stand-in when the library
	 * has none — a site mid-upgrade still gets a header rather than a blank
	 * page, drawn entirely from the code defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function singleton( string $type ): array {
		foreach ( $this->library->all() as $block ) {
			if ( (string) $block['type'] === $type ) {
				return $block;
			}
		}
		return array( 'type' => $type, 'content' => array(), 'settings' => array() );
	}

	/**
	 * Drop links whose target page is hidden or whose integration is absent,
	 * then resolve each surviving page key to its real URL. Filtering by key
	 * keeps hidden-page omission working whether links render as the preview
	 * '?page=' form or real WordPress permalinks.
	 *
	 * @param array<int,array{label:string,key:string}> $items
	 * @return array<int,array{label:string,href:string}>
	 */
	private static function nav_links( array $items, Blueworx_Clubhouse_Visibility $visibility ): array {
		$out = array();
		foreach ( $items as $item ) {
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
}
