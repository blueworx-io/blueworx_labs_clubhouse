<?php
declare(strict_types=1);

/**
 * A whole site, composed from blocks, for a test that wants to look at a page.
 *
 * Every page test used to call one of Page_Renderer's eleven page methods.
 * Those have gone: a page is the blocks it is composed of, so a test that wants
 * one has to seed a library first. Doing that by hand in thirty test classes
 * would be thirty chances to seed it slightly differently, so it happens here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Test_Site {

	/** A seeded composer over this storage, seeding it if nobody has yet. */
	public static function composer( Blueworx_Clubhouse_Storage $storage ): Blueworx_Clubhouse_Page_Composer {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		if ( ! $composition->is_configured() ) {
			( new Blueworx_Clubhouse_Block_Seeder( $library, $composition ) )->seed();
		}
		return new Blueworx_Clubhouse_Page_Composer( $library, $composition );
	}

	/**
	 * One page's whole body, header and footer included.
	 */
	public static function page(
		string $page,
		?Blueworx_Clubhouse_Storage $storage = null,
		string $filter = '',
		?Blueworx_Clubhouse_Collections $collections = null
	): string {
		$storage     = $storage ?? new Blueworx_Clubhouse_Fake_Storage();
		$collections = $collections ?? new Blueworx_Clubhouse_Demo_Collections();
		return self::composer( $storage )->page(
			$page,
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			$collections,
			'',
			$filter
		);
	}

	/** The same, addressed by URL slug rather than page key — '' being Home. */
	public static function slug( string $slug, ?Blueworx_Clubhouse_Storage $storage = null ): string {
		return self::page( Blueworx_Clubhouse_Page_Map::page_key( $slug ), $storage );
	}

	/**
	 * The single-article screen, which wears the same chrome as everything else
	 * but is not a composed page — it is whichever post News::source() is on.
	 */
	public static function article(
		?Blueworx_Clubhouse_Storage $storage = null,
		?Blueworx_Clubhouse_Collections $collections = null
	): string {
		$storage = $storage ?? new Blueworx_Clubhouse_Fake_Storage();
		return Blueworx_Clubhouse_Page_Renderer::post(
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			$collections ?? new Blueworx_Clubhouse_Demo_Collections(),
			self::composer( $storage )
		);
	}

	/** Just the <main>, for a test about the page rather than the chrome. */
	public static function main( string $html ): string {
		$open  = strpos( $html, '<main' );
		$close = strpos( $html, '</main>' );
		if ( false === $open || false === $close ) {
			return $html;
		}
		return substr( $html, $open, $close - $open );
	}

	/**
	 * Take the blocks at these addresses off their pages — the block stays in
	 * the library, exactly as removing it on the Pages screen would leave it.
	 * This is how a test says "a club that does not show its committee".
	 */
	public static function without( Blueworx_Clubhouse_Storage $storage, string ...$addresses ): void {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		if ( ! $composition->is_configured() ) {
			( new Blueworx_Clubhouse_Block_Seeder( $library, $composition ) )->seed();
		}
		foreach ( $addresses as $address ) {
			$id = $library->by_address( $address );
			if ( '' === $id ) {
				continue;
			}
			foreach ( $composition->uses( $id ) as $page ) {
				$composition->remove( $page, $id );
			}
		}
	}

	/**
	 * Write content the way a club held it before blocks, for a test about the
	 * migration. Nothing in the plugin writes here any more — Content_Store is
	 * read-only and the Visibility tab has gone — so a test that needs an
	 * old-shaped site has to lay one down itself.
	 *
	 * @param array<string,mixed> $fields
	 */
	public static function legacy_content( Blueworx_Clubhouse_Storage $storage, string $page, string $section, array $fields ): void {
		$key             = 'content_' . $page;
		$all             = (array) $storage->get( $key, array() );
		$all[ $section ] = array_merge( (array) ( $all[ $section ] ?? array() ), $fields );
		$storage->set( $key, $all );
	}

	/** A section the club had switched off on the old Setup screen's Visibility tab. */
	public static function legacy_section_off( Blueworx_Clubhouse_Storage $storage, string $page, string $section ): void {
		$state = (array) $storage->get( 'visibility', array() );
		$state['sections'][ $page . '.' . $section ] = false;
		$storage->set( 'visibility', $state );
	}

	/**
	 * Read a field back off the block that holds an address — the other half of
	 * write(), for a test checking what a save or an import actually stored.
	 */
	public static function read( Blueworx_Clubhouse_Storage $storage, string $address, string $field, mixed $default = null ): mixed {
		$content = self::content( $storage, $address );
		$key     = Blueworx_Clubhouse_Block_Addresses::prefix( $address ) . $field;
		return array_key_exists( $key, $content ) ? $content[ $key ] : $default;
	}

	/** A repeating section's stored rows. @return array<int,array<string,mixed>> */
	public static function items( Blueworx_Clubhouse_Storage $storage, string $address ): array {
		$items = self::content( $storage, $address )['items'] ?? array();
		return is_array( $items ) ? array_values( $items ) : array();
	}

	/** Everything stored on the block that holds an address. @return array<string,mixed> */
	public static function content( Blueworx_Clubhouse_Storage $storage, string $address ): array {
		$library = new Blueworx_Clubhouse_Block_Library( $storage );
		$id      = $library->by_address( Blueworx_Clubhouse_Block_Addresses::host( $address ) );
		if ( '' === $id ) {
			return array();
		}
		return (array) $library->get( $id )['content'];
	}

	/**
	 * Write a block's content, the way the Blocks screen would.
	 *
	 * @param array<string,mixed> $content
	 */
	public static function write( Blueworx_Clubhouse_Storage $storage, string $address, array $content ): void {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		if ( ! $composition->is_configured() ) {
			( new Blueworx_Clubhouse_Block_Seeder( $library, $composition ) )->seed();
		}
		$id = $library->by_address( Blueworx_Clubhouse_Block_Addresses::host( $address ) );
		if ( '' === $id ) {
			return;
		}
		$prefix = Blueworx_Clubhouse_Block_Addresses::prefix( $address );
		$merged = (array) $library->get( $id )['content'];
		foreach ( $content as $key => $value ) {
			$merged[ 'items' === (string) $key ? 'items' : $prefix . (string) $key ] = $value;
		}
		$library->set_content( $id, $merged );
	}
}
