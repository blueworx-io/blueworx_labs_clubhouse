<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a page is on the site. Defaults to on; an owner switches one off
 * under Content → Pages, and Frontend::resolve_slug then 404s its address.
 *
 * Sections used to live here too, one flag per page-and-section, switched from
 * the Setup screen's Visibility tab. That tab has gone: what a page shows is
 * the list of blocks it is composed of, so taking a section off a page is
 * removing its block rather than setting a flag beside it. Nothing sets a
 * section flag any more; the stored ones are read exactly once, by the
 * migration that decides which blocks a club's pages start out with.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Visibility {

	private const KEY = 'visibility';

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
	 * Whether a section was showing on the site the club had before blocks.
	 * Read by the migration and by nothing else.
	 */
	public function is_section_visible( string $page, string $section ): bool {
		$state = $this->state();
		return (bool) ( $state['sections'][ $this->section_key( $page, $section ) ] ?? true );
	}

	public function set_page_visible( string $page, bool $visible ): void {
		$state                        = $this->state();
		$state['pages'][ $page ]      = $visible;
		$this->storage->set( self::KEY, $state );
	}

}
