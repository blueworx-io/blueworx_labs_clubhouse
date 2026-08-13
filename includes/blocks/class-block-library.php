<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The club's blocks: named instances of a block type, each holding its own
 * content. One block used on three pages is one entry here, which is what makes
 * "edit it once" true.
 *
 * Stored as a single entry, like Content_Store — one autoloaded option rather
 * than one per block, because every page render reads the whole library.
 *
 * Content is stored as given. Sanitising is the admin controller's job, the
 * same division Content_Store keeps.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Library {

	private const KEY = 'blocks';

	/**
	 * The sub-key holding retired ids inside the same stored entry as the blocks,
	 * so the store stays one storage key. A block never has a 'retired' field, so
	 * this key can never be confused with a block entry.
	 */
	private const RETIRED_KEY = 'retired';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,array{id:string,type:string,name:string,defaults_key:string,position:int,content:array,settings:array}> */
	public function all(): array {
		$stored = $this->storage->get( self::KEY, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		unset( $stored[ self::RETIRED_KEY ] );
		return $stored;
	}

	/**
	 * Ids of blocks deleted in the past. Kept forever so a deleted block's id is
	 * never handed back out to a new block while pages still list the old id —
	 * see unique_id().
	 *
	 * @return array<int,string>
	 */
	private function retired(): array {
		$stored  = $this->storage->get( self::KEY, array() );
		$retired = is_array( $stored ) ? ( $stored[ self::RETIRED_KEY ] ?? array() ) : array();
		return is_array( $retired ) ? $retired : array();
	}

	/** @param array<string,array> $blocks */
	private function save( array $blocks ): void {
		$blocks[ self::RETIRED_KEY ] = $this->retired();
		$this->storage->set( self::KEY, $blocks );
	}

	/** @param array<int,string> $retired */
	private function save_retired( array $blocks, array $retired ): void {
		$blocks[ self::RETIRED_KEY ] = array_values( array_unique( $retired ) );
		$this->storage->set( self::KEY, $blocks );
	}

	public function has( string $id ): bool {
		return isset( $this->all()[ $id ] );
	}

	/** @return array{id:string,type:string,name:string,defaults_key:string,position:int,content:array,settings:array}|null */
	public function get( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	/** @return array<string,array> */
	public function of_type( string $type ): array {
		return array_filter(
			$this->all(),
			static fn( array $block ): bool => $block['type'] === $type
		);
	}

	/**
	 * A url-safe id from the block's name, suffixed until it is unique. The id is
	 * fixed at creation and never follows a rename — pages refer to blocks by id,
	 * and a renamed block must not fall off the pages using it. Checked against
	 * retired ids too, so a deleted block's id is never recycled onto a new block
	 * while a page still lists the old id.
	 */
	private function unique_id( string $name, array $blocks ): string {
		$base = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ) ?? '', '-' );
		if ( '' === $base ) {
			$base = 'block';
		}
		$retired = $this->retired();
		$id      = $base;
		$n       = 1;
		while ( isset( $blocks[ $id ] ) || in_array( $id, $retired, true ) ) {
			++$n;
			$id = $base . '-' . $n;
		}
		return $id;
	}

	/**
	 * @param string   $type         A Block_Types key.
	 * @param string   $name         Owner-facing name.
	 * @param string   $defaults_key The "page/section" address whose default copy
	 *                               this block inherits; '' for the type's own.
	 * @param int|null $position     Where it sits on a page; the type's rank when null.
	 * @return string The new block's id.
	 */
	public function add( string $type, string $name, string $defaults_key = '', ?int $position = null ): string {
		$blocks = $this->all();
		$id     = $this->unique_id( $name, $blocks );

		$blocks[ $id ] = array(
			'id'           => $id,
			'type'         => $type,
			'name'         => $name,
			'defaults_key' => $defaults_key,
			'position'     => $position ?? Blueworx_Clubhouse_Block_Types::rank( $type ),
			'content'      => array(),
			'settings'     => array(),
		);
		$this->save( $blocks );
		return $id;
	}

	public function set_content( string $id, array $content ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		$blocks[ $id ]['content'] = $content;
		$this->save( $blocks );
	}

	public function set_settings( string $id, array $settings ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		$blocks[ $id ]['settings'] = $settings;
		$this->save( $blocks );
	}

	public function rename( string $id, string $name ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		$blocks[ $id ]['name'] = $name;
		$this->save( $blocks );
	}

	/** @return string The copy's id, or '' when the original is gone. */
	public function duplicate( string $id, string $name ): string {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return '';
		}
		$copy_id = $this->unique_id( $name, $blocks );

		$copy               = $blocks[ $id ];
		$copy['id']         = $copy_id;
		$copy['name']       = $name;
		$blocks[ $copy_id ] = $copy;

		$this->save( $blocks );
		return $copy_id;
	}

	/**
	 * Removes the block and retires its id, so a later block of the same name
	 * cannot take it back over while a page still lists the old id.
	 */
	public function delete( string $id ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		unset( $blocks[ $id ] );
		$retired   = $this->retired();
		$retired[] = $id;
		$this->save_retired( $blocks, $retired );
	}
}
