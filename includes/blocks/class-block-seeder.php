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
