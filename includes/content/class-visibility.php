<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show/hide state for pages and sections. Defaults to visible; owners hide by
 * opting out. Persisted as one storage entry mirroring the feature-toggle pattern.
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

	public function is_section_visible( string $page, string $section ): bool {
		$state = $this->state();
		return (bool) ( $state['sections'][ $this->section_key( $page, $section ) ] ?? true );
	}

	public function set_page_visible( string $page, bool $visible ): void {
		$state                        = $this->state();
		$state['pages'][ $page ]      = $visible;
		$this->storage->set( self::KEY, $state );
	}

	public function set_section_visible( string $page, string $section, bool $visible ): void {
		$state = $this->state();
		$state['sections'][ $this->section_key( $page, $section ) ] = $visible;
		$this->storage->set( self::KEY, $state );
	}
}
