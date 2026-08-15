<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #165: two of the three upcoming event cards carried a call to action
 * and the third did not, so it read as broken rather than different.
 *
 * The rule the shipped content now follows: every upcoming event offers an
 * action, and no past one does. Both halves of that are what make the
 * difference legible — a card with nothing to do about it sits among other
 * cards with nothing to do about them.
 */
final class EventCardActionsTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function events( string $status ): array {
		return array_values( array_filter(
			Blueworx_Clubhouse_Demo_Content::events(),
			static fn ( array $e ): bool => $status === ( $e['status'] ?? '' )
		) );
	}

	public function test_every_upcoming_event_offers_an_action(): void {
		$upcoming = $this->events( 'upcoming' );
		$this->assertNotSame( array(), $upcoming );
		foreach ( $upcoming as $event ) {
			$this->assertNotSame( '', trim( (string) $event['cta_label'] ), $event['title'] . ' has no button label' );
			$this->assertNotSame( '', trim( (string) $event['cta_href'] ), $event['title'] . ' has no button link' );
		}
	}

	public function test_no_past_event_offers_one(): void {
		foreach ( $this->events( 'past' ) as $event ) {
			$this->assertSame( '', trim( (string) $event['cta_label'] ), $event['title'] . ' is over and should offer nothing' );
		}
	}

	public function test_the_events_page_renders_a_button_on_every_upcoming_card(): void {
		$html = Blueworx_Clubhouse_Sections::event_grid( array(
			'eyebrow' => 'E',
			'heading' => 'H',
			'cards'   => array_map(
				static fn ( array $e ): array => array(
					'tag'       => (string) $e['tag'],
					'date'      => (string) $e['date'],
					'title'     => (string) $e['title'],
					'detail'    => (string) $e['detail'],
					'cta_label' => (string) $e['cta_label'],
					'cta_href'  => (string) $e['cta_href'],
				),
				$this->events( 'upcoming' )
			),
		) );
		$this->assertSame( count( $this->events( 'upcoming' ) ), substr_count( $html, 'ch-event__cta' ) );
	}
}
