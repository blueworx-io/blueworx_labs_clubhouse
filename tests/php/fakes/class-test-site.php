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
