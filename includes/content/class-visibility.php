<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show/hide state for pages and sections. Defaults to visible; owners hide by
 * opting out — except the sections listed in SECTION_DEFAULTS, which ship hidden
 * and are opted into. Persisted as one storage entry mirroring the feature-toggle
 * pattern.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Visibility {

	private const KEY = 'visibility';

	/**
	 * Sections that ship hidden, keyed "page.section" — owners opt in rather than
	 * out. Anything absent here defaults to visible.
	 *
	 * @var array<string, bool>
	 */
	private const SECTION_DEFAULTS = array(
		// The social feed shows nothing until a club has pasted its posts in, so
		// shipping it on would put an empty band on every existing club site the
		// moment this updated. It is opted into, on Setup → Visibility.
		'home.social_feed' => false,
	);

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string, array<string, bool>> */
	private function state(): array {
		$state = $this->storage->get( self::KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	private function section_key( string $page, string $section ): string {
		return $page . '.' . $section;
	}

	public function is_page_visible( string $page ): bool {
		$state = $this->state();
		return (bool) ( $state['pages'][ $page ] ?? true );
	}

	/**
	 * Whether the real WordPress page behind this key is published, or null
	 * when there is no page to ask — a site whose club pages have not been
	 * created yet, or a preview with no WordPress under it.
	 *
	 * This is what Setup's Visibility switch reads. set_page_visible() writes
	 * the flag and the page's status together, so the two normally agree; they
	 * stop agreeing the moment somebody publishes or drafts the page from
	 * WordPress's own Pages list, which is a thing an owner can now do. The
	 * page's own status is the fact worth believing — it is what decides
	 * whether a visitor gets the page or a "not found" — so the switch asks
	 * that, and falls back to the stored flag only when there is nothing to
	 * ask.
	 */
	public function page_status_is_visible( string $page ): ?bool {
		if ( ! class_exists( 'Blueworx_Clubhouse_Club_Pages' ) || ! function_exists( 'get_post_status' ) ) {
			return null;
		}
		$slug = Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $page );
		if ( null === $slug ) {
			return null;
		}
		$post_id = Blueworx_Clubhouse_Club_Pages::post_id( $slug );
		if ( $post_id <= 0 ) {
			return null;
		}
		$status = get_post_status( $post_id );
		if ( ! is_string( $status ) || '' === $status ) {
			return null;
		}
		return Blueworx_Clubhouse_Club_Pages::status_for( true ) === $status;
	}

	public function is_section_visible( string $page, string $section ): bool {
		$state = $this->state();
		$key   = $this->section_key( $page, $section );
		return (bool) ( $state['sections'][ $key ] ?? self::SECTION_DEFAULTS[ $key ] ?? true );
	}

	/**
	 * Switch a page on or off.
	 *
	 * The flag is the record — the Setup screen reads it and resolve_slug()
	 * checks it — and the real WordPress page behind the club page is moved to
	 * match, published while it is on and a draft once it is off. Without that
	 * second half a switched-off page is still in the sitemap and still in
	 * search, because only this plugin knows it is off.
	 */
	public function set_page_visible( string $page, bool $visible ): void {
		$state                   = $this->state();
		$state['pages'][ $page ] = $visible;
		$this->storage->set( self::KEY, $state );
		if ( class_exists( 'Blueworx_Clubhouse_Club_Pages' ) ) {
			Blueworx_Clubhouse_Club_Pages::sync_status( $page, $visible );
		}
	}

	public function set_section_visible( string $page, string $section, bool $visible ): void {
		$state = $this->state();
		$state['sections'][ $this->section_key( $page, $section ) ] = $visible;
		$this->storage->set( self::KEY, $state );
	}
}
