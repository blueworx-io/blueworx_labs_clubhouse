<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionMetaBoxesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_box_html_renders_a_control_per_field_with_nonce(): void {
		$html = Blueworx_Clubhouse_Collection_Meta_Boxes::box_html( 'clubhouse_fixture', 0 );
		$this->assertStringContainsString( 'name="_clubhouse_meta_nonce"', $html );
		$this->assertStringContainsString( 'name="clubhouse_meta[match_date]"', $html );
		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'type="time"', $html );
		$this->assertStringContainsString( '<select', $html ); // outcome
	}

	public function test_box_html_escapes_stored_values(): void {
		$GLOBALS['wp_stub_postmeta'][5] = array( 'venue' => '"><script>x</script>' );
		$html = Blueworx_Clubhouse_Collection_Meta_Boxes::box_html( 'clubhouse_fixture', 5 );
		$this->assertStringNotContainsString( '<script>x', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_save_sanitises_and_persists_each_field(): void {
		$post = (object) array( 'ID' => 11, 'post_type' => 'clubhouse_fixture' );
		$_POST['_clubhouse_meta_nonce'] = 'stub';
		$_POST['clubhouse_meta'] = array(
			'match_date' => '2026-08-01',
			'outcome'    => 'BOGUS',
			'home_team'  => '  Alpha  ',
		);

		Blueworx_Clubhouse_Collection_Meta_Boxes::save( 11, $post );

		$this->assertSame( '2026-08-01', $GLOBALS['wp_stub_postmeta'][11]['match_date'] );
		$this->assertSame( '', $GLOBALS['wp_stub_postmeta'][11]['outcome'] );      // rejected → default
		$this->assertSame( 'Alpha', $GLOBALS['wp_stub_postmeta'][11]['home_team'] ); // trimmed

		unset( $_POST['_clubhouse_meta_nonce'], $_POST['clubhouse_meta'] );
	}

	public function test_save_ignores_posts_without_the_nonce(): void {
		$post = (object) array( 'ID' => 12, 'post_type' => 'clubhouse_fixture' );
		Blueworx_Clubhouse_Collection_Meta_Boxes::save( 12, $post );
		$this->assertArrayNotHasKey( 12, $GLOBALS['wp_stub_postmeta'] );
	}

	public function test_merge_columns_inserts_our_columns_between_title_and_date(): void {
		$cols = array( 'cb' => '<input>', 'title' => 'Title', 'date' => 'Date' );
		$merged = Blueworx_Clubhouse_Collection_Meta_Boxes::merge_columns( 'clubhouse_fixture', $cols );
		$this->assertSame(
			array( 'cb', 'title', 'clubhouse_match_date', 'clubhouse_matchup', 'clubhouse_result', 'date' ),
			array_keys( $merged )
		);
	}

	public function test_column_value_composes_fixture_matchup_and_result(): void {
		$GLOBALS['wp_stub_postmeta'][20] = array( 'home_team' => 'Alpha', 'away_team' => 'Beta', 'score' => '2-1', 'outcome' => 'W' );
		$this->assertSame( 'Alpha v Beta', Blueworx_Clubhouse_Collection_Meta_Boxes::column_value( 'clubhouse_fixture', 'clubhouse_matchup', 20 ) );
		$this->assertSame( '2-1 (W)', Blueworx_Clubhouse_Collection_Meta_Boxes::column_value( 'clubhouse_fixture', 'clubhouse_result', 20 ) );
	}

	public function test_register_adds_meta_box_and_column_hooks(): void {
		Blueworx_Clubhouse_Collection_Meta_Boxes::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$filters = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_filter' ) );
		$this->assertContains( 'add_meta_boxes', $actions );
		$this->assertContains( 'save_post', $actions );
		$this->assertContains( 'manage_clubhouse_fixture_posts_columns', $filters );
	}
}
