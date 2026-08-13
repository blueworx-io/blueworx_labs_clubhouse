<?php

use PHPUnit\Framework\TestCase;

/**
 * The spine has to be able to describe the site the plugin ships today. This
 * builds it from the address map and checks what comes back out, so a model
 * that cannot hold the real site fails here rather than in plan 2's renderer.
 */
final class BlockSpineTest extends TestCase {

	/** @return array{0:Blueworx_Clubhouse_Block_Library,1:Blueworx_Clubhouse_Page_Composition} */
	private function build(): array {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$lib     = new Blueworx_Clubhouse_Block_Library( $storage );
		$comp    = new Blueworx_Clubhouse_Page_Composition( $storage );

		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			[ $page, $section ] = explode( '/', $address );
			$id                 = $lib->add(
				$entry['type'],
				ucfirst( $page ) . ' · ' . ucfirst( str_replace( '_', ' ', $section ) ),
				$address,
				$entry['position']
			);
			if ( 'global' !== $page ) {
				$comp->add( $page, $id );
			}
		}
		return array( $lib, $comp );
	}

	private function ordered( Blueworx_Clubhouse_Block_Library $lib, Blueworx_Clubhouse_Page_Composition $comp, string $page ): array {
		$blocks = array();
		foreach ( $comp->blocks( $page ) as $index => $id ) {
			$block             = $lib->get( $id );
			$blocks[] = array( $block['position'], $index, $block['defaults_key'] );
		}
		usort(
			$blocks,
			static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] )
		);
		return array_column( $blocks, 2 );
	}

	public function test_every_address_becomes_exactly_one_block(): void {
		[ $lib ] = $this->build();
		$this->assertCount( count( Blueworx_Clubhouse_Block_Addresses::map() ), $lib->all() );
	}

	/**
	 * About is the page that killed one-rank-per-type: values and get involved are
	 * both benefit grids, either side of facilities and committee. If the model
	 * ever loses per-block positions, this is the test that says so.
	 */
	public function test_about_keeps_its_running_order(): void {
		[ $lib, $comp ] = $this->build();
		$this->assertSame(
			array(
				'about/hero',
				'about/history',
				'about/values',
				'about/facilities',
				'about/committee',
				'about/get_involved',
				'about/cta',
			),
			$this->ordered( $lib, $comp, 'about' )
		);
	}

	public function test_home_keeps_its_running_order(): void {
		[ $lib, $comp ] = $this->build();
		$this->assertSame(
			array(
				'home/hero',
				'home/quick_tiles',
				'home/ticker',
				'home/sports',
				'home/clubhouse',
				'home/membership',
				'home/activity',
				'home/news',
				'home/sponsors',
				'home/social',
				'home/info',
			),
			$this->ordered( $lib, $comp, 'home' )
		);
	}

	public function test_a_block_can_be_shared_by_two_pages(): void {
		[ $lib, $comp ] = $this->build();
		$shared         = $lib->add( 'band', 'Join today', 'about/cta', 400 );
		$comp->add( 'home', $shared );
		$comp->add( 'contact', $shared );

		$this->assertSame( array( 'home', 'contact' ), $comp->uses( $shared ) );

		$lib->set_content( $shared, array( 'heading' => 'Come and play' ) );
		$this->assertSame( 'Come and play', $lib->get( $shared )['content']['heading'] );
	}

	public function test_taking_a_block_off_one_page_leaves_it_in_the_library(): void {
		[ $lib, $comp ] = $this->build();
		$id             = $comp->blocks( 'about' )[0];
		$comp->remove( 'about', $id );

		$this->assertNotContains( $id, $comp->blocks( 'about' ) );
		$this->assertTrue( $lib->has( $id ) );
	}

	public function test_the_header_and_footer_are_on_no_page(): void {
		[ $lib, $comp ] = $this->build();
		foreach ( $lib->all() as $id => $block ) {
			if ( in_array( $block['type'], array( 'header', 'footer' ), true ) ) {
				$this->assertSame( array(), $comp->uses( $id ), $id );
			}
		}
	}
}
