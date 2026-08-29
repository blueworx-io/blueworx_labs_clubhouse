<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every editable content area, said in the page editor library's vocabulary.
 *
 * One source, three readers: Page_Editors builds the fifteen screens from it,
 * Page_Content casts stored values back by the kinds it declares, and the
 * migration reads it to know where each old address now lives. A field that is
 * not here is a field that cannot be edited, cannot be read and will not be
 * migrated — which is why a lockstep test holds it against the catalogue it
 * replaces until that catalogue is deleted.
 *
 * A straight translation of Content_Catalogue::pages(), not a redesign: every
 * catalogue field keeps its label, its placeholder, its rows, its default and
 * its options. Nothing here reads the catalogue at runtime — the two classes
 * describe the same content independently, and PageFieldsTest is what proves
 * they still agree.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Fields {

	/** The field key a repeating panel's rows live under — Content_Store's own. */
	public const REPEATER_FIELD = 'items';

	/**
	 * The bare key a copytext placeholder is filed under. Never a real
	 * catalogue field — see copytext() — which is how field_key() recognises
	 * one and refuses to hand the migration an address to nothing.
	 */
	private const COPYTEXT_KEY = 'about';

	/** A field's id: its section and its key, joined. Unique within an area. */
	public static function field_id( string $section, string $field ): string {
		return $section . '_' . $field;
	}

	/**
	 * The inverse of field_id(): the bare key a field id was built from, or ''
	 * when the id was never built by field_id() at all — the panel's own
	 * auto-declared show/hide switch (task 3), or this class's copytext
	 * placeholder. Task 8's migration uses this to address the old catalogue
	 * option; a field that was never stored there has nothing to migrate.
	 */
	public static function field_key( string $section, string $field_id ): string {
		$prefix = $section . '_';
		if ( 0 !== strpos( $field_id, $prefix ) ) {
			return '';
		}
		$key = substr( $field_id, strlen( $prefix ) );
		if ( '_shown' === $key || self::COPYTEXT_KEY === $key ) {
			return '';
		}
		return $key;
	}

	/**
	 * The library kind a field was declared with, or '' when this class has no
	 * such field — an unknown section, an unknown key, or the panel's own
	 * show/hide switch, which task 3 adds a case for.
	 */
	public static function kind_of( string $area, string $section, string $field ): string {
		$areas = self::areas();
		if ( ! isset( $areas[ $area ] ) ) {
			return '';
		}
		$id = self::field_id( $section, $field );
		foreach ( $areas[ $area ]['tabs'] as $tab ) {
			foreach ( $tab['panels'] as $panel ) {
				if ( $panel['id'] !== $section ) {
					continue;
				}
				foreach ( $panel['fields'] as $f ) {
					if ( $f['id'] === $id ) {
						return (string) $f['kind'];
					}
				}
			}
		}
		return '';
	}

	/**
	 * The human name for a stored content address ("Home · Hero"), or the raw
	 * address when this class no longer has it. Replaces
	 * Content_Catalogue::address_label() — task 10 repoints its callers.
	 */
	public static function address_label( string $address ): string {
		$slash = strpos( $address, '/' );
		if ( false === $slash ) {
			return $address;
		}
		$area    = substr( $address, 0, $slash );
		$section = substr( $address, $slash + 1 );

		$areas = self::areas();
		if ( ! isset( $areas[ $area ] ) ) {
			return $address;
		}
		foreach ( $areas[ $area ]['tabs'] as $tab ) {
			foreach ( $tab['panels'] as $panel ) {
				if ( $panel['id'] === $section ) {
					return $areas[ $area ]['label'] . ' · ' . $panel['title'];
				}
			}
		}
		return $address;
	}

	// ---------------------------------------------------------------------
	// Field builders. One per catalogue field type, per the brief's mapping
	// table. Every field id is built through field_id() so it stays unique
	// within its area and reversible by field_key().
	// ---------------------------------------------------------------------

	private static function text( string $section, string $key, string $label, string $ph = '' ): array {
		$out = array( 'id' => self::field_id( $section, $key ), 'kind' => 'text', 'label' => $label );
		if ( '' !== $ph ) {
			$out['placeholder'] = $ph;
		}
		return $out;
	}

	private static function area_field( string $section, string $key, string $label, int $rows = 3 ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'textarea', 'label' => $label, 'rows' => $rows );
	}

	private static function url( string $section, string $key, string $label ): array {
		// Suggestions are attached in Page_Editors, not here: they depend on
		// the site's own pages and shop, and this class stays pure.
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'text', 'format' => 'url', 'label' => $label );
	}

	private static function media( string $section, string $key, string $label ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'media', 'label' => $label );
	}

	private static function toggle( string $section, string $key, string $label, bool $default = false ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'toggle', 'label' => $label, 'default' => $default );
	}

	/** @param array<string,string> $options value => label */
	private static function options( array $options ): array {
		$out = array();
		foreach ( $options as $value => $text ) {
			$out[] = array( 'value' => (string) $value, 'label' => (string) $text );
		}
		return $out;
	}

	/** @param array<string,string> $options value => label */
	private static function select( string $section, string $key, string $label, array $options ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'select', 'label' => $label, 'options' => self::options( $options ) );
	}

	/** @param array<int,array<string,mixed>> $cells */
	private static function repeater( string $section, string $label, array $cells ): array {
		return array(
			'id'     => self::field_id( $section, self::REPEATER_FIELD ),
			'kind'   => 'repeater',
			'label'  => $label,
			'fields' => $cells,
		);
	}

	/** A row cell. Its id is bare — repeater scopes are separate, so no prefix. */
	private static function cell( string $id, string $kind, string $label, array $extra = array() ): array {
		return array_merge( array( 'id' => $id, 'kind' => $kind, 'label' => $label ), $extra );
	}

	/**
	 * Display-only prose, where a section points at a collection or at
	 * auto-generated content instead of editing it directly. Carries the
	 * section's own label, since Schema::validate() requires one of every
	 * field, copytext included — the catalogue's 'link'/'auto' sentinels
	 * never needed one because they were never registered against it.
	 */
	private static function copytext( string $section, string $label, string $text ): array {
		return array( 'id' => self::field_id( $section, self::COPYTEXT_KEY ), 'kind' => 'copytext', 'label' => $label, 'text' => $text );
	}

	/**
	 * One tab, wrapping the "every area gets one tab, `content`" default that
	 * every area but Home uses.
	 *
	 * @param array<int,array<string,mixed>> $panels
	 */
	private static function content_tab( array $panels ): array {
		return array( array( 'id' => 'content', 'label' => 'Content', 'panels' => $panels ) );
	}

	/**
	 * One panel. `hideable` is computed from $hideable rather than declared at
	 * each call site, so it can never drift from Setup_Sections::inventory() —
	 * see hideable_panels().
	 *
	 * @param array<string,bool>             $hideable
	 * @param array<int,array<string,mixed>> $fields
	 */
	private static function panel( array $hideable, string $area, string $id, string $title, array $fields, string $note = '', string $eyebrow = '' ): array {
		$panel = array( 'id' => $id, 'title' => $title );
		if ( '' !== $eyebrow ) {
			$panel['eyebrow'] = $eyebrow;
		}
		if ( '' !== $note ) {
			$panel['note'] = $note;
		}
		$panel['hideable'] = self::is_hideable( $hideable, $area, $id );
		$panel['fields']   = $fields;
		return $panel;
	}

	/**
	 * Every "<page>/<section>" pair a club can switch off today, read from
	 * Setup_Sections::inventory() rather than duplicated here — the source
	 * this class is told to match.
	 *
	 * @return array<string,bool>
	 */
	private static function hideable_panels(): array {
		$keys = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$keys[ $page['page'] . '/' . $section['key'] ] = true;
			}
		}
		return $keys;
	}

	/**
	 * @param array<string,bool> $hideable
	 */
	private static function is_hideable( array $hideable, string $area, string $panel_id ): bool {
		// Setup_Sections keys the sitewide chrome (header, footer, welcome
		// pack, cookie notice) under 'home', not 'global' — the visibility
		// inventory is keyed by the pages this plugin serves, and none of the
		// four belong to one page on their own.
		$page = 'global' === $area ? 'home' : $area;
		return isset( $hideable[ $page . '/' . $panel_id ] );
	}

	// ---------------------------------------------------------------------
	// Field sets shared by several panels, mirroring Content_Catalogue's own
	// private hero_fields()/cta_fields()/etc. — translated the same way.
	// ---------------------------------------------------------------------

	private static function hero_fields( string $section ): array {
		return array(
			self::text( $section, 'eyebrow', 'Eyebrow', 'e.g. Est. 1974 · Marlow, UK' ),
			self::text( $section, 'title_lead', 'Heading' ),
			self::text( $section, 'title_highlight', 'Highlighted phrase' ),
			self::area_field( $section, 'lede', 'Subheading' ),
			self::text( $section, 'cta_primary', 'Primary button label' ),
			self::url( $section, 'cta_primary_href', 'Primary button link' ),
			self::text( $section, 'cta_secondary', 'Secondary button label' ),
			self::url( $section, 'cta_secondary_href', 'Secondary button link' ),
			self::media( $section, 'image', 'Background image' ),
		);
	}

	/** hero_fields() minus the CTAs and the image — hero_filter() has no inputs for them. */
	private static function hero_filter_fields( string $section ): array {
		return array(
			self::text( $section, 'eyebrow', 'Eyebrow', 'e.g. Est. 1974 · Marlow, UK' ),
			self::text( $section, 'title_lead', 'Heading' ),
			self::text( $section, 'title_highlight', 'Highlighted phrase' ),
			self::area_field( $section, 'lede', 'Subheading' ),
		);
	}

	/** hero_filter_fields() again, minus the eyebrow's placeholder — a legal page has no founding year to suggest. */
	private static function legal_hero_fields( string $section ): array {
		return array(
			self::text( $section, 'eyebrow', 'Eyebrow' ),
			self::text( $section, 'title_lead', 'Heading' ),
			self::text( $section, 'title_highlight', 'Highlighted phrase' ),
			self::area_field( $section, 'lede', 'Subheading' ),
		);
	}

	private static function cta_fields( string $section ): array {
		return array(
			self::text( $section, 'heading', 'Heading' ),
			self::area_field( $section, 'lede', 'Body' ),
			self::text( $section, 'cta_label', 'Button label' ),
			self::url( $section, 'cta_href', 'Button link' ),
		);
	}

	/** A booking slot: its own heading, plus the LatePoint shortcode that fills it. */
	private static function booking_slot_fields( string $section ): array {
		return array(
			self::text( $section, 'eyebrow', 'Eyebrow' ),
			self::text( $section, 'heading', 'Heading' ),
			self::text( $section, 'shortcode', 'Shortcode' ),
			self::text( $section, 'link_label', 'Link label' ),
			self::url( $section, 'link_href', 'Link' ),
		);
	}

	/** One clause of a legal document, as a repeater row. */
	private static function legal_loop_cells(): array {
		return array(
			self::cell( 'heading', 'text', 'Heading' ),
			self::cell( 'body', 'textarea', 'Text (leave a blank line between paragraphs)', array( 'rows' => 8 ) ),
		);
	}

	/**
	 * Icon choices for the Home hero's tiles — Sections::TILE_ICONS' own keys.
	 */
	private const TILE_ICON_OPTIONS = array(
		''         => 'No icon',
		'join'     => 'Join / membership',
		'tour'     => 'Tour / explore',
		'fixtures' => 'Fixtures / calendar',
		'contact'  => 'Contact / email',
	);

	/** The options for a tier's product picker — "Not connected" first, then every price the shop offers. */
	private static function price_options( ?Blueworx_Clubhouse_Products $products ): array {
		$options = array( '' => 'Not connected — use the price typed above' );
		if ( null === $products ) {
			return $options;
		}
		foreach ( $products->prices() as $price ) {
			$options[ (string) $price['id'] ] = (string) $price['label'];
		}
		return $options;
	}

	/** The note under the tiers panel, which depends on whether there is a shop to connect to. */
	private static function tiers_note( ?Blueworx_Clubhouse_Products $products ): string {
		if ( null === $products ) {
			return 'Connect a tier to a product to take payment for it. No shop is installed yet, so tiers show the price you type here and their button goes to the contact page.';
		}
		if ( array() === $products->prices() ) {
			return 'Connect a tier to a product to take payment for it. Your shop has no products yet — add one, and it will appear here.';
		}
		return 'Connect a tier to a product and the card shows what that product charges, with its button going straight to checkout. Change the price in the shop and this page follows. A tier left unconnected shows the price you type here. Give a tier an annual price as well as a monthly one and your pages get a Monthly / Annual switch above the tiers; a tier priced only one way simply shows that price and says so.';
	}

	// ---------------------------------------------------------------------
	// Home's three tabs.
	// ---------------------------------------------------------------------

	/**
	 * @param array<string,bool> $hideable
	 */
	private static function home_tabs( array $hideable, ?Blueworx_Clubhouse_Products $products ): array {
		return array(
			array( 'id' => 'hero', 'label' => 'Top of the page', 'panels' => array(
				self::panel( $hideable, 'home', 'hero', 'Hero', self::hero_fields( 'hero' ) ),
				self::panel( $hideable, 'home', 'quick_tiles', 'Quick tiles',
					array(
						self::repeater( 'quick_tiles', 'Tiles', array(
							self::cell( 'label', 'text', 'Label' ),
							self::cell( 'href', 'text', 'Link', array( 'format' => 'url' ) ),
							self::cell( 'icon', 'select', 'Icon', array( 'options' => self::options( self::TILE_ICON_OPTIONS ) ) ),
						) ),
					),
					'These render as the icon cards at the foot of the Home hero.'
				),
				self::panel( $hideable, 'home', 'ticker', 'Ticker',
					array(
						self::repeater( 'ticker', 'Messages', array(
							self::cell( 'text', 'text', 'Message' ),
						) ),
					)
				),
			) ),
			array( 'id' => 'club', 'label' => 'The club', 'panels' => array(
				self::panel( $hideable, 'home', 'sports', 'Sports grid', array(
					self::text( 'sports', 'heading', 'Heading' ),
					self::area_field( 'sports', 'eyebrow', 'Intro' ),
					self::copytext( 'sports', 'Sports grid', 'The sports shown here are managed in one place — the Sports collection.' ),
				) ),
				self::panel( $hideable, 'home', 'clubhouse', 'Clubhouse band', array(
					self::text( 'clubhouse', 'eyebrow', 'Eyebrow' ),
					self::text( 'clubhouse', 'heading', 'Heading' ),
					self::media( 'clubhouse', 'image', 'Image' ),
					self::text( 'clubhouse', 'cta_label', 'Button label' ),
					self::url( 'clubhouse', 'cta_href', 'Button link' ),
				) ),
				self::panel( $hideable, 'home', 'membership', 'Membership tiers', array(
					self::text( 'membership', 'eyebrow', 'Eyebrow' ),
					self::text( 'membership', 'heading', 'Heading' ),
					self::area_field( 'membership', 'lede', 'Intro' ),
					self::text( 'membership', 'cta_label', 'Button label' ),
					self::url( 'membership', 'cta_href', 'Button link' ),
					self::copytext( 'membership', 'Membership tiers', 'Tiers are managed in one place — the Membership page.' ),
				) ),
				self::panel( $hideable, 'home', 'activity', 'Activity tabs', array(
					self::copytext( 'activity', 'Activity tabs', 'Built from each sport’s latest fixtures, results and standings.' ),
				) ),
			) ),
			array( 'id' => 'community', 'label' => 'News and community', 'panels' => array(
				self::panel( $hideable, 'home', 'news', 'News', array(
					self::text( 'news', 'eyebrow', 'Eyebrow' ),
					self::text( 'news', 'heading', 'Heading' ),
					self::repeater( 'news', 'Articles', array(
						self::cell( 'tag', 'text', 'Tag' ),
						self::cell( 'date', 'text', 'Date' ),
						self::cell( 'title', 'text', 'Title' ),
						self::cell( 'image', 'media', 'Image' ),
					) ),
				) ),
				self::panel( $hideable, 'home', 'social_feed', 'Social feed', array(
					self::select( 'social_feed', 'platform', 'Platform', array( 'facebook' => 'Facebook', 'instagram' => 'Instagram' ) ),
					self::text( 'social_feed', 'heading', 'Heading', 'e.g. Latest from the club' ),
					self::area_field( 'social_feed', 'lede', 'Blurb', 2 ),
					self::select( 'social_feed', 'count', 'How many posts to show', array( '3' => '3', '6' => '6', '9' => '9' ) ),
					self::repeater( 'social_feed', 'Posts', array(
						self::cell( 'href', 'text', 'Post link', array( 'format' => 'url' ) ),
						self::cell( 'caption', 'text', 'Caption' ),
					) ),
				),
					'Switched off until you turn it on under Setup → Visibility. Paste the link to each post you want shown — the section stays off the page until at least one is pasted, because a heading over an empty space reads as a broken site. Connecting Facebook or Instagram directly, so posts arrive on their own, comes later.'
				),
				self::panel( $hideable, 'home', 'info', 'Find us details', array(
					self::repeater( 'info', 'Columns', array(
						self::cell( 'label', 'text', 'Label' ),
						self::cell( 'lines', 'textarea', 'Lines (one per line)', array( 'rows' => 3 ) ),
						self::cell( 'link_label', 'text', 'Link label' ),
						self::cell( 'link_href', 'text', 'Link href', array( 'format' => 'url' ) ),
					) ),
				) ),
				self::panel( $hideable, 'home', 'sponsors', 'Sponsors', array(
					self::copytext( 'sponsors', 'Sponsors', 'Sponsors are managed as a collection.' ),
				) ),
				self::panel( $hideable, 'home', 'social', 'Social', array(
					self::text( 'social', 'heading', 'Heading' ),
					self::area_field( 'social', 'lede', 'Lede' ),
				),
					'Profile links come from Site setup → Branding.'
				),
			) ),
		);
	}

	/**
	 * @return array<string,array{label:string,tabs:array<int,array{id:string,label:string,panels:array}>}>
	 */
	public static function areas( ?Blueworx_Clubhouse_Products $products = null ): array {
		$h = self::hideable_panels();

		$areas = array(
			'global' => array(
				'label' => 'Global content',
				'tabs'  => array(
					array( 'id' => 'content', 'label' => 'Content', 'panels' => array(
						self::panel( $h, 'global', 'header', 'Header',
							array(
								self::text( 'header', 'join', 'Menu CTA label', 'e.g. Join the Club' ),
								self::url( 'header', 'join_href', 'Menu CTA link' ),
								self::toggle( 'header', 'banner_show', 'Show announcement bar', true ),
								self::text( 'header', 'banner', 'Announcement text' ),
								self::url( 'header', 'banner_href', 'Announcement link' ),
							),
							'Shown on every page. Logo and club name come from Site setup → Branding.',
							'Every page · Top'
						),
						self::panel( $h, 'global', 'footer', 'Footer',
							array(
								self::area_field( 'footer', 'tagline', 'About blurb', 4 ),
								self::text( 'footer', 'newsletter_heading', 'Newsletter heading' ),
								self::area_field( 'footer', 'newsletter_lede', 'Newsletter blurb', 2 ),
								self::text( 'footer', 'newsletter_shortcode', 'Newsletter signup shortcode (SureForms)' ),
							),
							'Shown on every page. Contact details and social links come from Site setup → Branding. Paste a SureForms shortcode to collect newsletter signups — without one the signup box is hidden, because a box that takes an address and does nothing with it is worse than none.',
							'Every page · Foot'
						),
						self::panel( $h, 'global', 'welcome', 'Welcome pack',
							array(
								self::text( 'welcome', 'heading', 'Heading', 'e.g. Welcome to the club' ),
								self::area_field( 'welcome', 'body', 'Welcome pack', 8 ),
								self::text( 'welcome', 'link_label', 'Link label', 'e.g. Read the full handbook' ),
								self::url( 'welcome', 'link_href', 'Link' ),
							),
							'Shown to a member on their account dashboard once they have joined — the practical things a new member needs: how to get in, where to park, who to ask. Leave the body empty and nothing is shown at all.',
							'Member dashboard'
						),
						self::panel( $h, 'global', 'cookies', 'Cookie notice',
							array(
								self::toggle( 'cookies', 'show', 'Show the cookie notice', true ),
								self::area_field( 'cookies', 'text', 'Notice text', 3 ),
								self::text( 'cookies', 'link_label', 'Link label' ),
								self::url( 'cookies', 'link_href', 'Link' ),
								self::text( 'cookies', 'dismiss', 'Dismiss button label' ),
							),
							'Shown once per visitor, at the foot of every page, until they dismiss it. If you run a dedicated consent plugin, switch this off and let that one do the job.',
							'Every page · Foot'
						),
					) ),
				),
			),

			'home' => array( 'label' => 'Home', 'tabs' => self::home_tabs( $h, $products ) ),

			'about' => array( 'label' => 'About', 'tabs' => self::content_tab( array(
				self::panel( $h, 'about', 'hero', 'Hero', self::hero_fields( 'hero' ) ),
				self::panel( $h, 'about', 'history', 'History', array(
					self::text( 'history', 'heading', 'Heading' ),
					self::repeater( 'history', 'Milestones', array(
						self::cell( 'year', 'text', 'Year' ),
						self::cell( 'title', 'text', 'Title' ),
						self::cell( 'desc', 'textarea', 'Description', array( 'rows' => 3 ) ),
					) ),
				) ),
				self::panel( $h, 'about', 'values', 'Values', array(
					self::repeater( 'values', 'Values', array(
						self::cell( 'title', 'text', 'Title' ),
						self::cell( 'description', 'textarea', 'Description', array( 'rows' => 3 ) ),
					) ),
				) ),
				self::panel( $h, 'about', 'facilities', 'Facilities', array(
					self::text( 'facilities', 'eyebrow', 'Eyebrow' ),
					self::text( 'facilities', 'heading', 'Heading' ),
					self::media( 'facilities', 'image', 'Image' ),
					self::text( 'facilities', 'cta_label', 'Button label' ),
					self::url( 'facilities', 'cta_href', 'Button link' ),
				),
					'This renders as a single image band, not a list of facilities.'
				),
				self::panel( $h, 'about', 'committee', 'Committee', array(
					self::copytext( 'committee', 'Committee', 'The committee is managed in one place — the People collection.' ),
				) ),
				self::panel( $h, 'about', 'get_involved', 'Get involved', array(
					self::repeater( 'get_involved', 'Ways to help', array(
						self::cell( 'title', 'text', 'Title' ),
						self::cell( 'description', 'textarea', 'Description', array( 'rows' => 3 ) ),
					) ),
				) ),
				self::panel( $h, 'about', 'cta', 'Call to action', self::cta_fields( 'cta' ) ),
			) ) ),

			'membership' => array( 'label' => 'Membership', 'tabs' => self::content_tab( array(
				self::panel( $h, 'membership', 'hero', 'Hero', self::hero_fields( 'hero' ) ),
				self::panel( $h, 'membership', 'why', 'Why join', array(
					self::text( 'why', 'heading', 'Heading' ),
					self::area_field( 'why', 'eyebrow', 'Intro' ),
					self::repeater( 'why', 'Benefits', array(
						self::cell( 'title', 'text', 'Title' ),
						self::cell( 'description', 'textarea', 'Description', array( 'rows' => 3 ) ),
					) ),
				) ),
				self::panel( $h, 'membership', 'tiers', 'Tiers', array(
					self::repeater( 'tiers', 'Tiers', array(
						self::cell( 'name', 'text', 'Name' ),
						self::cell( 'price', 'text', 'Price' ),
						self::cell( 'period', 'text', 'Period' ),
						self::cell( 'price_annual', 'text', 'Annual price' ),
						self::cell( 'features', 'textarea', 'Features (one per line)', array( 'rows' => 4 ) ),
						self::cell( 'featured', 'toggle', 'Most popular', array( 'default' => false ) ),
						self::cell( 'cta_label', 'text', 'CTA label' ),
						self::cell( 'price_id', 'select', 'Sells', array( 'options' => self::options( self::price_options( $products ) ) ) ),
						self::cell( 'price_id_annual', 'select', 'Sells (annual)', array( 'options' => self::options( self::price_options( $products ) ) ) ),
					) ),
				),
					self::tiers_note( $products )
				),
				self::panel( $h, 'membership', 'detail', 'Included / excluded', array(
					self::repeater( 'detail', 'Points', array(
						self::cell( 'text', 'text', 'Text' ),
						self::cell( 'included', 'toggle', 'Included', array( 'default' => false ) ),
					) ),
				) ),
				self::panel( $h, 'membership', 'steps', 'How to join', array(
					self::repeater( 'steps', 'Steps', array(
						self::cell( 'title', 'text', 'Title' ),
						self::cell( 'description', 'textarea', 'Description', array( 'rows' => 3 ) ),
					) ),
				) ),
				self::panel( $h, 'membership', 'faq', 'FAQ', array(
					self::repeater( 'faq', 'Questions', array(
						self::cell( 'question', 'text', 'Question' ),
						self::cell( 'answer', 'textarea', 'Answer', array( 'rows' => 3 ) ),
					) ),
				) ),
				self::panel( $h, 'membership', 'cta', 'Call to action', self::cta_fields( 'cta' ) ),
			) ) ),

			'contact' => array( 'label' => 'Contact', 'tabs' => self::content_tab( array(
				self::panel( $h, 'contact', 'hero', 'Hero', self::hero_fields( 'hero' ) ),
				self::panel( $h, 'contact', 'form', 'Contact form', array(
					self::text( 'form', 'eyebrow', 'Eyebrow' ),
					self::text( 'form', 'heading', 'Heading' ),
					self::text( 'form', 'shortcode', 'Form shortcode (SureForms)' ),
					self::text( 'form', 'submit_label', 'Submit button label' ),
					self::text( 'form', 'info_heading', 'Details heading' ),
					self::area_field( 'form', 'address', 'Club address (one line per line)' ),
					self::text( 'form', 'email', 'Club email' ),
					self::text( 'form', 'phone', 'Club phone' ),
					self::media( 'form', 'map_image', 'Map or location image' ),
				),
					'Paste a SureForms shortcode to take real enquiries. Until you do, the form here is a demo that does not send anywhere — so visitors are shown the club email and phone instead. The details beside it are the real club address, email and phone.'
				),
				self::panel( $h, 'contact', 'directory', 'Directory', array(
					self::copytext( 'directory', 'Directory', 'The directory is managed in one place — the People collection.' ),
				) ),
				self::panel( $h, 'contact', 'social', 'Social', array(
					self::text( 'social', 'heading', 'Heading' ),
				),
					'Profile links come from Site setup → Branding.'
				),
			) ) ),

			'login' => array( 'label' => 'Log in', 'tabs' => self::content_tab( array(
				self::panel( $h, 'login', 'form', 'Login form', array(
					self::text( 'form', 'heading', 'Heading' ),
					self::area_field( 'form', 'lede', 'Helper text' ),
				) ),
			) ) ),

			'news' => array( 'label' => 'News', 'tabs' => self::content_tab( array(
				self::panel( $h, 'news', 'head', 'Page head', array(
					self::text( 'head', 'eyebrow', 'Eyebrow' ),
					self::text( 'head', 'title_lead', 'Heading, first part' ),
					self::text( 'head', 'title_highlight', 'Heading, highlighted part' ),
					self::area_field( 'head', 'lede', 'Standfirst' ),
				),
					'The stories themselves are ordinary WordPress posts — write them under Posts.'
				),
				self::panel( $h, 'news', 'featured', 'Featured story', array(
					self::copytext( 'featured', 'Featured story', 'The featured story is whichever post is newest. Publish a post and it takes the top spot.' ),
				) ),
				self::panel( $h, 'news', 'posts', 'Stories', array(
					self::copytext( 'posts', 'Stories', 'Club news is written as ordinary WordPress posts, under Posts.' ),
				) ),
			) ) ),

			'sports' => array( 'label' => 'Sports', 'tabs' => self::content_tab( array(
				self::panel( $h, 'sports', 'hero', 'Hero', self::hero_filter_fields( 'hero' ) ),
				self::panel( $h, 'sports', 'directory', 'Sports directory', array(
					self::copytext( 'directory', 'Sports directory', 'Sports are managed in one place — the Sports collection.' ),
				) ),
				self::panel( $h, 'sports', 'cta', 'Call to action', self::cta_fields( 'cta' ) ),
			) ) ),

			'teams' => array( 'label' => 'Teams', 'tabs' => self::content_tab( array(
				self::panel( $h, 'teams', 'hero', 'Hero', self::hero_filter_fields( 'hero' ) ),
				self::panel( $h, 'teams', 'directory', 'Teams directory', array(
					self::copytext( 'directory', 'Teams directory', 'Teams are managed in one place — the Teams collection.' ),
				) ),
				self::panel( $h, 'teams', 'cta', 'Call to action', self::cta_fields( 'cta' ) ),
			) ) ),

			'events' => array( 'label' => 'Events', 'tabs' => self::content_tab( array(
				self::panel( $h, 'events', 'hero', 'Hero', self::hero_filter_fields( 'hero' ) ),
				self::panel( $h, 'events', 'upcoming', 'Upcoming events', array(
					self::copytext( 'upcoming', 'Upcoming events', 'Upcoming events are managed in one place — the Events collection.' ),
				) ),
				self::panel( $h, 'events', 'past', 'Past events', array(
					self::copytext( 'past', 'Past events', 'Derived from events marked past.' ),
				) ),
				self::panel( $h, 'events', 'cta', 'Call to action', self::cta_fields( 'cta' ) ),
			) ) ),

			'calendar' => array( 'label' => 'Calendar', 'tabs' => self::content_tab( array(
				self::panel( $h, 'calendar', 'hero', 'Hero', self::hero_filter_fields( 'hero' ) ),
				self::panel( $h, 'calendar', 'booking', 'Bookings', self::booking_slot_fields( 'booking' ),
					'The Bookings calendar, above the fixtures — this is the "when" half of booking. The link beside the heading goes to the Bookings page, which is the what, where and who; a member seeing free slots with no idea what they are is how this got raised.'
				),
				self::panel( $h, 'calendar', 'schedule', 'Schedule', array(
					self::text( 'schedule', 'heading', 'Heading' ),
					self::area_field( 'schedule', 'eyebrow', 'Intro' ),
					self::copytext( 'schedule', 'Schedule', 'Built from each sport’s fixtures and results.' ),
				) ),
				self::panel( $h, 'calendar', 'cta', 'Call to action', self::cta_fields( 'cta' ) ),
			) ) ),

			// Booking is LatePoint's page. drop_unavailable() removes it entirely
			// — here and from the visibility inventory — when LatePoint is not
			// live, so an owner is never shown fields for a page that cannot
			// render. See Page_Map::is_available().
			'booking' => array( 'label' => 'Bookings', 'tabs' => self::content_tab( array(
				self::panel( $h, 'booking', 'hero', 'Hero', self::hero_fields( 'hero' ) ),
				self::panel( $h, 'booking', 'services', 'Sessions and services', self::booking_slot_fields( 'services' ),
					'Ships with the Bookings services list. Switch the section off under Site setup → Visibility to drop it.'
				),
				self::panel( $h, 'booking', 'locations', 'Courts and locations', self::booking_slot_fields( 'locations' ),
					'Ships with the Bookings locations list. Switch the section off under Site setup → Visibility to drop it.'
				),
				self::panel( $h, 'booking', 'agents', 'Coaches and staff', self::booking_slot_fields( 'agents' ),
					'Ships with the Bookings agents list. Switch the section off under Site setup → Visibility to drop it.'
				),
			) ) ),

			'privacy' => array( 'label' => 'Privacy', 'tabs' => self::content_tab( array(
				self::panel( $h, 'privacy', 'hero', 'Hero', self::legal_hero_fields( 'hero' ) ),
				self::panel( $h, 'privacy', 'body', 'Policy', array(
					self::repeater( 'body', 'Sections', self::legal_loop_cells() ),
				),
					'Ships with starter wording that describes what this site actually collects. Anywhere it says ADD, only your club can answer — replace those lines before you take real sign-ups. This is a starting point, not legal advice.'
				),
			) ) ),

			'terms' => array( 'label' => 'Terms', 'tabs' => self::content_tab( array(
				self::panel( $h, 'terms', 'hero', 'Hero', self::legal_hero_fields( 'hero' ) ),
				self::panel( $h, 'terms', 'body', 'Terms', array(
					self::repeater( 'body', 'Sections', self::legal_loop_cells() ),
				),
					'Ships with starter wording. Anywhere it says ADD, only your club can answer — the payments and refunds sections matter most, and should be written before you sell anything. This is a starting point, not legal advice.'
				),
			) ) ),

			'rules' => array( 'label' => 'Club rules', 'tabs' => self::content_tab( array(
				self::panel( $h, 'rules', 'hero', 'Hero', self::legal_hero_fields( 'hero' ) ),
				self::panel( $h, 'rules', 'body', 'Rules', array(
					self::repeater( 'body', 'Sections', self::legal_loop_cells() ),
				),
					'Every section here is an example, because only your club knows its own rules — opening hours, footwear, guests, parking. Rewrite or delete the lot. This is the everyday stuff; the contract lives on the Terms tab.'
				),
			) ) ),
		);

		return self::drop_unavailable( $areas );
	}

	/**
	 * Drops a whole area when its page's integration is absent, then drops
	 * individual panels through Integrations::section_available() — the same
	 * two filters Content_Catalogue::pages() ends with, so a club without
	 * LatePoint is never offered a Bookings editor that cannot render.
	 *
	 * @param array<string,array<string,mixed>> $areas
	 * @return array<string,array<string,mixed>>
	 */
	private static function drop_unavailable( array $areas ): array {
		$out = array();
		foreach ( $areas as $key => $area ) {
			$slug = ( 'global' === $key || 'home' === $key ) ? '' : $key;
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			foreach ( $area['tabs'] as $t => $tab ) {
				$area['tabs'][ $t ]['panels'] = array_values(
					array_filter(
						$tab['panels'],
						static fn( array $panel ): bool => Blueworx_Clubhouse_Integrations::section_available( $key, (string) $panel['id'] )
					)
				);
			}
			$out[ $key ] = $area;
		}
		return $out;
	}
}
