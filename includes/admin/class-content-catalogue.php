<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pure declarative catalogue of editable page content, shaped 1:1 to the
 * visibility inventory (`Setup_Sections::inventory()` — enforced by a
 * lockstep test). Single source of truth for the Content editor: it drives
 * the editor UI and defines the `Content_Store` storage-key contract the
 * front-end renderers read (Page_Renderer's override layer).
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Catalogue {

	private static function f_text( string $key, string $label, string $ph = '' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'text', 'placeholder' => $ph );
	}
	private static function f_area( string $key, string $label, int $rows = 3, string $ph = '' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'textarea', 'rows' => $rows, 'placeholder' => $ph );
	}
	private static function f_url( string $key, string $label, string $ph = 'https://…' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'url', 'placeholder' => $ph );
	}
	private static function f_image( string $key, string $label ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'image' );
	}
	/**
	 * A switch, and what it means on a site that has never touched it.
	 *
	 * The default is declared here because the editing screen has to know it.
	 * Without it an unset switch drew as off while the page it controls was on,
	 * and saving the tab wrote that mistake back — which is how one save on the
	 * Global tab used to switch the cookie notice and announcement bar off.
	 *
	 * Kept in step with the renderer's own fallback by a test.
	 */
	private static function f_toggle( string $key, string $label, bool $default = false ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'toggle', 'default' => $default );
	}
	/**
	 * A slot for another plugin's shortcode. Unlike every other field type this
	 * one's value is rendered as HTML rather than escaped, so it is deliberately
	 * its own type — never a flag on 'text' or 'textarea', which would quietly
	 * unescape content everywhere those are used.
	 */
	private static function f_shortcode( string $key, string $label ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'shortcode' );
	}
	/** @param array<string,string> $options value => label */
	private static function f_select( string $key, string $label, array $options ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'select', 'options' => $options );
	}

	/**
	 * Icon choices for the Home hero's tiles — these values are the keys of
	 * Sections::TILE_ICONS. Any other value (or none) renders no glyph.
	 */
	private const TILE_ICON_OPTIONS = array(
		''         => 'No icon',
		'join'     => 'Join / membership',
		'tour'     => 'Tour / explore',
		'fixtures' => 'Fixtures / calendar',
		'contact'  => 'Contact / email',
	);

	/**
	 * The hero of a legal page. The same four fields every other hero has, minus
	 * the buttons and the photograph — the renderer passes those empty, so
	 * offering them here would be four controls that visibly do nothing.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function legal_hero_fields(): array {
		return array(
			self::f_text( 'eyebrow', 'Eyebrow' ),
			self::f_text( 'title_lead', 'Heading' ),
			self::f_text( 'title_highlight', 'Highlighted phrase' ),
			self::f_area( 'lede', 'Subheading' ),
		);
	}

	/**
	 * One clause of a legal document: a heading and its prose. Blank lines in
	 * the body become paragraphs; nothing else is interpreted.
	 *
	 * @return array<string,mixed>
	 */
	private static function legal_loop(): array {
		return array(
			'name'   => 'Section',
			'plural' => 'Sections',
			'fields' => array(
				self::f_text( 'heading', 'Heading' ),
				self::f_area( 'body', 'Text (leave a blank line between paragraphs)', 8 ),
			),
		);
	}

	/**
	 * Shared hero field set, used by every page's Hero section — keys map to
	 * Page_Renderer's Sections::hero() input array exactly.
	 */
	private static function hero_fields(): array {
		return array(
			self::f_text( 'eyebrow', 'Eyebrow', 'e.g. Est. 1974 · Marlow, UK' ),
			self::f_text( 'title_lead', 'Heading' ),
			self::f_text( 'title_highlight', 'Highlighted phrase' ),
			self::f_area( 'lede', 'Subheading' ),
			self::f_text( 'cta_primary', 'Primary button label' ),
			self::f_url( 'cta_primary_href', 'Primary button link' ),
			self::f_text( 'cta_secondary', 'Secondary button label' ),
			self::f_url( 'cta_secondary_href', 'Secondary button link' ),
			self::f_image( 'image', 'Background image' ),
		);
	}

	/**
	 * Filtered-hero field set, used by pages whose Hero section renders via
	 * Sections::hero_filter() (sports, teams, events, calendar) rather than the
	 * shared hero(). hero_filter() has no CTA or image inputs, so this is a
	 * strict subset of hero_fields() — offering CTA or image fields on those
	 * pages would edit fields the renderer never reads.
	 */
	private static function hero_filter_fields(): array {
		return array(
			self::f_text( 'eyebrow', 'Eyebrow', 'e.g. Est. 1974 · Marlow, UK' ),
			self::f_text( 'title_lead', 'Heading' ),
			self::f_text( 'title_highlight', 'Highlighted phrase' ),
			self::f_area( 'lede', 'Subheading' ),
		);
	}

	/**
	 * Shared "call to action" band field set — keys (heading/lede/cta_label/
	 * cta_href) match Sections::band()'s inputs; eyebrow stays hardcoded per
	 * the design spec's scoped field list.
	 */
	private static function cta_fields(): array {
		return array(
			self::f_text( 'heading', 'Heading' ),
			self::f_area( 'lede', 'Body' ),
			self::f_text( 'cta_label', 'Button label' ),
			self::f_url( 'cta_href', 'Button link' ),
		);
	}

	/**
	 * Flat lookup from a stored content address to its labels and owning tab.
	 * Keyed "{store_page}/{section_key}" — the same address Content_Store uses —
	 * so callers holding only stored data (the import preview, the images-needed
	 * notice) can name a section for a human and link to its panel.
	 *
	 * @return array<string,array{tab:string,tab_label:string,section_key:string,section_label:string}>
	 */
	public static function index(): array {
		$index = array();
		foreach ( self::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$key           = (string) $section['store_page'] . '/' . (string) $section['key'];
				$index[ $key ] = array(
					'tab'           => (string) $page['tab'],
					'tab_label'     => (string) $page['label'],
					'section_key'   => (string) $section['key'],
					'section_label' => (string) $section['label'],
				);
			}
		}
		return $index;
	}

	/**
	 * The human name for a stored content address ("Global · Hero"), or the
	 * raw address when the catalogue no longer has it. The single place this
	 * string is composed — the import preview, the applier's result rows and
	 * the images-needed notice all name sections through here.
	 *
	 * @param string $address The address in format "store_page/section_key".
	 * @return string
	 */
	public static function address_label( string $address ): string {
		$entry = self::index()[ $address ] ?? null;
		if ( null === $entry ) {
			return $address;
		}
		return $entry['tab_label'] . ' · ' . $entry['section_label'];
	}

	/**
	 * The options for a tier's product picker: "Not connected" first, then every
	 * price the shop offers. With no shop there is only the first, and the
	 * section's note explains why — an empty dropdown explains nothing.
	 *
	 * @return array<string,string>
	 */
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

	/** The note under the tiers section, which depends on whether there is a shop to connect to. */
	private static function tiers_note( ?Blueworx_Clubhouse_Products $products ): string {
		if ( null === $products ) {
			return 'Connect a tier to a product to take payment for it. No shop is installed yet, so tiers show the price you type here and their button goes to the contact page.';
		}
		if ( array() === $products->prices() ) {
			return 'Connect a tier to a product to take payment for it. Your shop has no products yet — add one, and it will appear here.';
		}
		return 'Connect a tier to a product and the card shows what that product charges, with its button going straight to checkout. Change the price in the shop and this page follows. A tier left unconnected shows the price you type here. Give a tier an annual price as well as a monthly one and your pages get a Monthly / Annual switch above the tiers; a tier priced only one way simply shows that price and says so.';
	}

	/**
	 * @param Blueworx_Clubhouse_Products|null $products The shop, when there is one.
	 *                                                   Its prices become the tier
	 *                                                   product picker's options.
	 * @return array<int,array<string,mixed>>
	 */
	public static function pages( ?Blueworx_Clubhouse_Products $products = null ): array {
		$pages = array(
			// Two tabs, not one: the header and footer appear on every page, while
			// everything else under the old combined tab was Home-only. Editing the
			// Home hero from a tab labelled "Global" read as a sitewide change.
			// Only the tab grouping moves — each section keeps its store_page, so
			// stored content addresses are unchanged and nothing needs migrating.
			array( 'tab' => 'global', 'label' => 'Global', 'sections' => array(
				array( 'key' => 'header', 'label' => 'Header', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Shown on every page. Logo and club name come from Site setup → Branding.',
					'fields' => array(
						self::f_text( 'join', 'Menu CTA label', 'e.g. Join the Club' ),
						self::f_url( 'join_href', 'Menu CTA link' ),
						self::f_toggle( 'banner_show', 'Show announcement bar', true ),
						self::f_text( 'banner', 'Announcement text' ),
						self::f_url( 'banner_href', 'Announcement link' ),
					) ),
				array( 'key' => 'footer', 'label' => 'Footer', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Shown on every page. Contact details and social links come from Site setup → Branding. Paste a SureForms shortcode to collect newsletter signups — without one the signup box is hidden, because a box that takes an address and does nothing with it is worse than none.',
					'fields' => array(
						self::f_area( 'tagline', 'About blurb', 4 ),
						self::f_text( 'newsletter_heading', 'Newsletter heading' ),
						self::f_area( 'newsletter_lede', 'Newsletter blurb', 2 ),
						self::f_shortcode( 'newsletter_shortcode', 'Newsletter signup shortcode (SureForms)' ),
					) ),
				array( 'key' => 'welcome', 'label' => 'Welcome pack', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Shown to a member on their account dashboard once they have joined — the practical things a new member needs: how to get in, where to park, who to ask. Leave the body empty and nothing is shown at all. It renders in the dashboard\'s own plain styling rather than the club look, because that page belongs to the shop.',
					'fields' => array(
						self::f_text( 'heading', 'Heading', 'e.g. Welcome to the club' ),
						self::f_area( 'body', 'Welcome pack', 8, 'A blank line starts a new paragraph.' ),
						self::f_text( 'link_label', 'Link label', 'e.g. Read the full handbook' ),
						self::f_url( 'link_href', 'Link' ),
					) ),
				array( 'key' => 'cookies', 'label' => 'Cookie notice', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Shown once per visitor, at the foot of every page, until they dismiss it. It says what this site uses cookies for — it does not withhold anything, because the shop and its payment provider set theirs as soon as those pages load. If you run a dedicated consent plugin, switch this off and let that one do the job.',
					'fields' => array(
						self::f_toggle( 'show', 'Show the cookie notice', true ),
						self::f_area( 'text', 'Notice text', 3 ),
						self::f_text( 'link_label', 'Link label' ),
						self::f_url( 'link_href', 'Link' ),
						self::f_text( 'dismiss', 'Dismiss button label' ),
					) ),
			) ),
			array( 'tab' => 'home', 'label' => 'Home', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'home', 'fields' => self::hero_fields() ),
				array( 'key' => 'quick_tiles', 'label' => 'Quick tiles', 'type' => 'loop', 'store_page' => 'home',
					'note' => 'These render as the icon cards at the foot of the Home hero.',
					'loop' => array( 'name' => 'Tile', 'plural' => 'Tiles', 'fields' => array( self::f_text( 'label', 'Label' ), self::f_url( 'href', 'Link' ), self::f_select( 'icon', 'Icon', self::TILE_ICON_OPTIONS ) ) ) ),
				array( 'key' => 'ticker', 'label' => 'Ticker', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Message', 'plural' => 'Messages', 'fields' => array( self::f_text( 'text', 'Message' ) ) ) ),
				array( 'key' => 'sports', 'label' => 'Sports grid', 'type' => 'linkout', 'store_page' => 'home',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'eyebrow', 'Intro' ) ),
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_sport', 'label' => 'Manage sports', 'text' => 'The sports shown here are managed in one place — the Sports collection.' ) ),
				array( 'key' => 'clubhouse', 'label' => 'Clubhouse band', 'type' => 'fields', 'store_page' => 'home',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ), self::f_image( 'image', 'Image' ), self::f_text( 'cta_label', 'Button label' ), self::f_url( 'cta_href', 'Button link' ) ) ),
				array( 'key' => 'membership', 'label' => 'Membership tiers', 'type' => 'linkout', 'store_page' => 'home',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ), self::f_area( 'lede', 'Intro' ), self::f_text( 'cta_label', 'Button label' ), self::f_url( 'cta_href', 'Button link' ) ),
					'link' => array( 'kind' => 'section', 'tab' => 'membership', 'sec' => 'tiers', 'label' => 'Edit tiers', 'text' => 'Tiers are managed in one place — the Membership page.' ) ),
				array( 'key' => 'activity', 'label' => 'Activity tabs', 'type' => 'auto', 'store_page' => 'home',
					'auto' => array( 'text' => 'Built from each sport’s latest fixtures, results and standings.', 'cpt' => 'clubhouse_event' ) ),
				array( 'key' => 'news', 'label' => 'News', 'type' => 'loop', 'store_page' => 'home',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ) ),
					'loop' => array( 'name' => 'Article', 'plural' => 'Articles', 'fields' => array( self::f_text( 'tag', 'Tag' ), self::f_text( 'date', 'Date' ), self::f_text( 'title', 'Title' ), self::f_image( 'image', 'Image' ) ) ) ),
				array( 'key' => 'info', 'label' => 'Find us details', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Column', 'plural' => 'Columns', 'fields' => array( self::f_text( 'label', 'Label' ), self::f_area( 'lines', 'Lines (one per line)' ), self::f_text( 'link_label', 'Link label' ), self::f_url( 'link_href', 'Link href' ) ) ) ),
				array( 'key' => 'sponsors', 'label' => 'Sponsors', 'type' => 'linkout', 'store_page' => 'home',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_sponsor', 'label' => 'Manage sponsors', 'text' => 'Sponsors are managed as a collection.' ) ),
				array( 'key' => 'social', 'label' => 'Social', 'type' => 'fields', 'store_page' => 'home',
					'note' => 'Profile links come from Site setup → Branding.',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'lede', 'Lede' ) ) ),
			) ),
			array( 'tab' => 'about', 'label' => 'About', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'about', 'fields' => self::hero_fields() ),
				array( 'key' => 'history', 'label' => 'History', 'type' => 'loop', 'store_page' => 'about',
					'fields' => array( self::f_text( 'heading', 'Heading' ) ),
					'loop' => array( 'name' => 'Milestone', 'plural' => 'Milestones', 'fields' => array( self::f_text( 'year', 'Year' ), self::f_text( 'title', 'Title' ), self::f_area( 'desc', 'Description' ) ) ) ),
				array( 'key' => 'values', 'label' => 'Values', 'type' => 'loop', 'store_page' => 'about',
					'loop' => array( 'name' => 'Value', 'plural' => 'Values', 'fields' => array( self::f_text( 'title', 'Title' ), self::f_area( 'description', 'Description' ) ) ) ),
				array( 'key' => 'facilities', 'label' => 'Facilities', 'type' => 'fields', 'store_page' => 'about',
					'note' => 'This renders as a single image band, not a list of facilities.',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ), self::f_image( 'image', 'Image' ), self::f_text( 'cta_label', 'Button label' ), self::f_url( 'cta_href', 'Button link' ) ) ),
				array( 'key' => 'committee', 'label' => 'Committee', 'type' => 'linkout', 'store_page' => 'about',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_person', 'label' => 'Manage people', 'text' => 'The committee is managed in one place — the People collection.' ) ),
				array( 'key' => 'get_involved', 'label' => 'Get involved', 'type' => 'loop', 'store_page' => 'about',
					'loop' => array( 'name' => 'Way to help', 'plural' => 'Ways to help', 'fields' => array( self::f_text( 'title', 'Title' ), self::f_area( 'description', 'Description' ) ) ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'about', 'fields' => self::cta_fields() ),
			) ),
			array( 'tab' => 'membership', 'label' => 'Membership', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'membership', 'fields' => self::hero_fields() ),
				array( 'key' => 'why', 'label' => 'Why join', 'type' => 'loop', 'store_page' => 'membership',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'eyebrow', 'Intro' ) ),
					'loop' => array( 'name' => 'Benefit', 'plural' => 'Benefits', 'fields' => array( self::f_text( 'title', 'Title' ), self::f_area( 'description', 'Description' ) ) ) ),
				array( 'key' => 'tiers', 'label' => 'Tiers', 'type' => 'loop', 'store_page' => 'membership',
					'note' => self::tiers_note( $products ),
					'loop' => array( 'name' => 'Tier', 'plural' => 'Tiers', 'fields' => array(
						self::f_text( 'name', 'Name' ),
						self::f_text( 'price', 'Price' ),
						self::f_text( 'period', 'Period' ),
						self::f_text( 'price_annual', 'Annual price' ),
						self::f_area( 'features', 'Features (one per line)', 4 ),
						self::f_toggle( 'featured', 'Most popular' ),
						self::f_text( 'cta_label', 'CTA label' ),
						self::f_select( 'price_id', 'Sells', self::price_options( $products ) ),
						self::f_select( 'price_id_annual', 'Sells (annual)', self::price_options( $products ) ),
					) ) ),
				array( 'key' => 'detail', 'label' => 'Included / excluded', 'type' => 'loop', 'store_page' => 'membership',
					'loop' => array( 'name' => 'Point', 'plural' => 'Points', 'fields' => array( self::f_text( 'text', 'Text' ), self::f_toggle( 'included', 'Included' ) ) ) ),
				array( 'key' => 'steps', 'label' => 'How to join', 'type' => 'loop', 'store_page' => 'membership',
					'loop' => array( 'name' => 'Step', 'plural' => 'Steps', 'fields' => array( self::f_text( 'title', 'Title' ), self::f_area( 'description', 'Description' ) ) ) ),
				array( 'key' => 'faq', 'label' => 'FAQ', 'type' => 'loop', 'store_page' => 'membership',
					'loop' => array( 'name' => 'Question', 'plural' => 'Questions', 'fields' => array( self::f_text( 'question', 'Question' ), self::f_area( 'answer', 'Answer' ) ) ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'membership', 'fields' => self::cta_fields() ),
			) ),
			array( 'tab' => 'contact', 'label' => 'Contact', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'contact', 'fields' => self::hero_fields() ),
				array( 'key' => 'form', 'label' => 'Contact form', 'type' => 'fields', 'store_page' => 'contact',
					'note' => 'Paste a SureForms shortcode to take real enquiries. Until you do, the form here is a demo that does not send anywhere — so visitors are shown the club email and phone instead. The details beside it are the real club address, email and phone.',
					'fields' => array(
						self::f_text( 'eyebrow', 'Eyebrow' ),
						self::f_text( 'heading', 'Heading' ),
						self::f_shortcode( 'shortcode', 'Form shortcode (SureForms)' ),
						self::f_text( 'submit_label', 'Submit button label' ),
						self::f_text( 'info_heading', 'Details heading' ),
						self::f_area( 'address', 'Club address (one line per line)' ),
						self::f_text( 'email', 'Club email' ),
						self::f_text( 'phone', 'Club phone' ),
						self::f_image( 'map_image', 'Map or location image' ),
					) ),
				array( 'key' => 'directory', 'label' => 'Directory', 'type' => 'linkout', 'store_page' => 'contact',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_person', 'label' => 'Manage people', 'text' => 'The directory is managed in one place — the People collection.' ) ),
				array( 'key' => 'social', 'label' => 'Social', 'type' => 'fields', 'store_page' => 'contact',
					'note' => 'Profile links come from Site setup → Branding.',
					'fields' => array( self::f_text( 'heading', 'Heading' ) ) ),
			) ),
			array( 'tab' => 'login', 'label' => 'Log in', 'sections' => array(
				array( 'key' => 'form', 'label' => 'Login form', 'type' => 'fields', 'store_page' => 'login',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'lede', 'Helper text' ) ) ),
			) ),
			array( 'tab' => 'news', 'label' => 'News', 'sections' => array(
				array( 'key' => 'head', 'label' => 'Page head', 'type' => 'fields', 'store_page' => 'news',
					'note' => 'The stories themselves are ordinary WordPress posts — write them under Posts.',
					'fields' => array(
						self::f_text( 'eyebrow', 'Eyebrow' ),
						self::f_text( 'title_lead', 'Heading, first part' ),
						self::f_text( 'title_highlight', 'Heading, highlighted part' ),
						self::f_area( 'lede', 'Standfirst' ),
					) ),
				array( 'key' => 'featured', 'label' => 'Featured story', 'type' => 'linkout', 'store_page' => 'news',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'post', 'label' => 'Write a story', 'text' => 'The featured story is whichever post is newest. Publish a post and it takes the top spot.' ) ),
				array( 'key' => 'posts', 'label' => 'Stories', 'type' => 'linkout', 'store_page' => 'news',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'post', 'label' => 'Write a story', 'text' => 'Club news is written as ordinary WordPress posts, under Posts.' ) ),
			) ),
			array( 'tab' => 'sports', 'label' => 'Sports', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'sports', 'fields' => self::hero_filter_fields() ),
				array( 'key' => 'directory', 'label' => 'Sports directory', 'type' => 'linkout', 'store_page' => 'sports',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_sport', 'label' => 'Manage sports', 'text' => 'Sports are managed in one place — the Sports collection.' ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'sports', 'fields' => self::cta_fields() ),
			) ),
			array( 'tab' => 'teams', 'label' => 'Teams', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'teams', 'fields' => self::hero_filter_fields() ),
				array( 'key' => 'directory', 'label' => 'Teams directory', 'type' => 'linkout', 'store_page' => 'teams',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_team', 'label' => 'Manage teams', 'text' => 'Teams are managed in one place — the Teams collection.' ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'teams', 'fields' => self::cta_fields() ),
			) ),
			array( 'tab' => 'events', 'label' => 'Events', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'events', 'fields' => self::hero_filter_fields() ),
				array( 'key' => 'upcoming', 'label' => 'Upcoming events', 'type' => 'linkout', 'store_page' => 'events',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_event', 'label' => 'Manage events', 'text' => 'Upcoming events are managed in one place — the Events collection.' ) ),
				array( 'key' => 'past', 'label' => 'Past events', 'type' => 'auto', 'store_page' => 'events',
					'auto' => array( 'text' => 'Derived from events marked past.', 'cpt' => 'clubhouse_event' ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'events', 'fields' => self::cta_fields() ),
			) ),
			array( 'tab' => 'calendar', 'label' => 'Calendar', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'calendar', 'fields' => self::hero_filter_fields() ),
				array( 'key' => 'booking', 'label' => 'Bookings', 'type' => 'fields', 'store_page' => 'calendar',
					'note' => 'The Bookings calendar, above the fixtures — this is the "when" half of booking. The link beside the heading goes to the Bookings page, which is the what, where and who; a member seeing free slots with no idea what they are is how this got raised.',
					'fields' => self::booking_slot_fields() ),
				array( 'key' => 'schedule', 'label' => 'Schedule', 'type' => 'fields', 'store_page' => 'calendar',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'eyebrow', 'Intro' ) ),
					'auto' => array( 'text' => 'Built from each sport’s fixtures and results.', 'cpt' => 'clubhouse_fixture' ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'calendar', 'fields' => self::cta_fields() ),
			) ),
			// Booking is LatePoint's page. It is dropped entirely — here and from the
			// visibility inventory — when LatePoint is not live, so an owner is never
			// shown fields for a page that cannot render. See Page_Map::available().
			array( 'tab' => 'booking', 'label' => 'Bookings', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'booking', 'fields' => self::hero_fields() ),
				array( 'key' => 'services', 'label' => 'Sessions and services', 'type' => 'fields', 'store_page' => 'booking',
					'note' => 'Ships with the Bookings services list. Switch the section off under Site setup → Visibility to drop it.',
					'fields' => self::booking_slot_fields() ),
				array( 'key' => 'locations', 'label' => 'Courts and locations', 'type' => 'fields', 'store_page' => 'booking',
					'note' => 'Ships with the Bookings locations list. Switch the section off under Site setup → Visibility to drop it.',
					'fields' => self::booking_slot_fields() ),
				array( 'key' => 'agents', 'label' => 'Coaches and staff', 'type' => 'fields', 'store_page' => 'booking',
					'note' => 'Ships with the Bookings agents list. Switch the section off under Site setup → Visibility to drop it.',
					'fields' => self::booking_slot_fields() ),
			) ),
			array( 'tab' => 'privacy', 'label' => 'Privacy', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'privacy', 'fields' => self::legal_hero_fields() ),
				array( 'key' => 'body', 'label' => 'Policy', 'type' => 'loop', 'store_page' => 'privacy',
					'note' => 'Ships with starter wording that describes what this site actually collects. Anywhere it says ADD, only your club can answer — replace those lines before you take real sign-ups. This is a starting point, not legal advice.',
					'loop' => self::legal_loop() ),
			) ),
			array( 'tab' => 'terms', 'label' => 'Terms', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'terms', 'fields' => self::legal_hero_fields() ),
				array( 'key' => 'body', 'label' => 'Terms', 'type' => 'loop', 'store_page' => 'terms',
					'note' => 'Ships with starter wording. Anywhere it says ADD, only your club can answer — the payments and refunds sections matter most, and should be written before you sell anything. This is a starting point, not legal advice.',
					'loop' => self::legal_loop() ),
			) ),
		);

		// Drop whole pages whose integration is absent, then individual sections —
		// a page can stand on its own while one of its sections cannot (Calendar
		// has fixtures either way; its booking calendar needs LatePoint).
		$available = array();
		foreach ( $pages as $page ) {
			$slug = 'global' === $page['tab'] || 'home' === $page['tab'] ? '' : $page['tab'];
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			$page['sections'] = array_values(
				array_filter(
					$page['sections'],
					static fn( array $s ): bool => Blueworx_Clubhouse_Integrations::section_available(
						(string) $s['store_page'],
						(string) $s['key']
					)
				)
			);
			$available[] = $page;
		}
		return $available;
	}

	/** A booking slot: its own heading, plus the LatePoint shortcode that fills it. */
	private static function booking_slot_fields(): array {
		return array(
			self::f_text( 'eyebrow', 'Eyebrow' ),
			self::f_text( 'heading', 'Heading' ),
			self::f_shortcode( 'shortcode', 'Shortcode' ),
			// The way across to the other half of booking. Both halves or
			// neither: a label with no link renders nothing.
			self::f_text( 'link_label', 'Link label' ),
			self::f_url( 'link_href', 'Link' ),
		);
	}
}
