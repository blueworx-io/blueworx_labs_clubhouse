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
