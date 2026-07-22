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
	private static function f_toggle( string $key, string $label ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'toggle' );
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

	/** @return array<int,array<string,mixed>> */
	public static function pages(): array {
		return array(
			array( 'tab' => 'global', 'label' => 'Global', 'sections' => array(
				array( 'key' => 'header', 'label' => 'Header', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Logo and club name come from Site setup → Branding.',
					'fields' => array(
						self::f_text( 'join', 'Menu CTA label', 'e.g. Join the Club' ),
						self::f_url( 'join_href', 'Menu CTA link' ),
						self::f_toggle( 'banner_show', 'Show announcement bar' ),
						self::f_text( 'banner', 'Announcement text' ),
						self::f_url( 'banner_href', 'Announcement link' ),
					) ),
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'home', 'fields' => self::hero_fields() ),
				array( 'key' => 'quick_tiles', 'label' => 'Quick tiles', 'type' => 'loop', 'store_page' => 'home',
					'note' => 'These render as the icon cards at the foot of the Home hero.',
					'loop' => array( 'name' => 'Tile', 'plural' => 'Tiles', 'fields' => array( self::f_text( 'label', 'Label' ), self::f_url( 'href', 'Link' ), self::f_select( 'icon', 'Icon', self::TILE_ICON_OPTIONS ) ) ) ),
				array( 'key' => 'ticker', 'label' => 'Ticker', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Message', 'plural' => 'Messages', 'fields' => array( self::f_text( 'text', 'Message' ) ) ) ),
				array( 'key' => 'stats', 'label' => 'Stats', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Stat', 'plural' => 'Stats', 'fields' => array( self::f_text( 'value', 'Value' ), self::f_text( 'label', 'Label' ), self::f_toggle( 'featured', 'Featured' ) ) ) ),
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
				array( 'key' => 'info', 'label' => 'Info strip', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Column', 'plural' => 'Columns', 'fields' => array( self::f_text( 'label', 'Label' ), self::f_area( 'lines', 'Lines (one per line)' ), self::f_text( 'link_label', 'Link label' ), self::f_url( 'link_href', 'Link href' ) ) ) ),
				array( 'key' => 'sponsors', 'label' => 'Sponsors', 'type' => 'linkout', 'store_page' => 'home',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_sponsor', 'label' => 'Manage sponsors', 'text' => 'Sponsors are managed as a collection.' ) ),
				array( 'key' => 'social', 'label' => 'Social', 'type' => 'fields', 'store_page' => 'home',
					'note' => 'Profile links come from Site setup → Branding.',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'lede', 'Lede' ) ) ),
				array( 'key' => 'footer', 'label' => 'Footer', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Contact details and social links come from Site setup → Branding.',
					'fields' => array( self::f_area( 'tagline', 'About blurb', 4 ) ) ),
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
					'loop' => array( 'name' => 'Tier', 'plural' => 'Tiers', 'fields' => array(
						self::f_text( 'name', 'Name' ),
						self::f_text( 'price', 'Price' ),
						self::f_text( 'period', 'Period' ),
						self::f_area( 'features', 'Features (one per line)', 4 ),
						self::f_toggle( 'featured', 'Most popular' ),
						self::f_text( 'cta_label', 'CTA label' ),
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
					'note' => 'This is a demo form — it does not send submissions anywhere yet.',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ), self::f_text( 'submit_label', 'Submit button label' ) ) ),
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
					'auto' => array( 'text' => 'Derived from events marked past. → Manage events', 'cpt' => 'clubhouse_event' ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'events', 'fields' => self::cta_fields() ),
			) ),
			array( 'tab' => 'calendar', 'label' => 'Calendar', 'sections' => array(
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'calendar', 'fields' => self::hero_filter_fields() ),
				array( 'key' => 'schedule', 'label' => 'Schedule', 'type' => 'fields', 'store_page' => 'calendar',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'eyebrow', 'Intro' ) ),
					'auto' => array( 'text' => 'Built from each sport’s fixtures and results.', 'cpt' => 'clubhouse_fixture' ) ),
				array( 'key' => 'cta', 'label' => 'Call to action', 'type' => 'fields', 'store_page' => 'calendar', 'fields' => self::cta_fields() ),
			) ),
		);
	}
}
