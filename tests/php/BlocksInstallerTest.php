<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Composing a club's site out of blocks, once.
 *
 * Once is the whole point. There used to be a sync that re-derived the blocks
 * from the old content store on every save, which meant a club's own edits
 * could be walked over by the next upgrade. That has gone: the old store is
 * read exactly once, here, and never again.
 */
final class BlocksInstallerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function composition(): Blueworx_Clubhouse_Page_Composition {
		return new Blueworx_Clubhouse_Page_Composition( new Blueworx_Clubhouse_Options_Storage() );
	}

	private function library(): Blueworx_Clubhouse_Block_Library {
		return new Blueworx_Clubhouse_Block_Library( new Blueworx_Clubhouse_Options_Storage() );
	}

	public function test_an_uncomposed_site_gets_its_blocks(): void {
		$this->assertFalse( $this->composition()->is_configured() );

		Blueworx_Clubhouse_Blocks_Installer::install();

		$this->assertTrue( $this->composition()->is_configured() );
		$this->assertNotSame( '', $this->library()->by_address( 'home/hero' ) );
	}

	/** A club's words come across, the same as they would through the seeder. */
	public function test_it_carries_over_what_the_club_had_written(): void {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		Blueworx_Clubhouse_Test_Site::legacy_content( $storage, 'about', 'hero', array( 'eyebrow' => 'Est. 1974' ) );

		Blueworx_Clubhouse_Blocks_Installer::install();

		$this->assertSame( 'Est. 1974', Blueworx_Clubhouse_Test_Site::read( $storage, 'about/hero', 'eyebrow' ) );
	}

	/** The edit a club made after composing survives the next upgrade. */
	public function test_a_composed_site_is_left_exactly_as_the_club_left_it(): void {
		Blueworx_Clubhouse_Blocks_Installer::install();

		$storage = new Blueworx_Clubhouse_Options_Storage();
		$id      = $this->library()->by_address( 'about/committee' );
		$this->composition()->remove( 'about', $id );
		Blueworx_Clubhouse_Test_Site::write( $storage, 'about/hero', array( 'eyebrow' => 'Our own words' ) );
		$before = count( $this->library()->all() );

		Blueworx_Clubhouse_Blocks_Installer::install();

		$this->assertCount( $before, $this->library()->all(), 'a second run duplicated the library' );
		$this->assertNotContains( $id, $this->composition()->blocks( 'about' ) );
		$this->assertSame( 'Our own words', Blueworx_Clubhouse_Test_Site::read( $storage, 'about/hero', 'eyebrow' ) );
	}

	/**
	 * A club composed on an earlier release has the blocks that release knew
	 * about. The welcome pack came later and sits on no page, so nothing would
	 * ever create it — leaving the club with wording it cannot reach and a
	 * switch it cannot find.
	 */
	public function test_a_composed_site_still_gets_a_block_that_goes_on_no_page(): void {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		Blueworx_Clubhouse_Test_Site::legacy_content( $storage, 'global', 'welcome', array( 'heading' => 'Welcome' ) );
		Blueworx_Clubhouse_Blocks_Installer::install();

		// The site as an earlier release left it: composed, with no welcome block.
		$library = $this->library();
		$library->delete( $library->by_address( Blueworx_Clubhouse_Welcome_Pack::ADDRESS ) );
		$this->assertSame( '', $this->library()->by_address( Blueworx_Clubhouse_Welcome_Pack::ADDRESS ) );

		Blueworx_Clubhouse_Blocks_Installer::install();

		$this->assertNotSame( '', $this->library()->by_address( Blueworx_Clubhouse_Welcome_Pack::ADDRESS ) );
		$this->assertSame( 'Welcome', Blueworx_Clubhouse_Test_Site::read( $storage, Blueworx_Clubhouse_Welcome_Pack::ADDRESS, 'heading' ) );
	}

	/** Adopting a late block must not put a page back the way the plugin ships it. */
	public function test_adopting_leaves_the_clubs_own_pages_alone(): void {
		Blueworx_Clubhouse_Blocks_Installer::install();
		$id = $this->library()->by_address( 'about/committee' );
		$this->composition()->remove( 'about', $id );

		Blueworx_Clubhouse_Blocks_Installer::install();

		$this->assertNotContains( $id, $this->composition()->blocks( 'about' ) );
	}

	/**
	 * An in-place update never fires the activation hook, so the version stamp is
	 * what makes the first admin request afterwards compose the site — and what
	 * stops every request after that doing it again.
	 */
	public function test_the_version_stamp_makes_it_run_once_per_release(): void {
		Blueworx_Clubhouse_Blocks_Installer::maybe_install();
		$this->assertSame( BLUEWORX_LABS_CLUBHOUSE_VERSION, get_option( 'clubhouse_blocks_version', '' ) );

		$before = count( $this->library()->all() );
		Blueworx_Clubhouse_Blocks_Installer::maybe_install();
		$this->assertCount( $before, $this->library()->all() );
	}
}
