<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What each page is made of: whether it is on the site, and which blocks it
 * shows. The order stored here is only a tie-break — a page renders its blocks
 * by each block's own position (see Block_Library), because the editor does not
 * offer moving one.
 *
 * Persisted as one entry, mirroring Visibility, which it replaces for sections.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Composition {

	private const KEY = 'page_composition';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,array{enabled?:bool,blocks?:array<int,string>}> */
	private function state(): array {
		$state = $this->storage->get( self::KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	private function save( array $state ): void {
		$this->storage->set( self::KEY, $state );
	}

	/** False until this site has stored a composition — the seeder's cue. */
	public function is_configured(): bool {
		return array() !== $this->state();
	}

	/** @return array<int,string> */
	public function blocks( string $page ): array {
		$blocks = $this->state()[ $page ]['blocks'] ?? array();
		return is_array( $blocks ) ? array_values( $blocks ) : array();
	}

	/** @param array<int,string> $ids */
	public function set_blocks( string $page, array $ids ): void {
		$state                     = $this->state();
		$state[ $page ]['blocks']  = array_values( array_unique( $ids ) );
		$this->save( $state );
	}

	public function add( string $page, string $id ): void {
		$blocks = $this->blocks( $page );
		if ( in_array( $id, $blocks, true ) ) {
			return;
		}
		$blocks[] = $id;
		$this->set_blocks( $page, $blocks );
	}

	public function remove( string $page, string $id ): void {
		$this->set_blocks(
			$page,
			array_values( array_filter( $this->blocks( $page ), static fn( string $b ): bool => $b !== $id ) )
		);
	}

	/**
	 * Every page showing this block. The library's "used on" line and the delete
	 * warning both read this, so an owner is never surprised by a shared edit.
	 *
	 * @return array<int,string>
	 */
	public function uses( string $id ): array {
		$pages = array();
		foreach ( array_keys( $this->state() ) as $page ) {
			if ( in_array( $id, $this->blocks( (string) $page ), true ) ) {
				$pages[] = (string) $page;
			}
		}
		return $pages;
	}

	public function is_enabled( string $page ): bool {
		return (bool) ( $this->state()[ $page ]['enabled'] ?? true );
	}

	public function set_enabled( string $page, bool $enabled ): void {
		$state                     = $this->state();
		$state[ $page ]['enabled'] = $enabled;
		$this->save( $state );
	}
}
