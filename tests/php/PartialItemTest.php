<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Issue #212: a stored item missing one of its keys took the whole page down.
 *
 * The renderers read each row's keys straight off the array and handed them to
 * a string-typed escaper, so a missing key was a TypeError — a white screen on
 * About and Membership rather than a gap in one card. The admin editor always
 * writes every key, so no owner reached it by saving; an import, a partial
 * write or anything hand-edited did.
 *
 * A missing key is now an empty one. Local, not total: the rest of the row, the
 * rest of the list and the rest of the page all still render.
 */
final class PartialItemTest extends TestCase {

	/**
	 * One repeating section per case, its list holding a single row that carries
	 * one key and is missing every other — the worst case a hand-written file
	 * can produce.
	 *
	 * The section's own fields are supplied in full, because those are the
	 * renderer's contract with the block that calls it and are never partial. It
	 * is the rows that come from stored content.
	 *
	 * @return array<string,array{0:string,1:string,2:array<string,mixed>,3:string}>
	 */
	public static function sections(): array {
		$head = array( 'eyebrow' => 'E', 'heading' => 'H', 'link_label' => '', 'link_href' => '' );

		return array(
			'benefit_grid'   => array( 'benefit_grid', 'cards', $head, 'ch-benefit' ),
			'step_grid'      => array( 'step_grid', 'steps', $head, 'ch-step' ),
			'faq'            => array( 'faq', 'items', $head, 'ch-faq' ),
			'people_grid'    => array( 'people_grid', 'people', $head, 'ch-person' ),
			'card_grid'      => array( 'card_grid', 'cards', $head, 'ch-card' ),
			'stat_card_grid' => array( 'stat_card_grid', 'cards', $head, 'ch-scard' ),
			'news_cards'     => array( 'news_cards', 'cards', $head, 'ch-news' ),
			'event_grid'     => array( 'event_grid', 'cards', $head, 'ch-event' ),
			'timeline'       => array( 'timeline', 'milestones', $head, 'ch-milestone' ),
		);
	}

	/**
	 * @param array<string,mixed> $head
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'sections' )]
	public function test_a_row_missing_every_other_key_renders_rather_than_fatals(
		string $section,
		string $list,
		array $head,
		string $marker
	): void {
		// One key, and it is not one every section reads — so each case proves
		// the other keys are absent rather than empty.
		$html = Blueworx_Clubhouse_Sections::$section( $head + array( $list => array( array( 'title' => 'Kept' ) ) ) );

		$this->assertStringContainsString( $marker, $html, "{$section} rendered no row at all" );
	}

	/**
	 * The whole point: one bad row must not take the page with it. This is the
	 * exact shape that brought About and Membership down.
	 */
	public function test_a_partial_row_leaves_the_rest_of_the_page_standing(): void {
		wp_stub_reset();
		update_option( 'clubhouse_page_id_about', 42 );
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$content = new Blueworx_Clubhouse_Page_Content( $storage );
		$content->set_items( 'about', 'values', array(
			array( 'title' => 'Half a card' ),
			array( 'title' => 'A whole one', 'description' => 'With both keys.' ),
		) );

		$html = Blueworx_Clubhouse_Page_Renderer::about(
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Demo_Collections(),
			'',
			$content
		);

		$this->assertStringContainsString( 'Half a card', $html );
		$this->assertStringContainsString( 'A whole one', $html );
		$this->assertStringContainsString( '</footer>', $html, 'the page stopped short' );
	}
}
