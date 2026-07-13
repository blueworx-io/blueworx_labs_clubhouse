<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class WpCollectionsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_sport_image_id_is_resolved_to_a_url(): void {
		$post = (object) array( 'ID' => 7, 'post_title' => 'Rugby', 'post_type' => 'clubhouse_sport' );
		$GLOBALS['wp_stub_posts']['clubhouse_sport'] = array( $post );
		$GLOBALS['wp_stub_postmeta'][7] = array( 'label' => 'RUG', 'image' => '42' );

		$sports = ( new Blueworx_Clubhouse_WP_Collections() )->sports();

		$this->assertCount( 1, $sports );
		$this->assertSame( 'Rugby', $sports[0]['title'] );
		$this->assertSame( 'https://club.test/wp-content/uploads/att-42.png', $sports[0]['image'] );
	}

	public function test_empty_image_stays_empty(): void {
		$post = (object) array( 'ID' => 8, 'post_title' => 'Cricket', 'post_type' => 'clubhouse_sport' );
		$GLOBALS['wp_stub_posts']['clubhouse_sport'] = array( $post );
		$GLOBALS['wp_stub_postmeta'][8] = array( 'image' => '' );

		$sports = ( new Blueworx_Clubhouse_WP_Collections() )->sports();
		$this->assertSame( '', $sports[0]['image'] );
	}
}
