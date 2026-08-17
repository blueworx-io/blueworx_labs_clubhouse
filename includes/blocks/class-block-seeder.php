<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fills the block library and the page compositions.
 *
 * Two ways in, one shape out. A fresh install is seeded: every block the
 * plugin ships, on the page it ships on, with no content of its own so the
 * code defaults show through. An existing club is migrated: the same blocks,
 * carrying whatever that club had written, on the pages where those sections
 * were switched on. Either way the front end afterwards is the site the club
 * already had.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Seeder {

	private Blueworx_Clubhouse_Block_Library $library;
	private Blueworx_Clubhouse_Page_Composition $composition;

	public function __construct(
		Blueworx_Clubhouse_Block_Library $library,
		Blueworx_Clubhouse_Page_Composition $composition
	) {
		$this->library     = $library;
		$this->composition = $composition;
	}

	/**
	 * A fresh site: every block on, nothing written yet.
	 */
	public function seed(): void {
		$this->build( null, null );
	}

	/**
	 * An existing site: the club's own words, and only the sections it had
	 * switched on. The front end must be identical either side of this.
	 */
	public function migrate( Blueworx_Clubhouse_Content_Store $content, Blueworx_Clubhouse_Visibility $visibility ): void {
		$this->build( $content, $visibility );
	}

	/**
	 * Bring an already-composed site back into step with the old screens.
	 *
	 * Interim, and deliberately so: until the page and block screens exist, the
	 * Club Pages and Setup screens are still where a club edits its site, and
	 * they write to the content store and the visibility toggles. This projects
	 * a save onto the blocks the front end renders, so an owner's change shows
	 * up on the site. It creates and deletes nothing — a site that has not been
	 * composed yet is left alone — and it goes away with the screens it serves.
	 */
	public function sync( Blueworx_Clubhouse_Content_Store $content, Blueworx_Clubhouse_Visibility $visibility ): void {
		if ( ! $this->composition->is_configured() ) {
			return;
		}

		$by_address = array();
		foreach ( $this->library->all() as $id => $block ) {
			$key = (string) ( $block['defaults_key'] ?? '' );
			if ( '' !== $key ) {
				$by_address[ $key ] = (string) $id;
			}
		}

		$onpage = array();
		foreach ( array_keys( Blueworx_Clubhouse_Block_Addresses::map() ) as $address ) {
			$address = (string) $address;
			if ( ! isset( $by_address[ $address ] ) ) {
				continue;
			}
			$id = $by_address[ $address ];

			$this->library->set_content( $id, $this->content_for( $address, $content ) );
			$settings = self::settings_for( $address, $visibility );
			if ( array() !== $settings ) {
				$this->library->set_settings( $id, $settings );
			}

			$page = explode( '/', $address, 2 )[0];
			if ( 'global' === $page ) {
				continue;
			}
			if ( self::on_page( $address, $visibility ) ) {
				$onpage[ $page ][] = $id;
			}
		}

		foreach ( $onpage as $page => $ids ) {
			$this->composition->set_blocks( (string) $page, $ids );
		}
		foreach ( $this->pages() as $page ) {
			if ( ! isset( $onpage[ $page ] ) ) {
				$this->composition->set_blocks( $page, array() );
			}
			$this->composition->set_enabled( $page, $visibility->is_page_visible( $page ) );
		}
	}

	/**
	 * Write a block's content back to the old content store.
	 *
	 * The other half of sync(), and interim for the same reason. While Club
	 * Pages, Setup and import still project the content store onto the blocks,
	 * anything saved on the Blocks screen would be overwritten by the next save
	 * on any of them. Writing back keeps the two in step, so whichever screen an
	 * owner reaches for, their words survive.
	 *
	 * Exactly reverses content_for: folded items go back to the address they came
	 * from, and the footer's cookie fields go back to the cookie notice. A block
	 * with no address of its own — one an owner made — has nowhere to write back
	 * to, and needs none: sync() only ever visits addresses.
	 *
	 * Goes with sync() when the old path does (issue #207).
	 *
	 * @param array<string,mixed> $block
	 */
	public static function project( array $block, Blueworx_Clubhouse_Content_Store $content ): void {
		$address = (string) ( $block['defaults_key'] ?? '' );
		if ( '' === $address || ! str_contains( $address, '/' ) ) {
			return;
		}
		[ $page, $section ] = explode( '/', $address, 2 );

		foreach ( (array) ( $block['content'] ?? array() ) as $key => $value ) {
			$key = (string) $key;

			if ( 'items' === $key ) {
				$target = self::items_address( $address );
				[ $item_page, $item_section ] = explode( '/', $target, 2 );
				$content->set_items( $item_page, $item_section, is_array( $value ) ? array_values( $value ) : array() );
				continue;
			}

			if ( 'global/footer' === $address && str_starts_with( $key, 'cookie_' ) ) {
				$content->set( 'global', 'cookies', substr( $key, 7 ), $value );
				continue;
			}

			$content->set( $page, $section, $key, $value );
		}
	}

	/**
	 * Where a block's repeated items belong in the old store: the address that
	 * folds into it when one does, and its own otherwise.
	 */
	private static function items_address( string $address ): string {
		foreach ( Blueworx_Clubhouse_Block_Addresses::folds() as $folded => $into ) {
			if ( $into === $address ) {
				return (string) $folded;
			}
		}
		return $address;
	}

	/**
	 * Every page a block can sit on.
	 *
	 * @return array<int,string>
	 */
	private function pages(): array {
		$pages = array();
		foreach ( array_keys( Blueworx_Clubhouse_Block_Addresses::map() ) as $address ) {
			$page = explode( '/', (string) $address, 2 )[0];
			if ( 'global' !== $page && ! in_array( $page, $pages, true ) ) {
				$pages[] = $page;
			}
		}
		return $pages;
	}

	private function build( ?Blueworx_Clubhouse_Content_Store $content, ?Blueworx_Clubhouse_Visibility $visibility ): void {
		$folds  = Blueworx_Clubhouse_Block_Addresses::folds();
		$made   = array();
		$onpage = array();

		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			// A folded address has no block of its own — its content belongs to the
			// block it renders inside, and is picked up there.
			if ( isset( $folds[ $address ] ) ) {
				continue;
			}
			// The welcome pack is prose in shape but renders on the shop's customer
			// dashboard, which this plugin does not own. It stays where it is.
			if ( 'global/welcome' === $address ) {
				continue;
			}
			// The cookie notice renders inside the footer, so its wording goes onto
			// the footer block rather than becoming a block nobody can place.
			if ( 'global/cookies' === $address ) {
				continue;
			}

			[ $page, $section ] = explode( '/', $address, 2 );

			$id = $this->library->add(
				$entry['type'],
				self::name_for( $page, $section ),
				$address,
				$entry['position']
			);
			$made[ $address ] = $id;

			if ( null !== $content ) {
				$this->library->set_content( $id, $this->content_for( $address, $content ) );
			}
			$settings = self::settings_for( $address, $visibility );
			if ( array() !== $settings ) {
				$this->library->set_settings( $id, $settings );
			}

			if ( 'global' === $page ) {
				continue;
			}
			if ( self::on_page( $address, $visibility ) ) {
				$onpage[ $page ][] = $id;
			}
		}

		// The Home tier grid shows the Membership page's tiers rather than a copy
		// of them, so editing the tiers once still changes both pages.
		if ( isset( $made['home/tiers'], $made['membership/tiers'] ) ) {
			$this->library->set_settings( $made['home/tiers'], array( 'mirror' => $made['membership/tiers'] ) );
		}

		foreach ( $onpage as $page => $ids ) {
			$this->composition->set_blocks( (string) $page, $ids );
		}
		foreach ( $this->pages() as $page ) {
			$this->composition->set_enabled( $page, null === $visibility || $visibility->is_page_visible( $page ) );
		}
	}

	/**
	 * Whether this block goes onto its page, from the switches the club had set.
	 *
	 * Most blocks obey their own section's switch. Two do not, because they were
	 * never one section to begin with:
	 *
	 * - The Home tier grid has no switch of its own. It appears with the
	 *   membership band above it and disappears with it, which is how the page
	 *   has always behaved.
	 * - The Home closing band is two switches over one rendered block — the
	 *   socials and the find-us columns. It stays on the page while either half
	 *   is on; which halves show is then a setting on the block.
	 */
	private static function on_page( string $address, ?Blueworx_Clubhouse_Visibility $visibility ): bool {
		if ( null === $visibility ) {
			return true;
		}
		if ( 'home/tiers' === $address ) {
			return $visibility->is_section_visible( 'home', 'membership' );
		}
		if ( 'home/social' === $address ) {
			return $visibility->is_section_visible( 'home', 'social' )
				|| $visibility->is_section_visible( 'home', 'info' );
		}
		[ $page, $section ] = explode( '/', $address, 2 );
		return $visibility->is_section_visible( $page, $section );
	}

	/** "Home · Hero", and just "Header" for the two that are on every page. */
	private static function name_for( string $page, string $section ): string {
		$label = ucfirst( str_replace( '_', ' ', $section ) );
		if ( 'global' === $page ) {
			return $label;
		}
		return ucfirst( $page ) . ' · ' . $label;
	}

	/**
	 * A block's settings, where its behaviour was two toggles before it was one
	 * block.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings_for( string $address, ?Blueworx_Clubhouse_Visibility $visibility ): array {
		if ( 'home/social' !== $address ) {
			return array();
		}
		return array(
			'show_social'  => null === $visibility || $visibility->is_section_visible( 'home', 'social' ),
			'show_columns' => null === $visibility || $visibility->is_section_visible( 'home', 'info' ),
		);
	}

	/**
	 * What a club had written at this address — plus, where two addresses render
	 * as one block, what it had written at the other.
	 *
	 * @return array<string,mixed>
	 */
	private function content_for( string $address, Blueworx_Clubhouse_Content_Store $content ): array {
		[ $page, $section ] = explode( '/', $address, 2 );
		$own = $content->get_section( $page, $section );

		foreach ( Blueworx_Clubhouse_Block_Addresses::folds() as $folded => $into ) {
			if ( $into !== $address ) {
				continue;
			}
			[ $fold_page, $fold_section ] = explode( '/', $folded, 2 );
			$items = $content->get_items( $fold_page, $fold_section );
			if ( array() !== $items ) {
				$own['items'] = $items;
			}
		}

		if ( 'global/footer' === $address ) {
			foreach ( $content->get_section( 'global', 'cookies' ) as $key => $value ) {
				$own[ 'cookie_' . (string) $key ] = $value;
			}
		}

		return $own;
	}
}
